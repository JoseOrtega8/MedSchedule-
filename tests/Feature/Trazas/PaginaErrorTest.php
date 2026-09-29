<?php

namespace Tests\Feature\Trazas;

use Illuminate\Support\Facades\Route;
use RuntimeException;

class PaginaErrorTest extends TrazasTestCase
{
	public function test_error_500_muestra_folio_sin_detalle_interno(): void
	{
		config(['app.debug' => false]);
		Route::middleware('web')->get('/_prueba/error', fn () => throw new RuntimeException('detalle interno de la base'));

		$respuesta = $this->get('/_prueba/error');

		$respuesta->assertStatus(500);
		$trace_id = $respuesta->headers->get('X-Trace-Id');
		$this->assertNotNull($trace_id);
		$respuesta->assertSee("Folio: {$trace_id}");
		$respuesta->assertDontSee('detalle interno de la base');
	}
}
