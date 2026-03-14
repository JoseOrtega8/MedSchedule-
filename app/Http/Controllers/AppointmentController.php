<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    public function agendaData(Request $request): JsonResponse
    {
        return response()->json($this->agendaPayload($request));
    }

    public function update(Request $request, int $appointment): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['confirmed', 'cancelled'])],
        ]);

        $payload = $this->agendaPayload($request);
        $index = $this->findAgendaItemIndex($payload['agenda_items'], $appointment);

        if ($index === null) {
            return response()->json([
                'message' => 'La cita solicitada no existe.',
            ], 404);
        }

        $item = $payload['agenda_items'][$index];

        if (($item['schedule_status'] ?? null) === 'blocked') {
            return response()->json([
                'message' => 'No puedes actualizar un bloque de horario.',
            ], 422);
        }

        if (($item['status'] ?? null) !== 'pending') {
            return response()->json([
                'message' => 'Solo las citas pendientes pueden actualizarse desde esta agenda.',
            ], 422);
        }

        $newStatus = $validated['status'];
        $payload['agenda_items'][$index]['status'] = $newStatus;
        $payload['agenda_items'][$index]['appointment_history'][] = [
            'date' => now()->format('Y-m-d H:i:s'),
            'event' => $newStatus === 'confirmed'
                ? 'Cita confirmada por el doctor'
                : 'Cita cancelada por el doctor',
            'status' => $newStatus,
        ];

        $request->session()->put('doctor_agenda_payload', $payload);

        return response()->json([
            'message' => $newStatus === 'confirmed'
                ? 'La cita fue confirmada correctamente.'
                : 'La cita fue cancelada correctamente.',
            'appointment' => $payload['agenda_items'][$index],
        ]);
    }

    private function agendaPayload(Request $request): array
    {
        $payload = $request->session()->get('doctor_agenda_payload');

        if (! is_array($payload)) {
            $payload = $this->defaultAgendaPayload();
            $request->session()->put('doctor_agenda_payload', $payload);
        }

        return $payload;
    }

    private function findAgendaItemIndex(array $items, int $appointmentId): ?int
    {
        foreach ($items as $index => $item) {
            if ((int) ($item['id'] ?? 0) === $appointmentId) {
                return $index;
            }
        }

        return null;
    }

    private function defaultAgendaPayload(): array
    {
        return [
            'doctor' => [
                'name' => 'Dra. Maria Gutierrez',
                'initials' => 'MG',
                'role' => 'doctor',
            ],
            'reference_date' => '2026-03-10',
            'agenda_items' => [
                [
                    'id' => 1,
                    'date' => '2026-03-10',
                    'start_time' => '09:00:00',
                    'end_time' => '09:30:00',
                    'patient' => 'Ana Lopez',
                    'initials' => 'AL',
                    'color' => '#9a7b07',
                    'specialty' => 'Cardiologia',
                    'reason' => 'Dolor en el pecho al hacer ejercicio',
                    'status' => 'confirmed',
                    'appointment_history' => [
                        [
                            'date' => '2026-03-08 12:30:00',
                            'event' => 'Cita creada por paciente',
                            'status' => 'pending',
                        ],
                        [
                            'date' => '2026-03-09 08:15:00',
                            'event' => 'Cita confirmada por doctor',
                            'status' => 'confirmed',
                        ],
                    ],
                ],
                [
                    'id' => 2,
                    'date' => '2026-03-10',
                    'start_time' => '10:00:00',
                    'end_time' => '10:30:00',
                    'patient' => 'Carlos Ramirez',
                    'initials' => 'CR',
                    'color' => '#7c8795',
                    'specialty' => 'Cardiologia',
                    'reason' => 'Revision de rutina',
                    'status' => 'pending',
                    'appointment_history' => [
                        [
                            'date' => '2026-03-09 10:00:00',
                            'event' => 'Cita creada por paciente',
                            'status' => 'pending',
                        ],
                    ],
                ],
                [
                    'id' => 3,
                    'date' => '2026-03-10',
                    'start_time' => '11:00:00',
                    'end_time' => '11:30:00',
                    'patient' => 'Maria Perez',
                    'initials' => 'MP',
                    'color' => '#27a6be',
                    'specialty' => 'Cardiologia',
                    'reason' => 'Seguimiento post-operatorio',
                    'status' => 'completed',
                    'appointment_history' => [
                        [
                            'date' => '2026-03-07 09:10:00',
                            'event' => 'Cita creada por recepcion',
                            'status' => 'pending',
                        ],
                        [
                            'date' => '2026-03-08 11:45:00',
                            'event' => 'Cita confirmada por doctor',
                            'status' => 'confirmed',
                        ],
                        [
                            'date' => '2026-03-10 11:45:00',
                            'event' => 'Consulta completada',
                            'status' => 'completed',
                        ],
                    ],
                ],
                [
                    'id' => 4,
                    'date' => '2026-03-10',
                    'start_time' => '12:00:00',
                    'end_time' => '13:00:00',
                    'schedule_status' => 'blocked',
                    'note' => 'schedules.status = blocked',
                ],
                [
                    'id' => 5,
                    'date' => '2026-03-10',
                    'start_time' => '14:00:00',
                    'end_time' => '14:30:00',
                    'patient' => 'Luis Martinez',
                    'initials' => 'LM',
                    'color' => '#dd4799',
                    'specialty' => 'Cardiologia',
                    'reason' => 'Primera consulta - arritmia',
                    'status' => 'cancelled',
                    'appointment_history' => [
                        [
                            'date' => '2026-03-08 17:22:00',
                            'event' => 'Cita creada por paciente',
                            'status' => 'pending',
                        ],
                        [
                            'date' => '2026-03-09 19:10:00',
                            'event' => 'Cita cancelada por paciente',
                            'status' => 'cancelled',
                        ],
                    ],
                ],
                [
                    'id' => 6,
                    'date' => '2026-03-10',
                    'start_time' => '16:00:00',
                    'end_time' => '16:30:00',
                    'patient' => 'Diana Solis',
                    'initials' => 'DS',
                    'color' => '#4f7cff',
                    'specialty' => 'Cardiologia',
                    'reason' => 'Evaluacion pre-quirurgica',
                    'status' => 'pending',
                    'appointment_history' => [
                        [
                            'date' => '2026-03-09 14:40:00',
                            'event' => 'Cita creada por recepcion',
                            'status' => 'pending',
                        ],
                    ],
                ],
                [
                    'id' => 7,
                    'date' => '2026-03-11',
                    'start_time' => '08:30:00',
                    'end_time' => '09:00:00',
                    'patient' => 'Roberto Diaz',
                    'initials' => 'RD',
                    'color' => '#f08a24',
                    'specialty' => 'Cardiologia',
                    'reason' => 'Control de presion arterial',
                    'status' => 'confirmed',
                    'appointment_history' => [
                        [
                            'date' => '2026-03-10 07:50:00',
                            'event' => 'Cita confirmada por doctor',
                            'status' => 'confirmed',
                        ],
                    ],
                ],
                [
                    'id' => 8,
                    'date' => '2026-03-11',
                    'start_time' => '10:00:00',
                    'end_time' => '10:30:00',
                    'patient' => 'Elena Vega',
                    'initials' => 'EV',
                    'color' => '#7d53c8',
                    'specialty' => 'Cardiologia',
                    'reason' => 'Electrocardiograma de seguimiento',
                    'status' => 'pending',
                    'appointment_history' => [
                        [
                            'date' => '2026-03-10 15:00:00',
                            'event' => 'Cita creada por paciente',
                            'status' => 'pending',
                        ],
                    ],
                ],
                [
                    'id' => 9,
                    'date' => '2026-03-12',
                    'start_time' => '09:00:00',
                    'end_time' => '09:30:00',
                    'patient' => 'Pedro Solis',
                    'initials' => 'PS',
                    'color' => '#1380dd',
                    'specialty' => 'Cardiologia',
                    'reason' => 'Revision post-cirugia',
                    'status' => 'confirmed',
                    'appointment_history' => [
                        [
                            'date' => '2026-03-11 12:20:00',
                            'event' => 'Cita confirmada por doctor',
                            'status' => 'confirmed',
                        ],
                    ],
                ],
            ],
        ];
    }
}
