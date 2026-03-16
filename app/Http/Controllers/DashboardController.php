<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Appointment;
use App\Models\ActivityLog;
use App\Models\PatientProfile;
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
			return view('doctor.dashboard');
		} elseif ($user->hasRole('patient')) {
			return view('patient.dashboard');
		}

		abort(403);
	}
	/**
	 * Dashboard del administrador
	 */
	private function adminDashboard()
	{
		$data = [
			'total_users'    => User::count(),
			'total_doctors'  => User::role('doctor')->count(),
			'total_patients' => User::role('patient')->count(),
			'appointments_by_status' => Appointment::selectRaw('status, count(*) as total')
				->groupBy('status')
				->pluck('total', 'status'),
			'recent_activity' => ActivityLog::with('user')
				->orderBy('created_at', 'desc')
				->take(10)
				->get(),
		];

		return response()->json($data);
	}

	/**
	 * Dashboard del doctor
	 */
	private function doctorDashboard()
	{
		$user = Auth::user();
		$today = Carbon::today();

		$data = [
			'citas_hoy' => Appointment::where('doctor_id', $user->id)
				->whereDate('appointment_date', $today)
				->get(),
			'total_pacientes_atendidos' => Appointment::where('doctor_id', $user->id)
				->where('status', 'completed')
				->count(),
			'citas_por_status' => Appointment::where('doctor_id', $user->id)
				->selectRaw('status, count(*) as total')
				->groupBy('status')
				->pluck('total', 'status'),
		];

		return response()->json($data);
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
	 * Usuarios agrupados por fecha para gráfica (solo admin)
	 */
	public function getUsersChart(Request $request)
	{
		if (!Auth::user()->hasRole('admin')) {
			abort(403);
		}

		$query = User::selectRaw('DATE(created_at) as date, count(*) as total')
			->groupBy('date')
			->orderBy('date');

		if ($request->filled('date_from')) {
			$query->whereDate('created_at', '>=', $request->date_from);
		}

		if ($request->filled('date_to')) {
			$query->whereDate('created_at', '<=', $request->date_to);
		}

		return response()->json($query->get());
	}

	/**
	 * Citas agrupadas por fecha y status para gráfica (solo admin)
	 */
	public function getAppointmentsChart(Request $request)
	{
		if (!Auth::user()->hasRole('admin')) {
			abort(403);
		}

		$query = Appointment::selectRaw('DATE(appointment_date) as date, status, count(*) as total')
			->groupBy('date', 'status')
			->orderBy('date');

		if ($request->filled('date_from')) {
			$query->whereDate('appointment_date', '>=', $request->date_from);
		}

		if ($request->filled('date_to')) {
			$query->whereDate('appointment_date', '<=', $request->date_to);
		}

		return response()->json($query->get());
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

	public function adminData()
	{
		return $this->adminDashboard();
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
