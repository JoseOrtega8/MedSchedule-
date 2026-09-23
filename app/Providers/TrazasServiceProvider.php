<?php

namespace App\Providers;

use App\Observability\Trazas\Trazas;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use OpenTelemetry\API\Trace\NoopTracerProvider;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
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
use Throwable;

class TrazasServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->app->singleton(TracerProviderInterface::class, function () {
			// Apagadas: proveedor nulo, sin costo ni conexiones
			if (!config('trazas.habilitadas')) {
				return new NoopTracerProvider();
			}

			// Tempo inalcanzable no debe frenar la peticion: con los valores por
			// defecto (timeout 10 s, 3 reintentos) vaciar() bloqueaba ~40 s.
			// Timeout de 1 s (segundos, se pasa al cliente HTTP) y sin reintentos:
			// si falla, esos spans se pierden y solo queda el aviso en el log.
			$transporte = (new OtlpHttpTransportFactory())->create(
				rtrim((string) config('trazas.endpoint'), '/') . '/v1/traces',
				'application/json',
				timeout: 1.0,
				maxRetries: 0,
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

	public function boot(): void
	{
		$this->trazar_consultas();
		$this->trazar_jobs();
	}

	// Cada consulta SQL es un span hijo del span activo. Solo la sentencia
	// parametrizada: los bindings pueden contener PII y nunca se guardan.
	private function trazar_consultas(): void
	{
		DB::listen(function (QueryExecuted $consulta) {
			if (!Span::getCurrent()->isRecording()) {
				return;
			}

			$fin = (int) (microtime(true) * 1e9);
			$inicio = $fin - (int) ($consulta->time * 1e6);

			$span = app(Trazas::class)->tracer()
				->spanBuilder('db.query')
				->setSpanKind(SpanKind::KIND_CLIENT)
				->setStartTimestamp($inicio)
				->setAttribute('db.system', $consulta->connection->getDriverName())
				->setAttribute('db.statement', $consulta->sql)
				->setAttribute('db.connection', $consulta->connectionName)
				->startSpan();
			$span->end($fin);
		});
	}

	// Cada job de cola abre un span propio y lo envia al terminar
	private function trazar_jobs(): void
	{
		$abiertos = [];

		Queue::before(function (JobProcessing $evento) use (&$abiertos) {
			if (!config('trazas.habilitadas')) {
				return;
			}
			$span = app(Trazas::class)->tracer()
				->spanBuilder('job ' . $evento->job->resolveName())
				->setSpanKind(SpanKind::KIND_CONSUMER)
				->setAttribute('messaging.destination.name', $evento->job->getQueue())
				->startSpan();
			$abiertos[$evento->job->getJobId() ?? spl_object_id($evento->job)] = [$span, $span->activate()];
		});

		$cerrar = function ($evento, ?Throwable $error = null) use (&$abiertos) {
			$clave = $evento->job->getJobId() ?? spl_object_id($evento->job);
			if (!isset($abiertos[$clave])) {
				return;
			}
			[$span, $alcance] = $abiertos[$clave];
			unset($abiertos[$clave]);
			if ($error !== null) {
				$span->recordException($error);
				$span->setStatus(StatusCode::STATUS_ERROR);
			}
			$alcance->detach();
			$span->end();
			app(Trazas::class)->vaciar();
		};

		Queue::after(fn (JobProcessed $evento) => $cerrar($evento));
		Queue::failing(fn (JobFailed $evento) => $cerrar($evento, $evento->exception));
		// Si al job le quedan reintentos, Laravel lo libera de vuelta a la cola sin
		// disparar JobProcessed ni JobFailed: el guard evita el doble cierre cuando,
		// en el ultimo intento, JobFailed ya cerro el mismo span antes que este evento.
		Queue::exceptionOccurred(fn (JobExceptionOccurred $evento) => $cerrar($evento, $evento->exception));
	}
}
