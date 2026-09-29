<?php

namespace App\Support;

use App\Logging\RedactarDatosSensibles;
use Illuminate\Database\QueryException;
use Throwable;

// Helper unico para obtener el mensaje de una excepcion sin PII: lo mismo lo
// usa RedactarDatosSensibles (mensaje del log y resumen del contexto) que los
// puntos que exportan trazas a Tempo (evento 'exception' del span), asi ambos
// caminos quedan seguros de la misma forma y con una sola definicion.
class MensajeSeguro
{
	public static function de_excepcion(Throwable $excepcion): string
	{
		if ($excepcion instanceof QueryException) {
			return self::de_query_exception($excepcion);
		}

		// Reutiliza los PATRONES/MASCARA de RedactarDatosSensibles: no se
		// duplica la lista de expresiones regulares en dos sitios.
		return preg_replace(
			RedactarDatosSensibles::PATRONES,
			RedactarDatosSensibles::MASCARA,
			$excepcion->getMessage()
		);
	}

	// getMessage() de una QueryException interpola los bindings dentro del SQL
	// (ver Illuminate\Database\QueryException::formatMessage): por eso el texto
	// seguro usa getSql(), la sentencia parametrizada sin bindings.
	private static function de_query_exception(QueryException $excepcion): string
	{
		$texto = 'QueryException: ' . $excepcion->getSql();
		$sqlstate = $excepcion->getCode();

		if (!empty($sqlstate)) {
			$texto .= ' [SQLSTATE ' . $sqlstate . ']';
		}

		return $texto;
	}
}
