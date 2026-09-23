<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

// Enmascara credenciales y PII medica antes de escribir cualquier log
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

	public function __invoke(LogRecord $registro): LogRecord
	{
		return $registro->with(
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
			}
		}

		return $datos;
	}
}
