<?php

namespace Tests\Feature\Auditoria;

use App\Exceptions\RegistroAuditoriaInmutable;
use App\Models\ActivityLog;
use App\Services\Auditoria\SelladorAuditoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

	public function test_registros_historicos_sin_sello_no_rompen_la_cadena(): void
	{
		DB::table('activity_logs')->insert(['action' => 'historico', 'created_at' => now(), 'updated_at' => now()]);
		$this->crear_registro('nuevo');

		$resultado = app(SelladorAuditoria::class)->verificar();

		$this->assertSame('integra', $resultado['estado']);
		$this->assertSame(1, $resultado['sin_sellar_historicos']);
	}
}
