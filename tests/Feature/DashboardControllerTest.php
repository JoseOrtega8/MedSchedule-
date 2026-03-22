<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Appointment;
use App\Models\Specialty;
use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class DashboardControllerTest extends TestCase
{
	use RefreshDatabase;

	private function setupRoles(): void
	{
		Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
		Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'web']);
		Role::firstOrCreate(['name' => 'patient', 'guard_name' => 'web']);
	}

	private function makeUser(string $role): User
	{
		$this->setupRoles();
		$user = User::factory()->create();
		$user->assignRole($role);
		return $user;
	}

	// ── index ──────────────────────────────────────────────────────────────

	public function test_admin_dashboard_returns_ok(): void
	{
		$admin = $this->makeUser('admin');
		$response = $this->actingAs($admin)->get(route('dashboard'));
		$response->assertOk();
	}

	public function test_doctor_dashboard_redirects_to_agenda(): void
	{
		$doctor = $this->makeUser('doctor');
		$response = $this->actingAs($doctor)->get(route('dashboard'));
		$response->assertRedirect(route('doctor.agenda'));
	}

	public function test_patient_dashboard_returns_ok(): void
	{
		$patient = $this->makeUser('patient');
		$response = $this->actingAs($patient)->get(route('dashboard'));
		$response->assertOk();
	}

	// ── adminData ──────────────────────────────────────────────────────────

	public function test_admin_data_endpoint_returns_json(): void
	{
		$admin = $this->makeUser('admin');
		$response = $this->actingAs($admin)->getJson(route('dashboard.data'));
		$response->assertOk()
			->assertJsonStructure(['stats', 'recentAppointments', 'activityLogs']);
	}

	public function test_admin_data_stats_contain_required_keys(): void
	{
		$admin = $this->makeUser('admin');
		$response = $this->actingAs($admin)->getJson(route('dashboard.data'));
		$response->assertOk()
			->assertJsonStructure([
				'stats' => ['totalUsers', 'appointmentsToday', 'activeDoctors', 'appointmentsMonth', 'details']
			]);
	}

	// ── getUsersChart ──────────────────────────────────────────────────────

	public function test_users_chart_returns_json_for_admin(): void
	{
		$admin = $this->makeUser('admin');
		$response = $this->actingAs($admin)->getJson(route('admin.dashboard.users-chart'));
		$response->assertOk();
	}

	public function test_users_chart_rejects_non_admin(): void
	{
		$doctor = $this->makeUser('doctor');
		$response = $this->actingAs($doctor)->getJson(route('admin.dashboard.users-chart'));
		$response->assertForbidden();
	}

	public function test_users_chart_filters_by_date_range(): void
	{
		$admin = $this->makeUser('admin');
		$response = $this->actingAs($admin)->getJson(route('admin.dashboard.users-chart') . '?date_from=2026-01-01&date_to=2026-12-31');
		$response->assertOk();
	}

	// ── getAppointmentsChart ───────────────────────────────────────────────

	public function test_appointments_chart_returns_json_for_admin(): void
	{
		$admin = $this->makeUser('admin');
		$response = $this->actingAs($admin)->getJson(route('admin.dashboard.appointments-chart'));
		$response->assertOk();
	}

	public function test_appointments_chart_rejects_non_admin(): void
	{
		$doctor = $this->makeUser('doctor');
		$response = $this->actingAs($doctor)->getJson(route('admin.dashboard.appointments-chart'));
		$response->assertForbidden();
	}

	public function test_appointments_chart_filters_by_date_range(): void
	{
		$admin = $this->makeUser('admin');
		$response = $this->actingAs($admin)->getJson(route('admin.dashboard.appointments-chart') . '?date_from=2026-01-01&date_to=2026-12-31');
		$response->assertOk();
	}

	// ── getRecentActivity ──────────────────────────────────────────────────

	public function test_recent_activity_returns_json_for_admin(): void
	{
		$admin = $this->makeUser('admin');
		$response = $this->actingAs($admin)->getJson(route('admin.dashboard.recent-activity'));
		$response->assertOk();
	}

	public function test_recent_activity_rejects_non_admin(): void
	{
		$doctor = $this->makeUser('doctor');
		$response = $this->actingAs($doctor)->getJson(route('admin.dashboard.recent-activity'));
		$response->assertForbidden();
	}

	// ── patientData ────────────────────────────────────────────────────────

	public function test_patient_data_returns_json(): void
	{
		$patient = $this->makeUser('patient');
		$response = $this->actingAs($patient)->getJson(route('patient.dashboard.data'));
		$response->assertOk()
			->assertJsonStructure(['proximas_citas', 'citas_por_status']);
	}
}
