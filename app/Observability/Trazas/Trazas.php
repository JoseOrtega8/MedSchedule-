<?php

namespace App\Observability\Trazas;

use App\Support\MensajeSeguro;
use Illuminate\Support\Facades\Log;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface as ProveedorSdk;
use Throwable;

// Fachada minima sobre OpenTelemetry para el resto de la aplicacion
class Trazas
{
	public function __construct(private TracerProviderInterface $proveedor)
	{
	}

	public function tracer(): TracerInterface
	{
		return $this->proveedor->getTracer('medschedule');
	}

	public function trace_id_actual(): ?string
	{
		$contexto = Span::getCurrent()->getContext();

		return $contexto->isValid() ? $contexto->getTraceId() : null;
	}

	public function span_id_actual(): ?string
	{
		$contexto = Span::getCurrent()->getContext();

		return $contexto->isValid() ? $contexto->getSpanId() : null;
	}

	// Ejecuta $accion dentro de un span hijo del span activo
	public function en_span(string $nombre, callable $accion, array $atributos = []): mixed
	{
		$span = $this->tracer()
			->spanBuilder($nombre)
			->setSpanKind(SpanKind::KIND_CLIENT)
			->setAttributes($atributos)
			->startSpan();
		$alcance = $span->activate();

		try {
			return $accion();
		} catch (Throwable $error) {
			// No se usa recordException(): guarda el stacktrace con argumentos
			// y, en una QueryException, exception.message trae los bindings
			// (getMessage() los interpola). addEvent() con MensajeSeguro deja
			// el mismo evento 'exception' pero sin esos valores.
			$span->addEvent('exception', [
				'exception.type' => $error::class,
				'exception.message' => MensajeSeguro::de_excepcion($error),
			]);
			$span->setStatus(StatusCode::STATUS_ERROR);
			throw $error;
		} finally {
			$alcance->detach();
			$span->end();
		}
	}

	// Envia los spans pendientes; se llama al terminar la peticion o el job
	public function vaciar(): void
	{
		if (!$this->proveedor instanceof ProveedorSdk) {
			return;
		}

		try {
			$this->proveedor->forceFlush();
		} catch (Throwable $error) {
			// Si Tempo no responde, la peticion ya se atendio; solo se avisa
			Log::warning('No se pudieron enviar las trazas', ['error' => $error->getMessage()]);
		}
	}
}
