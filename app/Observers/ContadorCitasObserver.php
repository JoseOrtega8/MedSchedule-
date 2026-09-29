<?php

namespace App\Observers;

use App\Models\Appointment;
use App\Observability\Metricas\Metricas;
use App\Support\MensajeSeguro;
use Illuminate\Support\Facades\Log;
use Throwable;

// Alimenta los contadores de negocio de citas
class ContadorCitasObserver
{
	public function __construct(private Metricas $metricas)
	{
	}

	public function created(Appointment $cita): void
	{
		$this->contar('agendadas');
	}

	public function updated(Appointment $cita): void
	{
		if ($cita->wasChanged('status') && $cita->status === 'cancelled') {
			$this->contar('canceladas');
		}
	}

	private function contar(string $evento): void
	{
		try {
			$this->metricas->contar_cita($evento);
		} catch (Throwable $error) {
			// Un fallo de metricas no debe impedir agendar o cancelar
			Log::warning('No se pudo contar la cita', ['evento' => $evento, 'error' => MensajeSeguro::de_excepcion($error)]);
		}
	}
}
