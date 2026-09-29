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
// en lugar de los mensajes de google/protobuf en PHP puro (~0.15 ms por span mas
// ~1 ms fijo por peticion para cargar descriptores, medido).
// Los errores se registran con el logger interno de OpenTelemetry, igual que el
// exportador oficial; Trazas::vaciar() atiende las excepciones que escapen.
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
				$datos = ($respuesta === null || $respuesta === '') ? [] : json_decode($respuesta, true);
				$parcial = is_array($datos) ? ($datos['partialSuccess'] ?? null) : null;

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
