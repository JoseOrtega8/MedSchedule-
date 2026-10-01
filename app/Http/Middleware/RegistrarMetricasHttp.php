<?php

namespace App\Http\Middleware;

use App\Observability\Metricas\Metricas;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

// Mide cada peticion HTTP y la registra en Prometheus
class RegistrarMetricasHttp
{
	public function __construct(private Metricas $metricas)
	{
	}

	public function handle(Request $request, Closure $next): Response
	{
		$inicio = hrtime(true);

		$respuesta = $next($request);

		$ruta = $request->route();
		$nombre_ruta = $ruta ? ($ruta->getName() ?? 'sin_nombre') : 'sin_ruta';

		// El scrape de Prometheus no se cuenta como trafico de la aplicacion
		if ($nombre_ruta !== 'metricas') {
			try {
				$this->metricas->registrar_peticion(
					$request->method(),
					$nombre_ruta,
					$respuesta->getStatusCode(),
					(hrtime(true) - $inicio) / 1e9
				);
			} catch (Throwable $error) {
				// El monitoreo nunca debe tumbar la aplicacion
				Log::warning('No se pudieron registrar las metricas HTTP', ['error' => $error->getMessage()]);
			}
		}

		return $respuesta;
	}
}
