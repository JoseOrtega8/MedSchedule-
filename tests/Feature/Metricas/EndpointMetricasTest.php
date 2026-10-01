<?php

namespace Tests\Feature\Metricas;

use App\Models\Appointment;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndpointMetricasTest extends TestCase
{
	use RefreshDatabase;

	public function test_sin_token_responde_404(): void
	{
		$this->get('/metrics')->assertNotFound();
	}

	public function test_token_incorrecto_responde_404(): void
	{
		$this->withToken('otro-token')->get('/metrics')->assertNotFound();
	}

	public function test_token_correcto_devuelve_metricas(): void
	{
		$respuesta = $this->withToken('token-solo-para-pruebas')->get('/metrics');

		$respuesta->assertOk();
		$this->assertStringStartsWith('text/plain', $respuesta->headers->get('Content-Type'));
		$this->assertStringContainsString('medschedule_citas_hoy', $respuesta->getContent());
		$this->assertStringContainsString('medschedule_jobs_fallidos 0', $respuesta->getContent());
	}

	public function test_cita_agendada_y_cancelada_incrementa_contadores(): void
	{
		$especialidad = Specialty::create(['name' => 'Cardiologia']);
		$paciente = User::factory()->create();
		$doctor = User::factory()->create();

		$cita = Appointment::create([
			'patient_id' => $paciente->id,
			'doctor_id' => $doctor->id,
			'specialty_id' => $especialidad->id,
			'appointment_date' => now()->toDateString(),
			'start_time' => '10:00:00',
			'end_time' => '10:30:00',
			'status' => 'pending',
		]);
		$cita->update(['status' => 'cancelled']);

		$texto = $this->withToken('token-solo-para-pruebas')->get('/metrics')->getContent();

		$this->assertStringContainsString('medschedule_citas_agendadas_total 1', $texto);
		$this->assertStringContainsString('medschedule_citas_canceladas_total 1', $texto);
		$this->assertStringContainsString('medschedule_citas_hoy 1', $texto);
	}
}
