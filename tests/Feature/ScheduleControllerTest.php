<?php

namespace Tests\Feature;

use Tests\TestCase;

class ScheduleControllerTest extends TestCase
{
    public function test_store_calculates_end_time_using_consultation_duration(): void
    {
        $response = $this->withSession([
            'doctor_profile_payload' => [
                'consultation_duration' => 45,
            ],
        ])->postJson(route('doctor.schedules.store'), [
            'date' => '2026-03-12',
            'start_time' => '14:00',
        ]);

        $response->assertOk()
            ->assertJsonPath('schedule.start_time', '14:00')
            ->assertJsonPath('schedule.end_time', '14:45')
            ->assertJsonPath('schedule.status', 'available');
    }

    public function test_blocked_schedule_cannot_be_deleted(): void
    {
        $response = $this->deleteJson(route('doctor.schedules.destroy', 2));

        $response->assertStatus(422)
            ->assertJsonPath('message', 'No puedes eliminar un horario con cita agendada.');
    }
}
