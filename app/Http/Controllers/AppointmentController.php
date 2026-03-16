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
		$today = Carbon::today()->format('Y-m-d');

		// Obtener citas del doctor
		$appointments = Appointment::with(['patient', 'specialty'])
			->where('doctor_id', $user->id)
			->get()
			->map(function ($a) {
				$patient = $a->patient;
				$initials = $patient
					? strtoupper(substr($patient->name, 0, 1) . substr($patient->last_name, 0, 1))
					: '??';
				$colors = ['#9a7b07', '#7c8795', '#27a6be', '#dd4799', '#4f7cff', '#f08a24', '#7d53c8'];
				$color = $colors[$a->patient_id % count($colors)];

				return [
					'id'           => $a->id,
					'date'         => $a->appointment_date,
					'start_time'   => $a->start_time,
					'end_time'     => $a->end_time,
					'patient'      => $patient ? $patient->name . ' ' . $patient->last_name : 'N/A',
					'initials'     => $initials,
					'color'        => $color,
					'specialty'    => $a->specialty ? $a->specialty->name : 'N/A',
					'reason'       => $a->reason ?? '',
					'status'       => $a->status,
					'schedule_status' => $a->schedule ? $a->schedule->status : null,
					'appointment_history' => [],
				];
			});

		// Obtener horarios bloqueados sin cita
		$schedules = Schedule::where('doctor_id', $user->id)
			->where('status', 'blocked')
			->whereNotIn('id', $appointments->pluck('id'))
			->get()
			->map(function ($s) {
				return [
					'id'             => 'schedule_' . $s->id,
					'date'           => $s->date,
					'start_time'     => $s->start_time,
					'end_time'       => $s->end_time,
					'schedule_status' => 'blocked',
					'note'           => 'schedules.status = blocked',
				];
			});

		$agendaItems = $appointments->merge($schedules)->values();

		return response()->json([
			'reference_date' => $today,
			'agenda_items'   => $agendaItems,
		]);
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
