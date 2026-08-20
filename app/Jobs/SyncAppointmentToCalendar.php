<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Services\GoogleCalendarService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncAppointmentToCalendar implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	/**
	 * Número de intentos antes de marcar el job como fallido.
	 */
	public int $tries = 3;

	/**
	 * Segundos de espera entre cada reintento.
	 */
	public int $backoff = 10;

	public function __construct(private int $appointmentId) {}

	/**
	 * Execute the job.
	 */
	public function handle(GoogleCalendarService $calendar): void
	{
		$appointment = Appointment::with(['doctor', 'patient', 'specialty'])
			->find($this->appointmentId);

		if (!$appointment) {
			Log::warning("SyncAppointmentToCalendar: cita {$this->appointmentId} ya no existe.");
			return;
		}

		if ($appointment->status !== 'confirmed') {
			Log::info("SyncAppointmentToCalendar: cita {$this->appointmentId} ya no está confirmada, se omite.");
			return;
		}

		$eventId = $calendar->createEvent($appointment);

		if ($eventId) {
			$appointment->update(['google_event_id' => $eventId]);
		} else {
			// createEvent() devolvió null (fallo controlado) — se relanza
			// para que el mecanismo de reintentos de la cola actúe.
			throw new \RuntimeException(
				"No se pudo crear el evento en Google Calendar para la cita {$this->appointmentId}."
			);
		}
	}

	/**
	 * Se ejecuta si el job agota todos los reintentos sin éxito.
	 */
	public function failed(\Throwable $exception): void
	{
		Log::error("SyncAppointmentToCalendar falló definitivamente para la cita {$this->appointmentId}: {$exception->getMessage()}");
	}
}
