<?php

namespace App\Http\Middleware;

use App\Observability\Trazas\Trazas;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

// Abre el span raiz de cada peticion y deja el trace_id disponible para
// logs, auditoria y la pagina de error
class IniciarTraza
{
	public function __construct(private Trazas $trazas)
	{
	}

	public function handle(Request $request, Closure $next): Response
	{
		// El traceparent entrante se acepta tal cual: un cliente puede fijar el
		// trace_id o marcarlo como no muestreado. Aceptable en local; en
		// produccion deberia validarse o reescribirse en el borde (proxy/WAF).

		// Las cabeceras llegan como arreglos; el propagador espera cadenas
		$cabeceras = array_map(
			fn ($valor) => is_array($valor) ? implode(',', $valor) : $valor,
			$request->headers->all()
		);
		$contexto_padre = TraceContextPropagator::getInstance()->extract($cabeceras);

		$span = $this->trazas->tracer()
			->spanBuilder('HTTP ' . $request->method())
			->setParent($contexto_padre)
			->setSpanKind(SpanKind::KIND_SERVER)
			->setAttribute('http.request.method', $request->method())
			->startSpan();
		$alcance = $span->activate();

		$trace_id = $span->getContext()->isValid() ? $span->getContext()->getTraceId() : null;
		$request->attributes->set('trace_id', $trace_id);
		$request->attributes->set('request_id', (string) Str::uuid());

		try {
			$respuesta = $next($request);

			$ruta = $request->route();
			$span->updateName($request->method() . ' /' . ltrim($ruta?->uri() ?? 'sin_ruta', '/'));
			$span->setAttribute('http.route', $ruta?->getName() ?? $ruta?->uri() ?? 'sin_ruta');
			// Solo la plantilla (reset-password/{token}), nunca la ruta real:
			// la ruta real puede llevar tokens o firmas en sus parametros
			if ($ruta) {
				$span->setAttribute('url.template', $ruta->uri());
			}
			$span->setAttribute('http.response.status_code', $respuesta->getStatusCode());
			if ($request->user()) {
				$span->setAttribute('enduser.id', (string) $request->user()->getAuthIdentifier());
			}
			if ($respuesta->getStatusCode() >= 500) {
				$span->setStatus(StatusCode::STATUS_ERROR);
			}
			if ($trace_id !== null) {
				$respuesta->headers->set('X-Trace-Id', $trace_id);
			}

			return $respuesta;
		} catch (Throwable $error) {
			$span->recordException($error);
			$span->setStatus(StatusCode::STATUS_ERROR);
			throw $error;
		} finally {
			$alcance->detach();
			$span->end();
		}
	}

	public function terminate(Request $request, Response $response): void
	{
		$this->trazas->vaciar();
	}
}
