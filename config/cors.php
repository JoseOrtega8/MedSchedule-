<?php

return [

	/*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Solo se permiten los orígenes explícitamente listados abajo. Antes de
    | este cambio, Laravel usaba el default de fábrica (allowed_origins: '*'),
    | permitiendo peticiones desde cualquier dominio. Como todo el frontend
    | vive en el mismo Laravel (Blade + Bootstrap, sin SPA separado), solo se
    | permite el propio dominio de la app.
    |
    */

	'paths' => ['*'],

	'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

	'allowed_origins' => [
		env('APP_URL', 'http://localhost:8000'),
		'https://medschedule-production.up.railway.app',
	],

	'allowed_origins_patterns' => [],

	'allowed_headers' => ['*'],

	'exposed_headers' => [],

	'max_age' => 0,

	'supports_credentials' => true,

];
