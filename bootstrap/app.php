<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
	->withRouting(
		web: __DIR__ . '/../routes/web.php',
		commands: __DIR__ . '/../routes/console.php',
		health: '/up',
		then: function () {
			\Illuminate\Support\Facades\Route::middleware([])
				->group(base_path('routes/observabilidad.php'));
		},
	)
	->withMiddleware(function (Middleware $middleware): void {
		// Span raiz de cada peticion (unidad 3); va primero para envolver al resto
		$middleware->prepend(\App\Http\Middleware\IniciarTraza::class);

		$middleware->alias([
			'role'       => \Spatie\Permission\Middleware\RoleMiddleware::class,
			'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
			'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
		]);

		$middleware->append(\App\Http\Middleware\SecurityHeaders::class);

		// Metricas de Prometheus de cada peticion (unidad 3)
		$middleware->append(\App\Http\Middleware\RegistrarMetricasHttp::class);
	})
	->withExceptions(function (Exceptions $exceptions): void {
		//
	})->create();
