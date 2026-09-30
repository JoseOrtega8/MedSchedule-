<?php

namespace Tests\Feature\Auditoria;

use App\Models\ActivityLog;
use App\Providers\AuditoriaServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionProperty;
use RuntimeException;
use Tests\TestCase;

class AuditoriaServiceProviderTest extends TestCase
{
	use RefreshDatabase;

	private function simular_produccion_sin_llave(): void
	{
		config(['auditoria.llave_hmac' => '']);
		$this->app['env'] = 'production';
	}

	// Illuminate\Foundation\Application::runningInConsole() cachea el resultado
	// en la propiedad protegida isRunningInConsole (PHP_SAPI === 'cli' por
	// defecto). Se fuerza por reflexion para simular una peticion HTTP dentro
	// de una suite que corre en CLI.
	private function forzar_fuera_de_consola(): void
	{
		$propiedad = new ReflectionProperty($this->app, 'isRunningInConsole');
		$propiedad->setAccessible(true);
		$propiedad->setValue($this->app, false);
	}

	// composer install en CI (sin .env, por lo tanto APP_ENV=production) corre
	// `php artisan package:discover`, que arranca todos los ServiceProvider en
	// consola. Sin esta relajacion, boot() lanzaba y `composer install` fallaba.
	public function test_boot_en_consola_produccion_sin_llave_no_lanza(): void
	{
		$this->simular_produccion_sin_llave();

		(new AuditoriaServiceProvider($this->app))->boot();

		$this->assertTrue(true);
	}

	// Atendiendo HTTP en produccion sin llave si debe lanzar: es el caso real
	// que la proteccion original queria evitar (servir trafico sin poder sellar).
	public function test_boot_atendiendo_http_produccion_sin_llave_lanza(): void
	{
		$this->simular_produccion_sin_llave();
		$this->forzar_fuera_de_consola();

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Falta AUDIT_HMAC_KEY en produccion.');

		(new AuditoriaServiceProvider($this->app))->boot();
	}

	// Aunque boot() ya no lanza en consola, SelladorAuditoria::llave() (ver
	// app/Services/Auditoria/SelladorAuditoria.php) SI lanza en produccion al
	// intentar sellar sin llave: la proteccion contra sellar sin llave en
	// produccion se mantiene aunque el provider la relaje para arrancar en consola.
	public function test_sellar_en_produccion_sin_llave_lanza(): void
	{
		$this->simular_produccion_sin_llave();

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Falta AUDIT_HMAC_KEY: la auditoria no puede sellarse en produccion.');

		ActivityLog::create(['action' => 'uno']);
	}
}
