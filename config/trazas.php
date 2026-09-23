<?php

// Trazas OpenTelemetry. Apagadas por defecto: las pruebas y el CI no necesitan Tempo.
return [
	'habilitadas' => (bool) env('OTEL_ENABLED', false),
	'endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://127.0.0.1:4318'),
	'muestreo' => (float) env('OTEL_TRACES_SAMPLER_ARG', 1.0),
	'servicio' => env('OTEL_SERVICE_NAME', 'medschedule'),
];
