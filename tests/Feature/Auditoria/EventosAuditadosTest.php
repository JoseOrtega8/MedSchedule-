<?php

namespace Tests\Feature\Auditoria;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventosAuditadosTest extends TestCase
{
	use RefreshDatabase;

	public function test_login_fallido_se_audita_con_correo_enmascarado(): void
	{
		User::factory()->create(['email' => 'maria.lopez@example.com']);

		$this->post(route('login'), ['email' => 'maria.lopez@example.com', 'password' => 'incorrecta']);

		$registro = ActivityLog::where('action', 'login_fallido')->firstOrFail();
		$this->assertStringContainsString('m***@example.com', $registro->description);
		$this->assertStringNotContainsString('maria.lopez', $registro->description);
	}

	public function test_logout_se_audita(): void
	{
		$usuario = User::factory()->create();

		$this->actingAs($usuario)->post(route('logout'));

		$this->assertTrue(ActivityLog::where('action', 'logout')->where('user_id', $usuario->id)->exists());
	}

	public function test_asignar_y_retirar_rol_se_audita(): void
	{
		Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'web']);
		$usuario = User::factory()->create();

		$usuario->assignRole('doctor');
		$usuario->removeRole('doctor');

		$asignado = ActivityLog::where('action', 'rol_asignado')->firstOrFail();
		$this->assertSame(['roles' => ['doctor']], $asignado->new_values);
		$this->assertTrue(ActivityLog::where('action', 'rol_retirado')->exists());
	}

	public function test_acceso_denegado_a_admin_se_audita(): void
	{
		Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
		$paciente = User::factory()->create();

		$this->actingAs($paciente)->get(route('admin.logs'))->assertForbidden();

		$registro = ActivityLog::where('action', 'acceso_denegado')->firstOrFail();
		$this->assertSame($paciente->id, $registro->user_id);
		$this->assertStringContainsString('/admin/logs', $registro->description);
	}

	// throttle va antes de auditar.denegado: pasado el limite, los intentos
	// responden 429 sin escribir mas filas selladas en la auditoria
	public function test_accesos_denegados_repetidos_se_limitan_antes_de_auditarse(): void
	{
		Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
		$paciente = User::factory()->create();
		$this->actingAs($paciente);

		for ($i = 0; $i < 60; $i++) {
			$this->get(route('admin.logs'))->assertForbidden();
		}
		$this->get(route('admin.logs'))->assertStatus(429);

		$this->assertSame(60, ActivityLog::where('action', 'acceso_denegado')->count());
	}
}
