<?php

// Configuracion de las metricas de Prometheus que expone la aplicacion
return [
	// 'redis' en ejecucion normal; 'memoria' en pruebas
	'almacen' => env('METRICAS_ALMACEN', 'redis'),

	// Token que Prometheus envia como Bearer; sin token el endpoint no existe
	'token' => env('METRICS_TOKEN'),

	// Redis propio del stack de monitoreo (puerto 6380 para no chocar con otro Redis local)
	'redis' => [
		'host' => env('METRICAS_REDIS_HOST', '127.0.0.1'),
		'port' => (int) env('METRICAS_REDIS_PORT', 6380),
		'timeout' => 0.2,
		'read_write_timeout' => 0.2,
		// Reutiliza el socket entre peticiones del mismo proceso PHP
		'persistente' => (bool) env('METRICAS_REDIS_PERSISTENTE', true),
	],
];
