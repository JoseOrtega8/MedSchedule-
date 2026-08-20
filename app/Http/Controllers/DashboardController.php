<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Appointment;
use App\Models\ActivityLog;
use App\Models\PatientProfile;
use App\Services\DashboardStatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DashboardController extends Controller
{
	/**
	 * Detecta rol y retorna datos correspondientes
	 */
	public function index(Request $request)
	{
		$user = Auth::user();

		if ($user->hasRole('admin')) {
			return view('admin.dashboard');
		} elseif ($user->hasRole('doctor')) {
			return redirect()->route('doctor.agenda');
		} elseif ($user->hasRole('patient')) {
			return view('patient.dashboard');
		}

		abort(403);
	}

	/**
	 * Dashboard del administrador — estadísticas cacheadas vía DashboardStatsService
	 */
	public function adminData(DashboardStatsService $stats)
	{
		return response()->json($stats->adminStats());
	}

	/**
	 * Dashboard del doctor
	 */
	public function agendaData()
	{
		$user = Auth::user();
		$today = Carbon::today();

		$appointments = Appointment::where('doctor_id', $user->id)
			->get()
			->map(function ($a) {
				return [
					'id' => $a->id,
					'date' => $a->appointment_date, // 👈 IMPORTANTE
					'start_time' => $a->start_time,
					'end_time' => $a->end_time,
					'status' => $a->status,
					'patient' => $a->patient_name ?? 'Paciente',
					'specialty' => $a->specialty ?? 'General',
					'reason' => $a->reason ?? 'Consulta',
					'color' => '#0d6efd',
					'initials' => substr($a->patient_name ?? 'P', 0, 2),
					'appointment_history' => [], // opcional
				];
			});

		return response()->json([
			'agenda_items' => $appointments,
			'reference_date' => $today->toDateString(),
		]);
	}

	/**
	 * Dashboard del paciente
	 */
	private function patientDashboard()
	{
		$user = Auth::user();

		$data = [
			'proximas_citas' => Appointment::where('patient_id', $user->id)
				->whereIn('status', ['pending', 'confirmed'])
				->whereDate('appointment_date', '>=', Carbon::today())
				->orderBy('appointment_date')
				->get(),
			'citas_por_status' => Appointment::where('patient_id', $user->id)
				->selectRaw('status, count(*) as total')
				->groupBy('status')
				->pluck('total', 'status'),
			'perfil' => $user->patientProfile,
		];

		return response()->json($data);
	}

	/**
	 * Usuarios agrupados por fecha para gráfica (solo admin) — cacheado
	 */
	public function getUsersChart(Request $request, DashboardStatsService $stats)
	{
		if (!Auth::user()->hasRole('admin')) {
			abort(403);
		}

		return response()->json($stats->usersChart($request));
	}

	/**
	 * Citas agrupadas por fecha y status para gráfica (solo admin) — cacheado
	 */
	public function getAppointmentsChart(Request $request, DashboardStatsService $stats)
	{
		if (!Auth::user()->hasRole('admin')) {
			abort(403);
		}

		return response()->json($stats->appointmentsChart($request));
	}

	/**
	 * Últimos 10 registros de activity_logs (solo admin)
	 */
	public function getRecentActivity()
	{
		if (!Auth::user()->hasRole('admin')) {
			abort(403);
		}

		return response()->json(
			ActivityLog::with('user')
				->orderBy('created_at', 'desc')
				->take(10)
				->get()
		);
	}

	public function doctorData()
	{
		return $this->doctorDashboard();
	}

	public function patientData()
	{
		return $this->patientDashboard();
	}
}
