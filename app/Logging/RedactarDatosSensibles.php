<?php

namespace App\Logging;

use Illuminate\Database\QueryException;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Throwable;

// Enmascara credenciales y PII medica antes de escribir cualquier log
//
// Limite conocido: una contrasena escrita en texto libre (p.ej. "clave: abc123"
// dentro de un mensaje interpolado) no tiene un patron detectable de forma
// confiable y NO se enmascara por regex. La regla del equipo es no interpolar
// secretos en el mensaje: siempre deben pasarse en $context con su propia
// clave (password, token, etc.), que SI se enmascara por nombre de clave
// (ver self::CLAVES), sin importar el patron del valor.
//
// Las excepciones (Laravel las pone en context['exception']) se reducen a
// clase, mensaje redactado, archivo y linea: nunca el trace con argumentos.
class RedactarDatosSensibles implements ProcessorInterface
{
	public const MASCARA = '[redactado]';

	// Subcadenas en minusculas: una clave se enmascara si CONTIENE cualquiera
	// (asi 'remember_token', 'access_token', 'api_secret' o
	// 'password_confirmation' quedan cubiertas sin listarlas una por una)
	public const CLAVES = [
		'token', 'password', 'secret', 'authorization', 'cookie',
		'curp', 'email',
		'allergies', 'chronic_conditions', 'blood_type', 'emergency_contact',
	];

	// Patrones para detectar PII/credenciales dentro de texto libre: el mensaje
	// del log y los valores string de context/extra cuya clave no es sensible
	// (p.ej. un correo interpolado dentro de 'mensaje' => "reset para a@b.c").
	// La coincidencia completa se reemplaza por MASCARA, incluida la palabra
	// Bearer/Basic en el segundo patron: asi no se filtra ni el esquema de auth.
	public const PATRONES = [
		// correos electronicos
		'/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/',
		// credenciales Bearer/Basic en texto (se enmascara la coincidencia completa)
		'/\b(Bearer|Basic)\s+[A-Za-z0-9._~+\/=-]+/i',
		// CURP mexicana (18 caracteres)
		'/\b[A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z0-9]\d\b/',
	];

	public function __invoke(LogRecord $registro): LogRecord
	{
		return $registro->with(
			message: $this->redactar_texto($registro->message),
			context: $this->redactar($registro->context),
			extra: $this->redactar($registro->extra),
		);
	}

	private function redactar(array $datos): array
	{
		foreach ($datos as $clave => $valor) {
			if (is_string($clave) && $this->es_clave_sensible($clave)) {
				$datos[$clave] = self::MASCARA;
			} elseif ($valor instanceof Throwable) {
				$datos[$clave] = $this->resumir_excepcion($valor);
			} elseif (is_array($valor)) {
				$datos[$clave] = $this->redactar($valor);
			} elseif (is_string($valor)) {
				$datos[$clave] = $this->redactar_texto($valor);
			}
		}

		return $datos;
	}

	private function es_clave_sensible(string $clave): bool
	{
		$clave = strtolower($clave);
		foreach (self::CLAVES as $subcadena) {
			if (str_contains($clave, $subcadena)) {
				return true;
			}
		}

		return false;
	}

	// Reduce una excepcion a datos sin PII. De una QueryException se toma la
	// sentencia SIN bindings (getSql), porque getMessage() los interpola.
	private function resumir_excepcion(Throwable $excepcion): array
	{
		$mensaje = $excepcion instanceof QueryException ? $excepcion->getSql() : $excepcion->getMessage();

		return [
			'class' => $excepcion::class,
			'message' => $this->redactar_texto($mensaje),
			'file' => $excepcion->getFile(),
			'line' => $excepcion->getLine(),
		];
	}

	// Enmascara, dentro de un texto libre, cualquier coincidencia de los PATRONES
	private function redactar_texto(string $texto): string
	{
		return preg_replace(self::PATRONES, self::MASCARA, $texto);
	}
}
