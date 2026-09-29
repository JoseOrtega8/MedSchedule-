<?php

namespace Tests\Feature\Trazas;

use OpenTelemetry\API\Behavior\Internal\Logging;
use OpenTelemetry\API\Behavior\Internal\LogWriter\Psr3LogWriter;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Tests\TestCase;

// Los fallos de exportacion a Tempo deben llegar al log de Laravel
class LogExportacionTest extends TestCase
{
	protected function tearDown(): void
	{
		Logging::reset();

		parent::tearDown();
	}

	public function test_trazas_habilitadas_dirigen_el_log_interno_a_laravel(): void
	{
		Logging::reset();
		config(['trazas.habilitadas' => true]);
		$this->app->forgetInstance(TracerProviderInterface::class);

		$this->app->make(TracerProviderInterface::class);

		$this->assertInstanceOf(Psr3LogWriter::class, Logging::logWriter());
	}
}
