<?php

namespace App\Logging;

use App\Support\MensajeSeguro;
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
// La logica de PATRONES/MASCARA y de "una QueryException encadenada como
// causa" vive una sola vez en App\Support\MensajeSeguro: esta clase solo la
// usa (redactar_texto delega en MensajeSeguro::redactar_texto()).
class RedactarDatosSensibles implements ProcessorInterface
{
	// Subcadenas en minusculas: una clave se enmascara si CONTIENE cualquiera
	// (asi 'remember_token', 'access_token', 'api_secret' o
	// 'password_confirmation' quedan cubiertas sin listarlas una por una)
	// 'key' y 'hash' sueltos NO se agregan como subcadena: romperian claves
	// inocentes como 'cache_key' (debe quedar intacta). Por eso se listan
	// formas mas especificas: 'api_key' y 'apikey'.
	public const CLAVES = [
		'token', 'password', 'secret', 'authorization', 'cookie',
		'curp', 'email',
		'allergies', 'chronic_conditions', 'blood_type', 'emergency_contact',
		'api_key', 'apikey', 'credential', 'phone', 'telefono', 'signature',
	];

	public function __invoke(LogRecord $registro): LogRecord
	{
		// Los Throwable del contexto (a cualquier profundidad, antes de
		// convertirlos a su resumen) pueden coincidir textualmente con el
		// 'message' principal del registro: Handler::reportThrowable escribe
		// el log con $e->getMessage() como mensaje. MensajeSeguro::de_excepcion
		// ya recorre toda la cadena de causas (getPrevious()) buscando
		// QueryException encadenadas, directas o envueltas por otra excepcion.
		$mensaje = $this->redactar_mensaje_de_excepciones($registro->message, $registro->context);

		return $registro->with(
			message: $this->redactar_texto($mensaje),
			context: $this->redactar($registro->context),
			extra: $this->redactar($registro->extra),
		);
	}

	private function redactar_mensaje_de_excepciones(string $mensaje, array $contexto): string
	{
		foreach ($this->excepciones_en_contexto($contexto) as $excepcion) {
			$mensaje = str_replace($excepcion->getMessage(), MensajeSeguro::de_excepcion($excepcion), $mensaje);
		}

		return $mensaje;
	}

	private function excepciones_en_contexto(array $contexto): array
	{
		$encontradas = [];
		foreach ($contexto as $valor) {
			if ($valor instanceof Throwable) {
				$encontradas[] = $valor;
			} elseif (is_array($valor)) {
				$encontradas = array_merge($encontradas, $this->excepciones_en_contexto($valor));
			}
		}

		return $encontradas;
	}

	private function redactar(array $datos): array
	{
		foreach ($datos as $clave => $valor) {
			if (is_string($clave) && $this->es_clave_sensible($clave)) {
				$datos[$clave] = MensajeSeguro::MASCARA;
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

	// Reduce una excepcion a datos sin PII: mensaje seguro (MensajeSeguro),
	// clase, archivo y linea. Nunca el trace con argumentos.
	private function resumir_excepcion(Throwable $excepcion): array
	{
		return [
			'class' => $excepcion::class,
			'message' => MensajeSeguro::de_excepcion($excepcion),
			'file' => $excepcion->getFile(),
			'line' => $excepcion->getLine(),
		];
	}

	// Enmascara, dentro de un texto libre, cualquier coincidencia de los
	// PATRONES de MensajeSeguro (correo, Bearer/Basic, CURP): un unico lugar
	// para esa logica, sin duplicarla ni depender en circulo de MensajeSeguro.
	private function redactar_texto(string $texto): string
	{
		return MensajeSeguro::redactar_texto($texto);
	}
}
