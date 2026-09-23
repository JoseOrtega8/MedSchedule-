<?php

namespace Tests\Feature\Auditoria;

use App\Models\ActivityLog;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VisorAuditoriaTest extends TestCase
{
	use RefreshDatabase;

	private function admin(): User
	{
		$rol = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
		$admin = User::factory()->create(['name' => 'Admin Auditor']);
		$admin->assignRole($rol);

		return $admin;
	}

	public function test_admin_ve_el_listado(): void
	{
		$admin = $this->admin();
		Specialty::create(['name' => 'Pediatria']);

		$this->actingAs($admin)->get(route('admin.auditoria'))
			->assertOk()
			->assertSee('Auditoría')
			->assertSee('creado');
	}

	public function test_no_admin_recibe_403(): void
	{
		Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

		$this->actingAs(User::factory()->create())->get(route('admin.auditoria'))->assertForbidden();
	}

	public function test_filtra_por_entidad(): void
	{
		$admin = $this->admin();
		Specialty::create(['name' => 'Pediatria']);

		$respuesta = $this->actingAs($admin)->get(route('admin.auditoria', ['entidad' => 'especialidad']));

		$respuesta->assertOk();
		$this->assertTrue($respuesta->viewData('registros')->every(fn ($r) => $r->model_type === Specialty::class));
	}

	public function test_filtro_invalido_se_rechaza(): void
	{
		$admin = $this->admin();

		$this->actingAs($admin)->get(route('admin.auditoria', ['ip' => 'no-es-ip', 'entidad' => 'tabla_inventada']))
			->assertSessionHasErrors(['ip', 'entidad']);
	}

	public function test_detalle_muestra_antes_y_despues(): void
	{
		$admin = $this->admin();
		$this->actingAs($admin);
		$especialidad = Specialty::create(['name' => 'Pediatria']);
		$especialidad->update(['name' => 'Pediatria general']);
		$registro = ActivityLog::where('action', 'actualizado')->where('model_id', $especialidad->id)->firstOrFail();

		$this->get(route('admin.auditoria.show', $registro))
			->assertOk()
			->assertSeeInOrder(['name', 'Pediatria', 'Pediatria general']);
	}

	public function test_linea_de_tiempo_de_una_entidad(): void
	{
		$admin = $this->admin();
		$this->actingAs($admin);
		$especialidad = Specialty::create(['name' => 'Pediatria']);
		$especialidad->update(['name' => 'Pediatria general']);

		$this->get(route('admin.auditoria.entidad', ['entidad' => 'especialidad', 'id' => $especialidad->id]))
			->assertOk()
			->assertSeeInOrder(['actualizado', 'creado']);
	}

	public function test_exportar_csv_escapa_formulas_y_queda_auditado(): void
	{
		$admin = $this->admin();
		$this->actingAs($admin);
		// La descripcion es una celda propia del CSV: ahi es donde una formula seria peligrosa
		ActivityLog::create(['action' => 'prueba', 'description' => '=HYPERLINK("http://malicioso")']);

		$respuesta = $this->get(route('admin.auditoria.exportar'));

		$respuesta->assertOk();
		$contenido = $respuesta->streamedContent();
		$this->assertStringContainsString("'=HYPERLINK", $contenido);
		$this->assertDoesNotMatchRegularExpression('/(^|,)"?=HYPERLINK/m', $contenido);
		$this->assertTrue(ActivityLog::where('action', 'auditoria_exportada')->where('user_id', $admin->id)->exists());
	}
}
