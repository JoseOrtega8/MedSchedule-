<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScheduleController extends Controller
{
    public function indexData(Request $request): JsonResponse
    {
        $payload = $this->schedulesPayload($request);
        $doctorProfile = $this->doctorProfilePayload($request);

        return response()->json([
            'doctor' => $payload['doctor'],
            'selected_date' => $payload['selected_date'],
            'doctor_profile' => [
                'consultation_duration' => (int) ($doctorProfile['consultation_duration'] ?? 30),
            ],
            'schedules' => $payload['schedules'],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
        ]);

        $payload = $this->schedulesPayload($request);
        $doctorProfile = $this->doctorProfilePayload($request);
        $consultationDuration = (int) ($doctorProfile['consultation_duration'] ?? 30);

        $schedule = [
            'id' => $this->nextId($payload['schedules']),
            'date' => $validated['date'],
            'start_time' => $validated['start_time'],
            'end_time' => $this->calculateEndTime($validated['start_time'], $consultationDuration),
            'status' => 'available',
            'note' => null,
        ];

        if ($this->hasCollision($payload['schedules'], $schedule)) {
            return response()->json([
                'message' => 'Ya existe un horario que se cruza con ese rango.',
                'errors' => [
                    'start_time' => ['Ya existe un horario que se cruza con ese rango.'],
                ],
            ], 422);
        }

        $payload['selected_date'] = $schedule['date'];
        $payload['schedules'][] = $schedule;

        $request->session()->put('doctor_schedules_payload', $payload);

        return response()->json([
            'message' => 'Horario creado correctamente.',
            'schedule' => $schedule,
        ]);
    }

    public function update(Request $request, int $schedule): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['blocked'])],
        ]);

        $payload = $this->schedulesPayload($request);
        $index = $this->findScheduleIndex($payload['schedules'], $schedule);

        if ($index === null) {
            return response()->json([
                'message' => 'El horario solicitado no existe.',
            ], 404);
        }

        if (($payload['schedules'][$index]['status'] ?? null) === 'blocked') {
            return response()->json([
                'message' => 'Ese horario ya esta bloqueado por una cita agendada.',
            ], 422);
        }

        $payload['schedules'][$index]['status'] = $validated['status'];
        $payload['schedules'][$index]['note'] = 'Cita agendada';

        $request->session()->put('doctor_schedules_payload', $payload);

        return response()->json([
            'message' => 'Horario bloqueado correctamente.',
            'schedule' => $payload['schedules'][$index],
        ]);
    }

    public function destroy(Request $request, int $schedule): JsonResponse
    {
        $payload = $this->schedulesPayload($request);
        $index = $this->findScheduleIndex($payload['schedules'], $schedule);

        if ($index === null) {
            return response()->json([
                'message' => 'El horario solicitado no existe.',
            ], 404);
        }

        if (($payload['schedules'][$index]['status'] ?? null) === 'blocked') {
            return response()->json([
                'message' => 'No puedes eliminar un horario con cita agendada.',
            ], 422);
        }

        array_splice($payload['schedules'], $index, 1);
        $request->session()->put('doctor_schedules_payload', $payload);

        return response()->json([
            'message' => 'Horario eliminado correctamente.',
        ]);
    }

    private function schedulesPayload(Request $request): array
    {
        $payload = $request->session()->get('doctor_schedules_payload');

        if (! is_array($payload)) {
            $payload = $this->defaultSchedulesPayload();
            $request->session()->put('doctor_schedules_payload', $payload);
        }

        return $payload;
    }

    private function doctorProfilePayload(Request $request): array
    {
        $payload = $request->session()->get('doctor_profile_payload');

        if (! is_array($payload)) {
            $payload = $this->defaultDoctorProfilePayload();
            $request->session()->put('doctor_profile_payload', $payload);
        }

        return $payload;
    }

    private function defaultSchedulesPayload(): array
    {
        return [
            'doctor' => [
                'name' => 'Dra. Maria Gutierrez',
                'initials' => 'MG',
                'role' => 'doctor',
            ],
            'selected_date' => '2026-03-10',
            'schedules' => [
                [
                    'id' => 1,
                    'date' => '2026-03-10',
                    'start_time' => '09:00',
                    'end_time' => '09:30',
                    'status' => 'available',
                    'note' => null,
                ],
                [
                    'id' => 2,
                    'date' => '2026-03-10',
                    'start_time' => '10:00',
                    'end_time' => '10:30',
                    'status' => 'blocked',
                    'note' => 'Cita agendada',
                ],
                [
                    'id' => 3,
                    'date' => '2026-03-10',
                    'start_time' => '11:00',
                    'end_time' => '11:30',
                    'status' => 'available',
                    'note' => null,
                ],
                [
                    'id' => 4,
                    'date' => '2026-03-11',
                    'start_time' => '14:00',
                    'end_time' => '14:30',
                    'status' => 'available',
                    'note' => null,
                ],
                [
                    'id' => 5,
                    'date' => '2026-03-11',
                    'start_time' => '15:00',
                    'end_time' => '15:30',
                    'status' => 'blocked',
                    'note' => 'Cita agendada',
                ],
            ],
        ];
    }

    private function defaultDoctorProfilePayload(): array
    {
        // TEMPORARY MOCK SOURCE.
        // Replace this with the real doctor_profiles persistence when production data exists.
        return [
            'id' => 14,
            'name' => 'Miguel',
            'last_name' => 'Garcia',
            'email' => 'mgarcia@mail.com',
            'phone' => '662-987-6543',
            'role' => 'doctor',
            'specialty_id' => 'cardiologia',
            'license_number' => 'CED-123456',
            'consultation_duration' => 30,
            'bio' => 'Cardiologo con 10 anos de experiencia en el IMSS.',
            'photo_data_url' => null,
        ];
    }

    private function nextId(array $schedules): int
    {
        $maxId = 0;

        foreach ($schedules as $schedule) {
            $maxId = max($maxId, (int) ($schedule['id'] ?? 0));
        }

        return $maxId + 1;
    }

    private function findScheduleIndex(array $schedules, int $scheduleId): ?int
    {
        foreach ($schedules as $index => $schedule) {
            if ((int) ($schedule['id'] ?? 0) === $scheduleId) {
                return $index;
            }
        }

        return null;
    }

    private function hasCollision(array $schedules, array $candidate): bool
    {
        $start = $this->timeToMinutes($candidate['start_time'] ?? '00:00');
        $end = $this->timeToMinutes($candidate['end_time'] ?? '00:00');

        foreach ($schedules as $schedule) {
            if (($schedule['date'] ?? null) !== ($candidate['date'] ?? null)) {
                continue;
            }

            $currentStart = $this->timeToMinutes($schedule['start_time'] ?? '00:00');
            $currentEnd = $this->timeToMinutes($schedule['end_time'] ?? '00:00');

            if ($start < $currentEnd && $end > $currentStart) {
                return true;
            }
        }

        return false;
    }

    private function calculateEndTime(string $startTime, int $duration): string
    {
        return $this->minutesToTime($this->timeToMinutes($startTime) + max($duration, 1));
    }

    private function timeToMinutes(string $value): int
    {
        [$hours, $minutes] = array_pad(explode(':', $value), 2, '0');

        return (((int) $hours) * 60) + ((int) $minutes);
    }

    private function minutesToTime(int $totalMinutes): string
    {
        $hours = (int) floor($totalMinutes / 60) % 24;
        $minutes = $totalMinutes % 60;

        return sprintf('%02d:%02d', $hours, $minutes);
    }
}
