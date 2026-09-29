<?php

namespace Tests\Unit\Observability;

use App\Observability\Trazas\CodificadorOtlpJson;
use App\Observability\Trazas\ExportadorOtlpJson;
use InvalidArgumentException;
use OpenTelemetry\Contrib\Otlp\ContentTypes;
use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\SDK\Common\Future\CancellationInterface;
use OpenTelemetry\SDK\Common\Future\CompletedFuture;
use OpenTelemetry\SDK\Common\Future\ErrorFuture;
use OpenTelemetry\SDK\Common\Future\FutureInterface;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ExportadorOtlpJsonTest extends TestCase
{
	// Transporte falso que guarda lo enviado y responde lo que se le indique
	private function transporte(FutureInterface $respuesta, string $tipo = ContentTypes::JSON): TransportInterface
	{
		return new class ($respuesta, $tipo) implements TransportInterface {
			public array $enviados = [];

			public function __construct(private FutureInterface $respuesta, private string $tipo)
			{
			}

			public function contentType(): string
			{
				return $this->tipo;
			}

			public function send(string $payload, ?CancellationInterface $cancellation = null): FutureInterface
			{
				$this->enviados[] = $payload;

				return $this->respuesta;
			}

			public function shutdown(?CancellationInterface $cancellation = null): bool
			{
				return true;
			}

			public function forceFlush(?CancellationInterface $cancellation = null): bool
			{
				return true;
			}
		};
	}

	private function spans(): array
	{
		$memoria = new InMemoryExporter();
		$proveedor = TracerProvider::builder()->addSpanProcessor(new SimpleSpanProcessor($memoria))->build();
		$proveedor->getTracer('medschedule')->spanBuilder('GET /')->startSpan()->end();

		return $memoria->getSpans();
	}

	public function test_envia_el_json_del_codificador(): void
	{
		$transporte = $this->transporte(new CompletedFuture(null));
		$spans = $this->spans();

		$resultado = (new ExportadorOtlpJson($transporte))->export($spans)->await();

		$this->assertTrue($resultado);
		$this->assertSame([CodificadorOtlpJson::codificar($spans)], $transporte->enviados);
	}

	public function test_respuesta_vacia_del_colector_es_exito(): void
	{
		$transporte = $this->transporte(new CompletedFuture('{}'));

		$this->assertTrue((new ExportadorOtlpJson($transporte))->export($this->spans())->await());
	}

	public function test_spans_rechazados_por_el_colector_es_fallo(): void
	{
		$transporte = $this->transporte(new CompletedFuture('{"partialSuccess":{"rejectedSpans":"1","errorMessage":"x"}}'));

		$this->assertFalse((new ExportadorOtlpJson($transporte))->export($this->spans())->await());
	}

	public function test_fallo_de_transporte_devuelve_false_sin_lanzar(): void
	{
		$transporte = $this->transporte(new ErrorFuture(new RuntimeException('Tempo caido')));

		$this->assertFalse((new ExportadorOtlpJson($transporte))->export($this->spans())->await());
	}

	public function test_exige_transporte_json(): void
	{
		$this->expectException(InvalidArgumentException::class);

		new ExportadorOtlpJson($this->transporte(new CompletedFuture(null), ContentTypes::PROTOBUF));
	}
}
