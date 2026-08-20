<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardStatsService
{
	/**
	 * TTL en segundos para las estadísticas generales del dashboard.
	 */
	private const STATS_TTL = 120;

	/**
	 * TTL en segundos para las gráficas.
	 */
	private const CHART_TTL = 300; // 5 minutos

	/**
	 * Estadísticas del dashboard de administrador, cacheadas.
	 */
	public function adminStats(): array
	{
		return Cache::remember('dashboard.admin.stats', self::STATS_TTL, function () {
			return $this->buildAdminStats();
		});
	}

	/**
	 * Usuarios agrupados por fecha, cacheado por rango de fechas.
	 */
	public function usersChart(Request $request): array
	{
		$key = $this->chartCacheKey('dashboard.users-chart', $request);

		return Cache::remember($key, self::CHART_TTL, function () use ($request) {
			return $this->buildUsersChart($request);
		});
	}

	/**
	 * Citas agrupadas por fecha y status, cacheado por rango de fechas.
	 */
	public function appointmentsChart(Request $request): array
	{
		$key = $this->chartCacheKey('dashboard.appointments-chart', $request);

		return Cache::remember($key, self::CHART_TTL, function () use ($request) {
			return $this->buildAppointmentsChart($request);
		});
	}

	/**
	 * Construye las estadísticas del admin (lógica original, sin cambios de negocio).
	 */
	private function buildAdminStats(): array
	{
		$today = Carbon::today();
		$thisMonth = Carbon::now()->startOfMonth();

		$appointmentsToday = Appointment::whereDate('appointment_date', $today)->count();
		$appointmentsMonth = Appointment::whereDate('appointment_date', '>=', $thisMonth)->count();
		$pendingToday = Appointment::whereDate('appointment_date', $today)->where('status', 'pending')->count();

		$recentAppointments = Appointment::with(['patient', 'doctor'])
			->orderBy('appointment_date', 'desc')
			->take(10)
			->get()
			->map(fn($a) => [
				'patient' => $a->patient ? $a->patient->name . ' ' . $a->patient->last_name : 'N/A',
				'doctor' => $a->doctor ? 'Dr. ' . $a->doctor->last_name : 'N/A',
				'datetime' => $a->appointment_date . ' ' . $a->start_time,
				'status' => $a->status,
			]);

		$recentActivity = ActivityLog::with('user')
			->orderBy('created_at', 'desc')
			->take(5)
			->get()
			->map(fn($log) => [
				'icon' => match ($log->action) {
					'login' => 'bi-box-arrow-in-right',
					'logout' => 'bi-box-arrow-left',
					'create' => 'bi-plus-circle',
					'update' => 'bi-pencil-square',
					'delete' => 'bi-trash',
					default => 'bi-info-circle',
				},
				'tone' => match ($log->action) {
					'login' => 'primary',
					'logout' => 'secondary',
					'create' => 'success',
					'update' => 'warning',
					'delete' => 'danger',
					default => 'info',
				},
				'text' => $log->description,
				'time' => $log->created_at->diffForHumans(),
			]);

		return [
			'stats' => [
				'totalUsers' => User::count(),
				'appointmentsToday' => $appointmentsToday,
				'activeDoctors' => User::role('doctor')->count(),
				'appointmentsMonth' => $appointmentsMonth,
				'details' => [
					'totalUsers' => User::role('doctor')->count() . ' doctores, ' . User::role('patient')->count() . ' pacientes',
					'appointmentsToday' => $pendingToday . ' pendientes',
					'activeDoctors' => 'registrados',
					'appointmentsMonth' => 'este mes',
				],
			],
			'recentAppointments' => $recentAppointments->toArray(),
			'activityLogs' => $recentActivity->toArray(),
		];
	}

	private function buildUsersChart(Request $request): array
	{
		$query = User::selectRaw('DATE(created_at) as date, count(*) as total')
			->groupBy('date')
			->orderBy('date');

		if ($request->filled('date_from')) {
			$query->whereDate('created_at', '>=', $request->date_from);
		}

		if ($request->filled('date_to')) {
			$query->whereDate('created_at', '<=', $request->date_to);
		}

		return $query->get()->toArray();
	}

	private function buildAppointmentsChart(Request $request): array
	{
		$query = Appointment::selectRaw('DATE(appointment_date) as date, status, count(*) as total')
			->groupBy('date', 'status')
			->orderBy('date');

		if ($request->filled('date_from')) {
			$query->whereDate('appointment_date', '>=', $request->date_from);
		}

		if ($request->filled('date_to')) {
			$query->whereDate('appointment_date', '<=', $request->date_to);
		}

		return $query->get()->toArray();
	}

	/**
	 * Genera una clave de cache única según el rango de fechas solicitado.
	 */
	private function chartCacheKey(string $prefix, Request $request): string
	{
		$from = $request->input('date_from', 'all');
		$to = $request->input('date_to', 'all');

		return "{$prefix}.{$from}.{$to}";
	}
}
