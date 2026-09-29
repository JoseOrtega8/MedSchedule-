<?php

namespace App\Observability\Trazas;

use InvalidArgumentException;
use OpenTelemetry\API\Behavior\LogsMessagesTrait;
use OpenTelemetry\Contrib\Otlp\ContentTypes;
use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\SDK\Common\Future\CancellationInterface;
use OpenTelemetry\SDK\Common\Future\FutureInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use Throwable;

// Exportador OTLP/HTTP con JSON. Mismo contrato que
// OpenTelemetry\Contrib\Otlp\SpanExporter, pero codifica con CodificadorOtlpJson
// en lugar de los mensajes de google/protobuf en PHP puro. Medido en la
// microprueba (k6-atribucion.txt): forceFlush() bajo de 3.2 ms a 0.96 ms (p50).
// Los errores no se lanzan: se registran con el logger interno de OpenTelemetry
// (TrazasServiceProvider lo dirige al log de Laravel) y export() devuelve false;
// el BatchSpanProcessor atrapa cualquier excepcion que aun escape del exportador.
final class ExportadorOtlpJson implements SpanExporterInterface
{
	use LogsMessagesTrait;

	public function __construct(private TransportInterface $transporte)
	{
		if ($transporte->contentType() !== ContentTypes::JSON) {
			throw new InvalidArgumentException('ExportadorOtlpJson requiere un transporte application/json');
		}
	}

	public function export(iterable $batch, ?CancellationInterface $cancellation = null): FutureInterface
	{
		return $this->transporte
			->send(CodificadorOtlpJson::codificar($batch), $cancellation)
			->map(function (?string $respuesta): bool {
				// El colector responde ExportTraceServiceResponse; un cuerpo vacio es exito
				if ($respuesta === null || $respuesta === '') {
					return true;
				}
				$datos = json_decode($respuesta, true);
				if (json_last_error() !== JSON_ERROR_NONE || !is_array($datos)) {
					// Respuesta 2xx que no es JSON: los spans probablemente llegaron, pero
					// no se puede confirmar; se avisa en lugar de callar
					self::logWarning('Export response is not valid JSON', ['json_error' => json_last_error_msg()]);

					return true;
				}
				$parcial = $datos['partialSuccess'] ?? null;

				if (is_array($parcial) && (int) ($parcial['rejectedSpans'] ?? 0) > 0) {
					self::logError('Export partial success', [
						'rejected_spans' => (int) $parcial['rejectedSpans'],
						'error_message' => (string) ($parcial['errorMessage'] ?? ''),
					]);

					return false;
				}
				if (is_array($parcial) && ($parcial['errorMessage'] ?? '') !== '') {
					self::logWarning('Export success with warnings/suggestions', ['error_message' => (string) $parcial['errorMessage']]);
				}

				return true;
			})
			->catch(static function (Throwable $error): bool {
				self::logError('Export failure', ['exception' => $error]);

				return false;
			});
	}

	public function shutdown(?CancellationInterface $cancellation = null): bool
	{
		return $this->transporte->shutdown($cancellation);
	}

	public function forceFlush(?CancellationInterface $cancellation = null): bool
	{
		return $this->transporte->forceFlush($cancellation);
	}
}
