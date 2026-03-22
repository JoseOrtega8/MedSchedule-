<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class ActivityLogControllerTest extends TestCase
{
	use RefreshDatabase;

	private function makeAdmin(): User
	{
		$role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
		$admin = User::factory()->create();
		$admin->assignRole($role);
		return $admin;
	}

	private function makeDoctor(): User
	{
		Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
		$role = Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'web']);
		$doctor = User::factory()->create();
		$doctor->assignRole($role);
		return $doctor;
	}

	private function makeLog(User $user): ActivityLog
	{
		return ActivityLog::create([
			'user_id'     => $user->id,
			'action'      => 'login',
			'model_type'  => 'User',
			'model_id'    => $user->id,
			'description' => 'Test log entry',
			'ip_address'  => '127.0.0.1',
			'user_agent'  => 'PHPUnit',
		]);
	}

	// ── index ──────────────────────────────────────────────────────────────

	public function test_logs_index_returns_view_for_admin(): void
	{
		$admin = $this->makeAdmin();
		$response = $this->actingAs($admin)->get(route('admin.logs'));
		$response->assertOk();
	}

	public function test_logs_index_rejects_non_admin(): void
	{
		$doctor = $this->makeDoctor();
		$response = $this->actingAs($doctor)->get(route('admin.logs'));
		$response->assertForbidden();
	}

	public function test_logs_index_requires_auth(): void
	{
		$response = $this->get(route('admin.logs'));
		$response->assertRedirect(route('login'));
	}

	// ── show ───────────────────────────────────────────────────────────────

	public function test_logs_show_returns_view_for_admin(): void
	{
		$admin = $this->makeAdmin();
		$log = $this->makeLog($admin);
		$response = $this->actingAs($admin)->get(route('admin.logs.show', $log->id));
		$response->assertOk();
	}

	public function test_logs_show_rejects_non_admin(): void
	{
		$admin = $this->makeAdmin();
		$log = $this->makeLog($admin);
		$doctor = $this->makeDoctor();
		$response = $this->actingAs($doctor)->get(route('admin.logs.show', $log->id));
		$response->assertForbidden();
	}

	public function test_logs_show_returns_404_for_missing_log(): void
	{
		$admin = $this->makeAdmin();
		$response = $this->actingAs($admin)->get(route('admin.logs.show', 999));
		$response->assertNotFound();
	}

	// ── indexData ──────────────────────────────────────────────────────────

	public function test_logs_data_returns_json_structure(): void
	{
		$admin = $this->makeAdmin();
		$this->makeLog($admin);
		$response = $this->actingAs($admin)->getJson(route('admin.logs.data'));
		$response->assertOk()
			->assertJsonStructure([
				'logs',
				'filters' => ['actions', 'models', 'defaultFrom', 'defaultTo'],
			]);
	}

	public function test_logs_data_filters_by_action(): void
	{
		$admin = $this->makeAdmin();
		$this->makeLog($admin);
		$response = $this->actingAs($admin)->getJson(route('admin.logs.data') . '?action=login');
		$response->assertOk();
		$logs = $response->json('logs');
		foreach ($logs as $log) {
			$this->assertEquals('login', $log['action']);
		}
	}

	public function test_logs_data_filters_by_model_type(): void
	{
		$admin = $this->makeAdmin();
		$this->makeLog($admin);
		$response = $this->actingAs($admin)->getJson(route('admin.logs.data') . '?model_type=User');
		$response->assertOk();
	}

	public function test_logs_data_filters_by_date_range(): void
	{
		$admin = $this->makeAdmin();
		$this->makeLog($admin);
		$response = $this->actingAs($admin)->getJson(route('admin.logs.data') . '?date_from=2026-01-01&date_to=2026-12-31');
		$response->assertOk();
	}

	public function test_logs_data_rejects_non_admin(): void
	{
		$doctor = $this->makeDoctor();
		$response = $this->actingAs($doctor)->getJson(route('admin.logs.data'));
		$response->assertForbidden();
	}

	public function test_logs_data_requires_auth(): void
	{
		$response = $this->getJson(route('admin.logs.data'));
		$response->assertUnauthorized();
	}

	// ── getByUser ──────────────────────────────────────────────────────────

	public function test_get_by_user_returns_logs_for_admin(): void
	{
		$admin = $this->makeAdmin();
		$this->makeLog($admin);
		$response = $this->actingAs($admin)->get(route('admin.logs.user', $admin->id));
		$response->assertOk();
	}

	public function test_get_by_user_rejects_non_admin(): void
	{
		$admin = $this->makeAdmin();
		$doctor = $this->makeDoctor();
		$response = $this->actingAs($doctor)->get(route('admin.logs.user', $admin->id));
		$response->assertForbidden();
	}
}
