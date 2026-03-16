<?php

namespace Tests\Feature;

use Tests\TestCase;

class AppointmentControllerTest extends TestCase
{
    public function test_agenda_data_returns_doctor_and_items(): void
    {
        $response = $this->getJson(route('doctor.agenda.data'));

        $response->assertOk()
            ->assertJsonStructure([
                'doctor' => ['name', 'initials', 'role'],
                'reference_date',
                'agenda_items' => [
                    '*' => ['id', 'date', 'start_time', 'end_time'],
                ],
            ]);
    }

    public function test_pending_appointment_can_be_confirmed(): void
    {
        $response = $this->patchJson(route('appointments.update', 2), [
            'status' => 'confirmed',
        ]);

        $response->assertOk()
            ->assertJsonPath('appointment.id', 2)
            ->assertJsonPath('appointment.status', 'confirmed');
    }
}
