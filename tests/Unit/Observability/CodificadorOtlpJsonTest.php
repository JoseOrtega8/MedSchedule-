<?php

namespace Tests\Unit\Observability;

use App\Observability\Trazas\CodificadorOtlpJson;
use OpenTelemetry\API\Trace\SpanContext;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TraceFlags;
use OpenTelemetry\API\Trace\TraceState;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\Contrib\Otlp\ContentTypes;
use OpenTelemetry\Contrib\Otlp\ProtobufSerializer;
use OpenTelemetry\Contrib\Otlp\SpanConverter;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\SDK\Common\Future\CancellationInterface;
use OpenTelemetry\SDK\Common\Future\CompletedFuture;
use OpenTelemetry\SDK\Common\Future\FutureInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\TestCase;

// El codificador propio debe producir exactamente el mismo OTLP/JSON que el
// exportador oficial (SpanConverter + google/protobuf), solo que sin pasar por
// los mensajes protobuf en PHP puro, que eran la mayor parte del costo por peticion
class CodificadorOtlpJsonTest extends TestCase
{
	// JSON que generaria el exportador oficial con transporte application/json
	private function json_oficial(array $spans): array
	{
		$transporte = new class implements TransportInterface {
			public function contentType(): string
			{
				return ContentTypes::JSON;
			}

			public function send(string $payload, ?CancellationInterface $cancellation = null): FutureInterface
			{
				return new CompletedFuture(null);
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
		$serializador = ProtobufSerializer::forTransport($transporte);

		return json_decode($serializador->serialize((new SpanConverter($serializador))->convert($spans)), true);
	}

	private function spans_de_muestra(): array
	{
		$exportador = new InMemoryExporter();
		$recurso = ResourceInfo::create(
			Attributes::create(['service.name' => 'medschedule', 'deployment.environment' => 'local']),
			'https://opentelemetry.io/schemas/1.25.0'
		);
		$proveedor = TracerProvider::builder()
			->addSpanProcessor(new SimpleSpanProcessor($exportador))
			->setResource($recurso)
			->build();

		// Padre remoto (traceparent entrante) con tracestate
		$padre_remoto = SpanContext::createFromRemoteParent(
			'4bf92f3577b34da6a3ce929d0e0e4736',
			'00f067aa0ba902b7',
			TraceFlags::SAMPLED,
			new TraceState('proveedor=valor')
		);
		$contexto_padre = Context::getRoot()->withContextValue(Span::wrap($padre_remoto));

		$tracer = $proveedor->getTracer('medschedule');
		$raiz = $tracer->spanBuilder('GET /panel')
			->setParent($contexto_padre)
			->setSpanKind(SpanKind::KIND_SERVER)
			->setAttribute('http.request.method', 'GET')
			->setAttribute('http.response.status_code', 500)
			->setAttribute('proporcion', 0.25)
			->setAttribute('entero_como_flotante', 2.0)
			->setAttribute('bandera', true)
			->setAttribute('falso', false)
			->setAttribute('vacio', '')
			->setAttribute('cero', 0)
			->setAttribute('lista', ['a', 'b'])
			->setAttribute('lista_enteros', [1, 2, 3])
			->setAttribute('acentos', 'cita médica ñ')
			->setAttribute('binario', "\xff\xfe\x00")
			->addLink($padre_remoto, ['motivo' => 'enlace'])
			->startSpan();
		$alcance = $raiz->activate();

		$hijo = $tracer->spanBuilder('db.query')
			->setSpanKind(SpanKind::KIND_CLIENT)
			->setAttribute('db.statement', 'select * from `users` where `id` = ?')
			->startSpan();
		$hijo->end();

		$interno = $tracer->spanBuilder('interno')->startSpan();
		$interno->addEvent('exception', ['exception.type' => 'RuntimeException', 'exception.message' => 'fallo']);
		$interno->addEvent('sin_atributos');
		$interno->setStatus(StatusCode::STATUS_ERROR, 'descripcion del error');
		$interno->end();

		$ok = $proveedor->getTracer('otro', '1.2.3', 'https://ejemplo/schema', ['alcance' => 'x'])
			->spanBuilder('job App\\Jobs\\Enviar')
			->setSpanKind(SpanKind::KIND_CONSUMER)
			->startSpan();
		$ok->setStatus(StatusCode::STATUS_OK);
		$ok->end();

		$productor = $tracer->spanBuilder('publicar')->setSpanKind(SpanKind::KIND_PRODUCER)->startSpan();
		$productor->end();

		$alcance->detach();
		$raiz->end();

		// Un span sin padre (raiz local) y de otro recurso
		$otro_exportador = new InMemoryExporter();
		$otro_proveedor = TracerProvider::builder()
			->addSpanProcessor(new SimpleSpanProcessor($otro_exportador))
			->setResource(ResourceInfo::create(Attributes::create(['service.name' => 'worker'])))
			->build();
		$otro_proveedor->getTracer('medschedule')->spanBuilder('suelto')->startSpan()->end();

		return array_merge($exportador->getSpans(), $otro_exportador->getSpans());
	}

	public function test_produce_el_mismo_json_que_el_exportador_oficial(): void
	{
		$spans = $this->spans_de_muestra();

		$propio = json_decode(CodificadorOtlpJson::codificar($spans), true);

		$this->assertEquals($this->json_oficial($spans), $propio);
	}

	public function test_lote_vacio(): void
	{
		$this->assertEquals($this->json_oficial([]), json_decode(CodificadorOtlpJson::codificar([]), true));
	}

	public function test_ids_en_hexadecimal_y_tiempos_como_cadena(): void
	{
		$spans = $this->spans_de_muestra();

		$propio = json_decode(CodificadorOtlpJson::codificar($spans), true);
		$primero = $propio['resourceSpans'][0]['scopeSpans'][0]['spans'][0];

		$this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $primero['traceId']);
		$this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $primero['spanId']);
		$this->assertIsString($primero['startTimeUnixNano']);
	}
}
