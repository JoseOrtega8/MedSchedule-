<?php

namespace Tests\Feature\Metricas;

use App\Observability\Metricas\Metricas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricasHttpTest extends TestCase
{
	use RefreshDatabase;

	public function test_peticion_registra_contador_e_histograma(): void
	{
		$this->get(route('login'))->assertOk();

		$texto = app(Metricas::class)->exportar();

		$this->assertStringContainsString(
			'medschedule_http_requests_total{method="GET",route="login",status="200"} 1',
			$texto
		);
		$this->assertStringContainsString(
			'medschedule_http_request_duration_seconds_count{method="GET",route="login"} 1',
			$texto
		);
	}

	public function test_redis_caido_no_afecta_la_respuesta(): void
	{
		// Puerto cerrado: cualquier escritura de metricas falla
		config([
			'metricas.almacen' => 'redis',
			'metricas.redis.port' => 1,
		]);
		app()->forgetInstance(Metricas::class);

		$this->get(route('login'))->assertOk();
	}
}
