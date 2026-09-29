<?php

namespace Tests\Feature\Trazas;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

// Con las trazas apagadas (proveedor Noop) el folio usa el request_id de respaldo
class PaginaErrorSinTrazasTest extends TestCase
{
	public function test_error_500_sin_trazas_muestra_request_id_como_folio(): void
	{
		config(['app.debug' => false]);
		Route::middleware('web')->get('/_prueba/error-sin-trazas', fn () => throw new RuntimeException('detalle interno'));

		$respuesta = $this->get('/_prueba/error-sin-trazas');

		$respuesta->assertStatus(500);
		$this->assertMatchesRegularExpression(
			'/Folio: [0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/',
			$respuesta->getContent()
		);
		$respuesta->assertDontSee('detalle interno');
	}
}
