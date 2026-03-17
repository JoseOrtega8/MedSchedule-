<?php

namespace App\Services;

use App\Models\Appointment;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Illuminate\Support\Facades\Log;

class GoogleCalendarService
{
	private ?Client $client = null;

	public function __construct()
	{
		try {
			$this->client = new Client();
			$this->client->setApplicationName('MedSchedule');
			$this->client->setScopes([Calendar::CALENDAR]);
			$this->client->setAuthConfig([
				'type'          => 'service_account',
				'project_id'    => config('services.google.project_id'),
				'private_key_id' => config('services.google.private_key_id'),
				'private_key'   => config('services.google.private_key'),
				'client_email'  => config('services.google.client_email'),
				'client_id'     => config('services.google.client_id'),
				'auth_uri'      => 'https://accounts.google.com/o/oauth2/auth',
				'token_uri'     => 'https://oauth2.googleapis.com/token',
			]);
			$this->client->setSubject(config('services.google.calendar_email'));
		} catch (\Exception $e) {
			Log::error('GoogleCalendarService init error: ' . $e->getMessage());
			$this->client = null;
		}
	}

	public function createEvent(Appointment $appointment): ?string
	{
		if (!$this->client) {
			return null;
		}

		try {
			$service   = new Calendar($this->client);
			$doctor    = $appointment->doctor;
			$patient   = $appointment->patient;
			$specialty = $appointment->specialty;

			$event = new Event([
				'summary'     => 'Cita Médica — ' . ($specialty ? $specialty->name : 'MedSchedule'),
				'description' => 'Paciente: ' . ($patient ? $patient->name . ' ' . $patient->last_name : 'N/A') . "\n"
					. 'Doctor: '   . ($doctor  ? 'Dr. ' . $doctor->last_name : 'N/A') . "\n"
					. 'Motivo: '   . ($appointment->reason ?? 'N/A'),
				'start' => new EventDateTime([
					'dateTime' => $appointment->appointment_date . 'T' . $appointment->start_time,
					'timeZone' => config('app.timezone', 'America/Hermosillo'),
				]),
				'end' => new EventDateTime([
					'dateTime' => $appointment->appointment_date . 'T' . $appointment->end_time,
					'timeZone' => config('app.timezone', 'America/Hermosillo'),
				]),
			]);

			$calendarId   = config('services.google.calendar_id', 'primary');
			$createdEvent = $service->events->insert($calendarId, $event, [
				'sendUpdates' => 'none',
			]);

			return $createdEvent->getId();
		} catch (\Exception $e) {
			Log::error('Google Calendar createEvent error: ' . $e->getMessage());
			return null;
		}
	}

	public function deleteEvent(string $eventId): void
	{
		if (!$this->client) return;

		try {
			$service    = new Calendar($this->client);
			$calendarId = config('services.google.calendar_id', 'primary');
			$service->events->delete($calendarId, $eventId);
		} catch (\Exception $e) {
			Log::error('Google Calendar deleteEvent error: ' . $e->getMessage());
		}
	}
	public function listEvents($startDate, $endDate): array
	{
		if (!$this->client) return [];

		try {
			$service = new Calendar($this->client);
			$calendarId = config('services.google.calendar_id', 'primary');

			$events = $service->events->listEvents($calendarId, [
				'timeMin' => $startDate instanceof \DateTimeInterface ? $startDate->format(\DateTime::ATOM) : $startDate,
				'timeMax' => $endDate instanceof \DateTimeInterface ? $endDate->format(\DateTime::ATOM) : $endDate,
				'singleEvents' => true,
				'orderBy' => 'startTime',
			]);

			return $events->getItems() ?? [];
		} catch (\Exception $e) {
			\Log::error('Google Calendar listEvents error: ' . $e->getMessage());
			return [];
		}
	}
}
