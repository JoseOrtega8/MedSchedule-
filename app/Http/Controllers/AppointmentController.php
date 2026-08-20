<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class AppointmentController extends Controller
{
	public function agendaData(Request $request): JsonResponse
	{
		$user = Auth::user();
		$today = now()->format('Y-m-d');

		$appointments = Appointment::with(['patient', 'specialty', 'schedule'])
			->where('doctor_id', $user->id)
			->get()
			->map(fn (Appointment $appointment) => $this->mapAppointmentForAgenda($appointment));

		$schedules = Schedule::where('doctor_id', $user->id)
			->where('status', 'blocked')
			->get()
			->map(fn (Schedule $schedule) => $this->mapBlockedScheduleForAgenda($schedule));

		$gCalService = new \App\Services\GoogleCalendarService();
		$gcalEvents = $gCalService->listEvents(
			now()->startOfDay(),
			now()->endOfDay()
		);

		$gcalMapped = collect($gcalEvents)->map(fn (object $event) => $this->mapGoogleCalendarEventForAgenda($event));
		$agendaItems = $appointments->merge($schedules)->merge($gcalMapped)->values();

		return response()->json([
			'reference_date' => $today,
			'agenda_items' => $agendaItems,
		]);
	}

	private function mapAppointmentForAgenda(Appointment $appointment): array
	{
		$patient = $appointment->patient;
		$patientName = $patient ? trim((string) $patient->name) : '';
		$patientLastName = $patient ? trim((string) $patient->last_name) : '';
		$initials = $patient
			? strtoupper(substr($patientName, 0, 1) . substr($patientLastName, 0, 1))
			: '??';
		$colors = ['#9a7b07', '#7c8795', '#27a6be', '#dd4799', '#4f7cff', '#f08a24', '#7d53c8'];

		return [
			'id' => $appointment->id,
			'date' => $appointment->appointment_date,
			'start_time' => $appointment->start_time,
			'end_time' => $appointment->end_time,
			'patient' => $patient ? trim($patientName . ' ' . $patientLastName) : 'N/A',
			'initials' => $initials,
			'color' => $colors[$appointment->patient_id % count($colors)],
			'specialty' => $appointment->specialty ? $appointment->specialty->name : 'N/A',
			'reason' => $appointment->reason ?? '',
			'status' => $appointment->status,
			'schedule_status' => $appointment->schedule ? $appointment->schedule->status : null,
			'appointment_history' => [],
			'source' => 'local',
		];
	}

	private function mapBlockedScheduleForAgenda(Schedule $schedule): array
	{
		return [
			'id' => 'schedule_' . $schedule->id,
			'date' => $schedule->date,
			'start_time' => $schedule->start_time,
			'end_time' => $schedule->end_time,
			'schedule_status' => 'blocked',
			'note' => 'Horario bloqueado',
			'source' => 'local',
		];
	}

	private function mapGoogleCalendarEventForAgenda(object $event): array
	{
		$start = $event->start->dateTime ?? $event->start->date;
		$end = $event->end->dateTime ?? $event->end->date;

		return [
			'id' => 'gcal_' . $event->id,
			'date' => substr((string) $start, 0, 10),
			'start_time' => substr((string) $start, 11, 5),
			'end_time' => substr((string) $end, 11, 5),
			'patient' => $event->summary ?? 'Cita Google',
			'initials' => 'G',
			'color' => '#4285F4',
			'specialty' => null,
			'reason' => $event->description ?? '',
			'status' => 'confirmed',
			'schedule_status' => null,
			'appointment_history' => [],
			'source' => 'google',
		];
	}

public function store(Request $request): JsonResponse
{
    $validated = $request->validate([
        'doctor_id'    => 'required|exists:users,id',
        'specialty_id' => 'required|exists:specialties,id',
        'date'         => 'required|date',
        'time'         => 'required',
    ]);

    $endTime = Carbon::parse($validated['time'])->addMinutes(30)->format('H:i');

    $appointment = Appointment::create([
        'patient_id'      => Auth::id(),
        'doctor_id'       => $validated['doctor_id'],
        'specialty_id'    => $validated['specialty_id'],
        'appointment_date'=> $validated['date'],
        'start_time'      => $validated['time'],
        'end_time'        => $endTime,
        'status'          => 'pending',
        'reason'          => $request->reason ?? 'Consulta',
    ]);

    // Sincronizar con Google Calendar (opcional)
    $gCalService = new \App\Services\GoogleCalendarService();
    $gCalService->createEvent($appointment);

    return response()->json(['success' => true, 'appointment' => $appointment]);
}


	public function cancel(int $id): JsonResponse
	{
		$appointment = Appointment::where('patient_id', Auth::id())->findOrFail($id);

		if (!in_array($appointment->status, ['pending','confirmed'])) {
			return response()->json(['message'=>'No se puede cancelar esta cita'],422);
		}

		$appointment->status = 'cancelled';
		if ($appointment->schedule_id) {
			$appointment->schedule->update(['status'=>'available']);
		}
		$appointment->save();

		return response()->json(['success'=>true,'message'=>'Cita cancelada']);
	}

	public function history(): JsonResponse
	{
		$appointments = Appointment::where('patient_id', Auth::id())
			->whereDate('appointment_date','<',now())
			->get()
			->map(fn($a)=>[
				'appointment_date'=>$a->appointment_date->format('Y-m-d'),
				'doctor_name' => $a->doctor?->name . ' ' . $a->doctor?->last_name,
				'observaciones'=>$a->observaciones,
			]);

		return response()->json($appointments);
	}

	public function availability(int $doctorId): JsonResponse
	{
		$slots = Schedule::where('doctor_id',$doctorId)
			->where('status','available')
			->get()
			->map(fn($s)=>[
				'date'=>$s->date,
				'time'=>$s->start_time,
			]);

		return response()->json(['slots'=>$slots]);
	}


	public function update(Request $request, int $appointment): JsonResponse
	{
		$validated = $request->validate([
			'status' => ['required', 'string', Rule::in(['confirmed', 'cancelled'])],
		]);

		$apt = Appointment::where('doctor_id', Auth::user()->id)
			->findOrFail($appointment);

		if ($apt->status !== 'pending') {
			return response()->json([
				'message' => 'Solo las citas pendientes pueden actualizarse.',
			], 422);
		}

		$apt->status = $validated['status'];

		// Si se cancela revertir schedule a available
		if ($validated['status'] === 'cancelled' && $apt->schedule_id) {
			$apt->schedule->update(['status' => 'available']);
		}

		$apt->save();

		$colors = ['#9a7b07', '#7c8795', '#27a6be', '#dd4799', '#4f7cff', '#f08a24', '#7d53c8'];
		$patient = $apt->patient;
		$initials = $patient
			? strtoupper(substr($patient->name, 0, 1) . substr($patient->last_name, 0, 1))
			: '??';

		return response()->json([
			'message' => $validated['status'] === 'confirmed'
				? 'La cita fue confirmada correctamente.'
				: 'La cita fue cancelada correctamente.',
			'appointment' => [
				'id'              => $apt->id,
				'date'            => $apt->appointment_date,
				'start_time'      => $apt->start_time,
				'end_time'        => $apt->end_time,
				'patient'         => $patient ? $patient->name . ' ' . $patient->last_name : 'N/A',
				'initials'        => $initials,
				'color'           => $colors[$apt->patient_id % count($colors)],
				'specialty'       => $apt->specialty ? $apt->specialty->name : 'N/A',
				'reason'          => $apt->reason ?? '',
				'status'          => $apt->status,
				'schedule_status' => $apt->schedule ? $apt->schedule->status : null,
				'appointment_history' => [],
			],
		]);
	}
}
