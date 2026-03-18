<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class ActivityLogControllerTest extends TestCase
{
	use RefreshDatabase;

	public function test_logs_endpoint_returns_filters_and_log_rows(): void
	{
		$role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
		$admin = User::factory()->create();
		$admin->assignRole($role);

		$response = $this->actingAs($admin)->getJson(route('admin.logs.data'));

		$response->assertOk()
			->assertJsonStructure([
				'logs',
				'filters' => [
					'actions',
					'models',
				],
			]);
	}
}
