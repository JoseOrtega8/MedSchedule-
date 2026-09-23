<?php

namespace Tests\Feature\Trazas;

use App\Observability\Trazas\Trazas;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Tests\TestCase;

// Sustituye el proveedor de trazas por uno que guarda los spans en memoria
abstract class TrazasTestCase extends TestCase
{
	protected InMemoryExporter $exportador;

	protected function setUp(): void
	{
		parent::setUp();

		$this->exportador = new InMemoryExporter();
		$proveedor = TracerProvider::builder()
			->addSpanProcessor(new SimpleSpanProcessor($this->exportador))
			->build();

		config(['trazas.habilitadas' => true]);
		$this->app->instance(TracerProviderInterface::class, $proveedor);
		$this->app->forgetInstance(Trazas::class);
	}

	// Devuelve los spans exportados cuyo nombre empieza con $prefijo
	protected function spans_que_empiezan_con(string $prefijo): array
	{
		return array_values(array_filter(
			$this->exportador->getSpans(),
			fn ($span) => str_starts_with($span->getName(), $prefijo)
		));
	}
}
