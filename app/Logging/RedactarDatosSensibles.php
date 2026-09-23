<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

// Enmascara credenciales y PII medica antes de escribir cualquier log
//
// Limite conocido: una contrasena escrita en texto libre (p.ej. "clave: abc123"
// dentro de un mensaje interpolado) no tiene un patron detectable de forma
// confiable y NO se enmascara por regex. La regla del equipo es no interpolar
// secretos en el mensaje: siempre deben pasarse en $context con su propia
// clave (password, token, etc.), que SI se enmascara por nombre de clave
// (ver self::CLAVES), sin importar el patron del valor.
class RedactarDatosSensibles implements ProcessorInterface
{
	public const MASCARA = '[redactado]';

	// Comparacion en minusculas
	public const CLAVES = [
		'password', 'password_confirmation', 'current_password',
		'token', '_token', 'authorization', 'cookie',
		'curp', 'email',
		'allergies', 'chronic_conditions', 'blood_type',
		'emergency_contact_name', 'emergency_contact_phone',
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
			if (is_string($clave) && in_array(strtolower($clave), self::CLAVES, true)) {
				$datos[$clave] = self::MASCARA;
			} elseif (is_array($valor)) {
				$datos[$clave] = $this->redactar($valor);
			} elseif (is_string($valor)) {
				$datos[$clave] = $this->redactar_texto($valor);
			}
		}

		return $datos;
	}

	// Enmascara, dentro de un texto libre, cualquier coincidencia de los PATRONES
	private function redactar_texto(string $texto): string
	{
		return preg_replace(self::PATRONES, self::MASCARA, $texto);
	}
}
