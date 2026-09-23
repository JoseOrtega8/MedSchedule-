<?php

namespace Tests\Feature\Auditoria;

use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ComandosAuditoriaTest extends TestCase
{
	use RefreshDatabase;

	public function test_verificar_cadena_integra_termina_en_cero_y_guarda_resultado(): void
	{
		ActivityLog::create(['action' => 'uno']);

		$this->artisan('auditoria:verificar')->assertExitCode(0);

		$this->assertSame('integra', Cache::get('auditoria.integridad')['estado']);
	}

	public function test_verificar_cadena_rota_termina_en_uno_y_senala_el_registro(): void
	{
		$registro = ActivityLog::create(['action' => 'uno']);
		DB::table('activity_logs')->where('id', $registro->id)->update(['action' => 'alterado']);

		$this->artisan('auditoria:verificar')
			->expectsOutputToContain("Registro roto: {$registro->id}")
			->assertExitCode(1);

		$this->assertSame($registro->id, Cache::get('auditoria.integridad')['id_roto']);
	}
}
