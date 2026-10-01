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
				: new Predis([
					'host' => $config['redis']['host'],
					'port' => $config['redis']['port'],
					'timeout' => $config['redis']['timeout'],
					'read_write_timeout' => $config['redis']['read_write_timeout'],
				]);

			// false: sin la metrica php_info que agrega la libreria por defecto
			return new Metricas(new CollectorRegistry($almacen, false));
		});
	}

	public function boot(): void
	{
		\App\Models\Appointment::observe(\App\Observers\ContadorCitasObserver::class);
	}
}
