<?php

namespace App\Providers;

use App\Services\Auditoria\SelladorAuditoria;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AuditoriaServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->app->singleton(SelladorAuditoria::class);
	}

	public function boot(): void
	{
		// En produccion, atendiendo HTTP, la app no arranca sin llave de auditoria.
		// En consola (p.ej. `php artisan package:discover`, que corre este boot()
		// durante `composer install`) se permite arrancar sin llave: la proteccion
		// contra sellar sin llave en produccion se mantiene igual, porque
		// SelladorAuditoria::llave() (app/Services/Auditoria/SelladorAuditoria.php)
		// lanza en produccion en el momento de intentar sellar un registro.
		if (! $this->app->runningInConsole()
			&& $this->app->environment('production')
			&& (string) config('auditoria.llave_hmac') === '') {
			throw new RuntimeException('Falta AUDIT_HMAC_KEY en produccion.');
		}
	}
}
