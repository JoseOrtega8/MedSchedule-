<?php

namespace App\Http\Controllers;

use App\Jobs\SyncAppointmentToCalendar;
use App\Models\Appointment;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GoogleCalendarController extends Controller
{
	public function __construct(private GoogleCalendarService $calendar) {}

	/**
	 * Sincronizar cita con Google Calendar (asíncrono, vía cola)
	 */
	public function sync(int $appointmentId)
	{
		$appointment = Appointment::findOrFail($appointmentId);

		// Solo el doctor dueño o admin puede sincronizar
		$user = Auth::user();
		if (!$user->hasRole('admin') && $appointment->doctor_id !== $user->id) {
			abort(403);
		}

		if ($appointment->status !== 'confirmed') {
			return response()->json([
				'message' => 'Solo se pueden sincronizar citas confirmadas.',
			], 422);
		}

		SyncAppointmentToCalendar::dispatch($appointment->id);

		return response()->json([
			'message' => 'Sincronización con Google Calendar en proceso.',
		]);
	}

	/**
	 * Eliminar evento de Google Calendar al cancelar cita
	 */
	public function unsync(int $appointmentId)
	{
		$appointment = Appointment::findOrFail($appointmentId);

		if ($appointment->google_event_id) {
			$this->calendar->deleteEvent($appointment->google_event_id);
			$appointment->update(['google_event_id' => null]);
		}

		return response()->json(['message' => 'Evento eliminado de Google Calendar.']);
	}
}
