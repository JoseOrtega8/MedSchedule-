<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpecialtyControllerTest extends TestCase
{
    public function test_create_rejects_duplicate_specialty_name(): void
    {
        $response = $this->postJson(route('admin.specialties.store'), [
            'name' => 'Cardiologia',
            'description' => 'Duplicada',
            'status' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_delete_rejects_specialty_with_assigned_doctors(): void
    {
        $response = $this->deleteJson(route('admin.specialties.destroy', 1));

        $response->assertStatus(422)
            ->assertJsonPath('message', 'No puedes eliminar una especialidad con doctores asignados.');
    }
}
