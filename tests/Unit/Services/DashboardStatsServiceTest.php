<?php

namespace Tests\Unit\Services;

use App\Services\DashboardStatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardStatsServiceTest extends TestCase
{
	public function test_admin_stats_uses_cache_remember_with_correct_key_and_ttl(): void
	{
		$expected = ['stats' => ['totalUsers' => 5]];

		Cache::shouldReceive('remember')
			->once()
			->with('dashboard.admin.stats', 120, \Mockery::type('Closure'))
			->andReturn($expected);

		$service = new DashboardStatsService();

		$result = $service->adminStats();

		$this->assertSame($expected, $result);
	}

	public function test_users_chart_cache_key_differs_by_date_range(): void
	{
		$service = new DashboardStatsService();

		$requestA = Request::create('/admin/dashboard/users-chart', 'GET', [
			'date_from' => '2026-01-01',
			'date_to' => '2026-06-30',
		]);

		$requestB = Request::create('/admin/dashboard/users-chart', 'GET', [
			'date_from' => '2026-07-01',
			'date_to' => '2026-12-31',
		]);

		Cache::shouldReceive('remember')
			->once()
			->with('dashboard.users-chart.2026-01-01.2026-06-30', 300, \Mockery::type('Closure'))
			->andReturn(['rango' => 'A']);

		Cache::shouldReceive('remember')
			->once()
			->with('dashboard.users-chart.2026-07-01.2026-12-31', 300, \Mockery::type('Closure'))
			->andReturn(['rango' => 'B']);

		$resultA = $service->usersChart($requestA);
		$resultB = $service->usersChart($requestB);

		$this->assertSame(['rango' => 'A'], $resultA);
		$this->assertSame(['rango' => 'B'], $resultB);
	}

	public function test_appointments_chart_uses_cache_remember_with_ttl_of_five_minutes(): void
	{
		$service = new DashboardStatsService();

		$request = Request::create('/admin/dashboard/appointments-chart', 'GET');

		Cache::shouldReceive('remember')
			->once()
			->with('dashboard.appointments-chart.all.all', 300, \Mockery::type('Closure'))
			->andReturn([['date' => '2026-08-01', 'status' => 'confirmed', 'total' => 2]]);

		$result = $service->appointmentsChart($request);

		$this->assertSame(
			[['date' => '2026-08-01', 'status' => 'confirmed', 'total' => 2]],
			$result
		);
	}
}
