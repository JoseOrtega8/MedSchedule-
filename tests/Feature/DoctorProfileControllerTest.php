<?php

namespace Tests\Feature;

use Tests\TestCase;

class DoctorProfileControllerTest extends TestCase
{
    public function test_update_rejects_duplicate_license_number(): void
    {
        $response = $this->patchJson(route('doctor.profile.update'), [
            'name' => 'Miguel',
            'last_name' => 'Garcia',
            'phone' => '662-987-6543',
            'specialty_id' => 'cardiologia',
            'license_number' => 'CED-998877',
            'consultation_duration' => 30,
            'bio' => 'Cardiologo con 10 anos de experiencia en el IMSS.',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['license_number']);
    }

    public function test_profile_photo_update_accepts_data_url(): void
    {
        $response = $this->postJson(route('doctor.profile.photo'), [
            'photo_data_url' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAAB',
        ]);

        $response->assertOk()
            ->assertJsonPath('doctor.photo_data_url', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAAB');
    }
}
