<?php

namespace App\Observability\Metricas;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;

// Punto unico de registro de metricas de la aplicacion
class Metricas
{
	private const ESPACIO = 'medschedule';

	// Cubren desde respuestas rapidas hasta el SLO de 5 s y el doble
	private const BUCKETS = [0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10];

	private const EVENTOS_CITA = ['agendadas', 'canceladas'];

	public function __construct(private CollectorRegistry $registro)
	{
	}

	// Registra una peticion HTTP atendida; $ruta es el nombre de la ruta, nunca la URL
	public function registrar_peticion(string $metodo, string $ruta, int $estado, float $segundos): void
	{
		$this->registro
			->getOrRegisterCounter(self::ESPACIO, 'http_requests_total', 'Peticiones HTTP atendidas', ['method', 'route', 'status'])
			->inc([$metodo, $ruta, (string) $estado]);

		$this->registro
			->getOrRegisterHistogram(self::ESPACIO, 'http_request_duration_seconds', 'Duracion de las peticiones HTTP', ['method', 'route'], self::BUCKETS)
			->observe($segundos, [$metodo, $ruta]);
	}

	// Cuenta una cita agendada o cancelada
	public function contar_cita(string $evento): void
	{
		if (!in_array($evento, self::EVENTOS_CITA, true)) {
			throw new InvalidArgumentException("Evento de cita no soportado: {$evento}");
		}

		$this->registro
			->getOrRegisterCounter(self::ESPACIO, "citas_{$evento}_total", "Citas {$evento}")
			->inc();
	}

	// Calcula los gauges de negocio y devuelve el texto que lee Prometheus
	public function exportar(): string
	{
		$this->registro
			->getOrRegisterGauge(self::ESPACIO, 'citas_hoy', 'Citas programadas para hoy')
			->set(DB::table('appointments')->whereDate('appointment_date', now()->toDateString())->count());

		$this->registro
			->getOrRegisterGauge(self::ESPACIO, 'jobs_fallidos', 'Jobs registrados en failed_jobs')
			->set(DB::table('failed_jobs')->count());

		return (new RenderTextFormat())->render($this->registro->getMetricFamilySamples());
	}
}
