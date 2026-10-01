<?php

namespace App\Http\Controllers;

use App\Observability\Metricas\Metricas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Prometheus\RenderTextFormat;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

// Expone las metricas a Prometheus; para cualquier otro cliente la ruta no existe
class MetricasController extends Controller
{
	public function __invoke(Request $request, Metricas $metricas): Response
	{
		$esperado = (string) config('metricas.token');
		$recibido = (string) $request->bearerToken();

		// Sin token configurado o con token incorrecto se responde 404, no 401,
		// para no revelar que el endpoint existe
		if ($esperado === '' || !hash_equals($esperado, $recibido)) {
			abort(404);
		}

		try {
			$cuerpo = $metricas->exportar();
		} catch (Throwable $error) {
			Log::warning('No se pudieron exportar las metricas', ['error' => $error->getMessage()]);
			abort(503);
		}

		return response($cuerpo, 200, ['Content-Type' => RenderTextFormat::MIME_TYPE]);
	}
}
