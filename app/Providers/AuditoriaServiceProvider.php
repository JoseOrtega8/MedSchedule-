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
		// En produccion la aplicacion no arranca sin llave de auditoria
		if ($this->app->environment('production') && (string) config('auditoria.llave_hmac') === '') {
			throw new RuntimeException('Falta AUDIT_HMAC_KEY en produccion.');
		}
	}
}
