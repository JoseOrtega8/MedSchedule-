<?php

namespace App\Observability\Trazas;

use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Common\Attribute\AttributesInterface;
use OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\SpanDataInterface;
use stdClass;

// Codifica spans en OTLP/JSON (ExportTraceServiceRequest) con arreglos de PHP y
// json_encode. Produce el mismo JSON que SpanConverter + google/protobuf (ver
// CodificadorOtlpJsonTest), pero sin construir mensajes protobuf en PHP puro:
// eso, mas cargar el pool de descriptores en cada peticion, era la mayor parte
// del costo de exportar las trazas.
// Reglas de proto3 JSON que se replican: campos con valor por defecto se omiten,
// int64 van como cadena, enums como entero y los ids en hexadecimal (OTLP/JSON).
final class CodificadorOtlpJson
{
	// SpanKind de la API -> enum Span.SpanKind de OTLP
	private const TIPOS = [
		SpanKind::KIND_INTERNAL => 1,
		SpanKind::KIND_SERVER => 2,
		SpanKind::KIND_CLIENT => 3,
		SpanKind::KIND_PRODUCER => 4,
		SpanKind::KIND_CONSUMER => 5,
	];

	// StatusCode de la API -> enum Status.StatusCode de OTLP
	private const ESTADOS = [
		StatusCode::STATUS_UNSET => 0,
		StatusCode::STATUS_OK => 1,
		StatusCode::STATUS_ERROR => 2,
	];

	// Mascaras de SpanFlags (trace.proto)
	private const TIENE_REMOTO = 0x100;
	private const ES_REMOTO = 0x200;

	/** @param iterable<SpanDataInterface> $spans */
	public static function codificar(iterable $spans): string
	{
		$recursos = [];
		$claves_recurso = [];
		$claves_alcance = [];

		// Agrupa por recurso y luego por alcance, en el orden en que aparecen
		foreach ($spans as $span) {
			$recurso = $span->getResource();
			$alcance = $span->getInstrumentationScope();

			$clave_recurso = $claves_recurso[spl_object_id($recurso)] ??= serialize([
				$recurso->getSchemaUrl(),
				$recurso->getAttributes()->toArray(),
				$recurso->getAttributes()->getDroppedAttributesCount(),
			]);
			$clave_alcance = $claves_alcance[spl_object_id($alcance)] ??= serialize([
				$alcance->getName(),
				$alcance->getVersion(),
				$alcance->getSchemaUrl(),
				$alcance->getAttributes()->toArray(),
				$alcance->getAttributes()->getDroppedAttributesCount(),
			]);

			if (!isset($recursos[$clave_recurso])) {
				$recursos[$clave_recurso] = ['recurso' => self::recurso_spans($recurso), 'alcances' => []];
			}
			if (!isset($recursos[$clave_recurso]['alcances'][$clave_alcance])) {
				$recursos[$clave_recurso]['alcances'][$clave_alcance] = self::alcance_spans($alcance);
			}
			$recursos[$clave_recurso]['alcances'][$clave_alcance]['spans'][] = self::span($span);
		}

		$solicitud = [];
		foreach ($recursos as $grupo) {
			$recurso_spans = $grupo['recurso'];
			$recurso_spans['scopeSpans'] = array_values($grupo['alcances']);
			$solicitud['resourceSpans'][] = $recurso_spans;
		}

		return json_encode(
			$solicitud === [] ? new stdClass() : $solicitud,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
		);
	}

	private static function recurso_spans(ResourceInfo $recurso): array
	{
		$datos = ['resource' => self::objeto(self::con_atributos([], $recurso->getAttributes()))];
		if ((string) $recurso->getSchemaUrl() !== '') {
			$datos['schemaUrl'] = (string) $recurso->getSchemaUrl();
		}

		return $datos;
	}

	private static function alcance_spans(InstrumentationScopeInterface $alcance): array
	{
		$scope = [];
		if ($alcance->getName() !== '') {
			$scope['name'] = $alcance->getName();
		}
		if ((string) $alcance->getVersion() !== '') {
			$scope['version'] = (string) $alcance->getVersion();
		}
		$datos = ['scope' => self::objeto(self::con_atributos($scope, $alcance->getAttributes()))];
		if ((string) $alcance->getSchemaUrl() !== '') {
			$datos['schemaUrl'] = (string) $alcance->getSchemaUrl();
		}
		$datos['spans'] = [];

		return $datos;
	}

	private static function span(SpanDataInterface $span): array
	{
		$contexto = $span->getContext();
		$padre = $span->getParentContext();

		$datos = [
			'traceId' => $contexto->getTraceId(),
			'spanId' => $contexto->getSpanId(),
		];
		self::agregar_si_no_vacio($datos, 'traceState', (string) $contexto->getTraceState());
		if ($padre->isValid()) {
			$datos['parentSpanId'] = $padre->getSpanId();
		}
		self::agregar_si_no_vacio($datos, 'flags', self::banderas($contexto->getTraceFlags(), $padre));
		self::agregar_si_no_vacio($datos, 'name', $span->getName());
		self::agregar_si_no_vacio($datos, 'kind', self::TIPOS[$span->getKind()] ?? 0);
		self::agregar_si_no_vacio($datos, 'startTimeUnixNano', self::entero64($span->getStartEpochNanos()));
		self::agregar_si_no_vacio($datos, 'endTimeUnixNano', self::entero64($span->getEndEpochNanos()));
		$datos = self::con_atributos($datos, $span->getAttributes());

		foreach ($span->getEvents() as $evento) {
			$datos_evento = [];
			self::agregar_si_no_vacio($datos_evento, 'timeUnixNano', self::entero64($evento->getEpochNanos()));
			self::agregar_si_no_vacio($datos_evento, 'name', $evento->getName());
			$datos['events'][] = self::objeto(self::con_atributos($datos_evento, $evento->getAttributes()));
		}
		self::agregar_si_no_vacio($datos, 'droppedEventsCount', $span->getTotalDroppedEvents());

		foreach ($span->getLinks() as $enlace) {
			$contexto_enlace = $enlace->getSpanContext();
			$datos_enlace = [
				'traceId' => $contexto_enlace->getTraceId(),
				'spanId' => $contexto_enlace->getSpanId(),
			];
			self::agregar_si_no_vacio($datos_enlace, 'traceState', (string) $contexto_enlace->getTraceState());
			$datos_enlace = self::con_atributos($datos_enlace, $enlace->getAttributes());
			self::agregar_si_no_vacio($datos_enlace, 'flags', self::banderas($contexto_enlace->getTraceFlags(), $contexto_enlace));
			$datos['links'][] = $datos_enlace;
		}
		self::agregar_si_no_vacio($datos, 'droppedLinksCount', $span->getTotalDroppedLinks());

		$estado = [];
		self::agregar_si_no_vacio($estado, 'message', $span->getStatus()->getDescription());
		self::agregar_si_no_vacio($estado, 'code', self::ESTADOS[$span->getStatus()->getCode()] ?? 0);
		$datos['status'] = self::objeto($estado);

		return $datos;
	}

	// Banderas de W3C mas los bits de "padre/enlace remoto" (igual que SpanConverter)
	private static function banderas(int $banderas_traza, SpanContextInterface $contexto_remoto): int
	{
		$banderas = $banderas_traza | self::TIENE_REMOTO;
		if ($contexto_remoto->isRemote()) {
			$banderas |= self::ES_REMOTO;
		}

		return $banderas;
	}

	private static function con_atributos(array $datos, AttributesInterface $atributos): array
	{
		foreach ($atributos as $clave => $valor) {
			$datos['attributes'][] = ['key' => (string) $clave, 'value' => self::valor($valor)];
		}
		self::agregar_si_no_vacio($datos, 'droppedAttributesCount', $atributos->getDroppedAttributesCount());

		return $datos;
	}

	// AnyValue de OTLP; replica AttributesConverter::convertAnyValue
	private static function valor(mixed $valor): stdClass
	{
		$resultado = new stdClass();

		if (is_array($valor)) {
			$valores = [];
			if ($valor === [] || array_key_first($valor) === 0) {
				foreach ($valor as $elemento) {
					$valores[] = self::valor($elemento);
				}
				$resultado->arrayValue = self::objeto($valores === [] ? [] : ['values' => $valores]);
			} else {
				foreach ($valor as $clave => $elemento) {
					$valores[] = ['key' => (string) $clave, 'value' => self::valor($elemento)];
				}
				$resultado->kvlistValue = self::objeto(['values' => $valores]);
			}
		} elseif (is_int($valor)) {
			$resultado->intValue = (string) $valor;
		} elseif (is_bool($valor)) {
			$resultado->boolValue = $valor;
		} elseif (is_float($valor)) {
			$resultado->doubleValue = is_finite($valor) ? $valor : (is_nan($valor) ? 'NaN' : ($valor > 0 ? 'Infinity' : '-Infinity'));
		} elseif (is_string($valor)) {
			if (mb_check_encoding($valor, 'UTF-8')) {
				$resultado->stringValue = $valor;
			} else {
				$resultado->bytesValue = base64_encode($valor);
			}
		}

		return $resultado;
	}

	// int64 de proto3 JSON: cadena decimal
	private static function entero64(int $valor): string
	{
		return $valor === 0 ? '' : (string) $valor;
	}

	// proto3 JSON omite los campos con su valor por defecto
	private static function agregar_si_no_vacio(array &$datos, string $clave, int|string $valor): void
	{
		if ($valor !== 0 && $valor !== '') {
			$datos[$clave] = $valor;
		}
	}

	// Un mensaje vacio se escribe {} y no []
	private static function objeto(array $datos): array|stdClass
	{
		return $datos === [] ? new stdClass() : $datos;
	}
}
