<?php

namespace App\Providers;

use App\Observability\Metricas\Metricas;
use Illuminate\Support\ServiceProvider;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\Predis;

class MetricasServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->app->singleton(Metricas::class, function () {
			$config = config('metricas');

			$almacen = $config['almacen'] === 'memoria'
				? new InMemory()
				: new Predis(self::parametros_redis($config['redis']));

			// false: sin la metrica php_info que agrega la libreria por defecto
			return new Metricas(new CollectorRegistry($almacen, false));
		});
	}

	// Parametros de Predis. Con 'persistent' el socket sobrevive entre peticiones
	// del mismo proceso PHP (php-fpm o el servidor embebido) y no se reconecta a
	// Redis en cada peticion: medido, ahorraba ~0.5 ms por peticion.
	public static function parametros_redis(array $redis): array
	{
		return [
			'host' => $redis['host'],
			'port' => $redis['port'],
			'timeout' => $redis['timeout'],
			'read_write_timeout' => $redis['read_write_timeout'],
			'persistent' => (bool) ($redis['persistente'] ?? true),
		];
	}

	public function boot(): void
	{
		\App\Models\Appointment::observe(\App\Observers\ContadorCitasObserver::class);
	}
}
