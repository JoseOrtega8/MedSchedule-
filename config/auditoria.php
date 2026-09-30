<?php

return [
	// Llave del sello HMAC de la auditoria. Obligatoria en produccion; nunca en el repositorio.
	'llave_hmac' => env('AUDIT_HMAC_KEY'),

	// Grafana, para el enlace "ver traza" del visor
	'grafana_url' => env('GRAFANA_URL', 'http://localhost:3000'),
];
