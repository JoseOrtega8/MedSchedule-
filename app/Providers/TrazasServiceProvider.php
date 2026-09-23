<?php

namespace App\Providers;

use App\Observability\Trazas\Trazas;
use Illuminate\Support\ServiceProvider;
use OpenTelemetry\API\Trace\NoopTracerProvider;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

class TrazasServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->app->singleton(TracerProviderInterface::class, function () {
			// Apagadas: proveedor nulo, sin costo ni conexiones
			if (!config('trazas.habilitadas')) {
				return new NoopTracerProvider();
			}

			$transporte = (new OtlpHttpTransportFactory())->create(
				rtrim((string) config('trazas.endpoint'), '/') . '/v1/traces',
				'application/json'
			);
			$recurso = ResourceInfoFactory::emptyResource()->merge(
				ResourceInfo::create(Attributes::create(['service.name' => config('trazas.servicio')]))
			);

			return TracerProvider::builder()
				->addSpanProcessor(BatchSpanProcessor::builder(new SpanExporter($transporte))->build())
				->setResource($recurso)
				->setSampler(new ParentBased(new TraceIdRatioBasedSampler((float) config('trazas.muestreo'))))
				->build();
		});

		$this->app->singleton(Trazas::class, fn ($app) => new Trazas($app->make(TracerProviderInterface::class)));
	}
}
