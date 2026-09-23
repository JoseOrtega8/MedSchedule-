<?php

namespace Tests\Feature\Auditoria;

use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\PatientProfile;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditableTest extends TestCase
{
	use RefreshDatabase;

	private function ultimo(string $clase, int $id): ActivityLog
	{
		return ActivityLog::query()
			->where('model_type', $clase)->where('model_id', $id)
			->orderByDesc('id')->firstOrFail();
	}

	public function test_crear_actualizar_y_eliminar_generan_diff(): void
	{
		$admin = User::factory()->create();
		$this->actingAs($admin);

		$especialidad = Specialty::create(['name' => 'Cardiologia', 'description' => 'Corazon']);
		$creado = $this->ultimo(Specialty::class, $especialidad->id);
		$this->assertSame('creado', $creado->action);
		$this->assertSame('Cardiologia', $creado->new_values['name']);
		$this->assertSame($admin->id, $creado->user_id);

		$especialidad->update(['name' => 'Cardiologia clinica']);
		$actualizado = $this->ultimo(Specialty::class, $especialidad->id);
		$this->assertSame('actualizado', $actualizado->action);
		$this->assertSame(['name' => 'Cardiologia'], $actualizado->old_values);
		$this->assertSame(['name' => 'Cardiologia clinica'], $actualizado->new_values);

		$id = $especialidad->id;
		$especialidad->delete();
		$eliminado = $this->ultimo(Specialty::class, $id);
		$this->assertSame('eliminado', $eliminado->action);
		$this->assertSame('Cardiologia clinica', $eliminado->old_values['name']);
	}

	public function test_password_se_registra_como_protegido(): void
	{
		$usuario = User::factory()->create();
		$usuario->update(['password' => bcrypt('nueva-clave-segura')]);

		$registro = $this->ultimo(User::class, $usuario->id);

		$this->assertSame('actualizado', $registro->action);
		$this->assertSame('[protegido]', $registro->new_values['password']);
		$this->assertSame('[protegido]', $registro->old_values['password']);
	}

	public function test_campos_clinicos_se_registran_como_protegidos(): void
	{
		$paciente = User::factory()->create();
		$perfil = PatientProfile::create(['user_id' => $paciente->id, 'allergies' => 'Penicilina', 'blood_type' => 'O+']);

		$perfil->update(['allergies' => 'Penicilina y latex']);

		$registro = $this->ultimo(PatientProfile::class, $perfil->id);
		$this->assertSame('[protegido]', $registro->new_values['allergies']);
		$this->assertStringNotContainsString('latex', json_encode($registro->new_values));
		$this->assertStringNotContainsString('Penicilina', json_encode(ActivityLog::all()->toArray()));
	}

	public function test_cambio_solo_de_campos_excluidos_no_registra(): void
	{
		$usuario = User::factory()->create();
		$antes = ActivityLog::count();

		$usuario->update(['remember_token' => 'otro-token-cualquiera']);

		$this->assertSame($antes, ActivityLog::count());
	}

	public function test_motivo_y_observaciones_de_la_cita_se_registran_como_protegidos(): void
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
			'reason' => 'dolor de pecho',
		]);
		$creado = $this->ultimo(Appointment::class, $cita->id);
		$this->assertSame('[protegido]', $creado->new_values['reason']);

		// observaciones no es asignable en masa: se llena como lo haria el doctor
		$cita->forceFill(['reason' => 'dolor de pecho intenso', 'observaciones' => 'dolor de pecho con disnea'])->save();
		$actualizado = $this->ultimo(Appointment::class, $cita->id);
		$this->assertSame('[protegido]', $actualizado->old_values['reason']);
		$this->assertSame('[protegido]', $actualizado->new_values['reason']);
		$this->assertSame('[protegido]', $actualizado->new_values['observaciones']);

		$this->assertStringNotContainsString('dolor de pecho', json_encode(ActivityLog::all()->toArray()));
	}
}
