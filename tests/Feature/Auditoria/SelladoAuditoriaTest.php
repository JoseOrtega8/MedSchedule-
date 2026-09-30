<?php

namespace Tests\Feature\Auditoria;

use App\Exceptions\RegistroAuditoriaInmutable;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Auditoria\SelladorAuditoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class SelladoAuditoriaTest extends TestCase
{
	use RefreshDatabase;

	private function crear_registro(string $accion): ActivityLog
	{
		return ActivityLog::create(['action' => $accion, 'description' => "Registro {$accion}"]);
	}

	public function test_registro_nuevo_queda_sellado_y_encadenado(): void
	{
		$primero = $this->crear_registro('uno');
		$segundo = $this->crear_registro('dos');

		$this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $primero->hash);
		$this->assertNull($primero->hash_anterior);
		$this->assertSame($primero->hash, $segundo->hash_anterior);
	}

	public function test_no_se_puede_modificar(): void
	{
		$registro = $this->crear_registro('uno');

		$this->expectException(RegistroAuditoriaInmutable::class);
		$registro->update(['description' => 'alterado']);
	}

	public function test_no_se_puede_eliminar(): void
	{
		$registro = $this->crear_registro('uno');

		$this->expectException(RegistroAuditoriaInmutable::class);
		$registro->delete();
	}

	public function test_cadena_intacta_es_integra(): void
	{
		$this->crear_registro('uno');
		$this->crear_registro('dos');

		$resultado = app(SelladorAuditoria::class)->verificar();

		$this->assertSame('integra', $resultado['estado']);
		$this->assertSame(2, $resultado['revisados']);
	}

	public function test_detecta_alteracion_manual_en_la_base(): void
	{
		$this->crear_registro('uno');
		$alterado = $this->crear_registro('dos');
		$this->crear_registro('tres');

		DB::table('activity_logs')->where('id', $alterado->id)->update(['description' => 'alterado a mano']);

		$resultado = app(SelladorAuditoria::class)->verificar();

		$this->assertSame('rota', $resultado['estado']);
		$this->assertSame($alterado->id, $resultado['id_roto']);
	}

	public function test_detecta_eliminacion_intermedia(): void
	{
		$this->crear_registro('uno');
		$borrado = $this->crear_registro('dos');
		$siguiente = $this->crear_registro('tres');

		DB::table('activity_logs')->where('id', $borrado->id)->delete();

		$resultado = app(SelladorAuditoria::class)->verificar();

		$this->assertSame('rota', $resultado['estado']);
		$this->assertSame($siguiente->id, $resultado['id_roto']);
	}

	public function test_fila_sin_sello_rompe_la_cadena(): void
	{
		$this->crear_registro('uno');
		$sin_sello_id = DB::table('activity_logs')->insertGetId([
			'action' => 'sin_sello',
			'created_at' => now(),
			'updated_at' => now(),
		]);

		$resultado = app(SelladorAuditoria::class)->verificar();

		$this->assertSame('rota', $resultado['estado']);
		$this->assertSame($sin_sello_id, $resultado['id_roto']);
	}

	public function test_anular_todos_los_hashes_rompe_la_cadena(): void
	{
		$primero = $this->crear_registro('uno');
		$this->crear_registro('dos');
		$this->crear_registro('tres');

		DB::table('activity_logs')->update(['hash' => null]);

		$resultado = app(SelladorAuditoria::class)->verificar();

		$this->assertSame('rota', $resultado['estado']);
		$this->assertSame($primero->id, $resultado['id_roto']);
	}

	public function test_borrar_usuario_conserva_la_auditoria_y_la_cadena_integra(): void
	{
		$usuario = User::factory()->create();
		$registro = ActivityLog::create([
			'action' => 'uno',
			'description' => 'Registro uno',
			'user_id' => $usuario->id,
		]);
		$usuario_id = $usuario->id;

		$usuario->delete();

		// Sin la FK con ON DELETE SET NULL, la fila conserva el user_id
		// aunque el usuario ya no exista.
		$this->assertSame($usuario_id, $registro->fresh()->user_id);

		$resultado = app(SelladorAuditoria::class)->verificar();
		$this->assertSame('integra', $resultado['estado']);
	}

	// La migracion 2026_09_23_000000 sella, en su propio up(), las filas que
	// ya existian antes de agregar las columnas. RefreshDatabase migra sobre
	// una base vacia, asi que no hay forma de ver ese paso actuar sobre filas
	// reales dentro de una prueba normal: se invoca el metodo privado que usa
	// el propio up() (via reflexion), simulando filas insertadas antes de la
	// migracion, para probar la logica de sellado que realmente ejecuta.
	public function test_migracion_sella_las_filas_preexistentes(): void
	{
		DB::table('activity_logs')->insert([
			['action' => 'uno', 'description' => 'uno', 'created_at' => now(), 'updated_at' => now()],
			['action' => 'dos', 'description' => 'dos', 'created_at' => now(), 'updated_at' => now()],
		]);

		$migracion = require database_path('migrations/2026_09_23_000000_add_integridad_to_activity_logs_table.php');
		$metodo = new ReflectionMethod($migracion, 'sellar_filas_existentes');
		$metodo->setAccessible(true);
		$metodo->invoke($migracion);

		$resultado = app(SelladorAuditoria::class)->verificar();

		$this->assertSame('integra', $resultado['estado']);
		$this->assertSame(2, $resultado['revisados']);
	}
}
