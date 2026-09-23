<?php

namespace App\Logging;

use App\Observability\Trazas\Trazas;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Throwable;

// Agrega a cada linea de log los identificadores que permiten saltar a su traza
class AgregarContextoTraza implements ProcessorInterface
{
	public function __invoke(LogRecord $registro): LogRecord
	{
		try {
			$trazas = app(Trazas::class);
			$peticion = app()->bound('request') ? request() : null;
			$guardia = auth()->guard();

			$extra = [
				'trace_id' => $trazas->trace_id_actual(),
				'span_id' => $trazas->span_id_actual(),
				'request_id' => $peticion?->attributes->get('request_id'),
				// hasUser no dispara consultas: evita recursion con el log de SQL
				'user_id' => $guardia->hasUser() ? $guardia->id() : null,
				'route' => $peticion?->route()?->getName(),
				'method' => $peticion?->method(),
			];
		} catch (Throwable $error) {
			// Si no se puede calcular el contexto, la linea se escribe igual y se marca
			$extra = ['contexto_no_disponible' => $error::class];
		}

		return $registro->with(extra: array_merge(
			$registro->extra,
			array_filter($extra, fn ($valor) => $valor !== null)
		));
	}
}
