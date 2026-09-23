<?php

namespace Tests\Feature\Trazas;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

class IniciarTrazaTest extends TrazasTestCase
{
	use RefreshDatabase;

	public function test_peticion_genera_span_raiz_y_cabecera_x_trace_id(): void
	{
		$respuesta = $this->get(route('login'));

		$respuesta->assertOk();
		$trace_id = $respuesta->headers->get('X-Trace-Id');
		$this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', (string) $trace_id);

		$raices = $this->spans_que_empiezan_con('GET ');
		$this->assertCount(1, $raices);
		$this->assertSame($trace_id, $raices[0]->getTraceId());
		$this->assertSame('login', $raices[0]->getAttributes()->get('http.route'));
		$this->assertSame(200, $raices[0]->getAttributes()->get('http.response.status_code'));
	}

	public function test_respeta_traceparent_entrante(): void
	{
		$padre = '4bf92f3577b34da6a3ce929d0e0e4736';

		$respuesta = $this->withHeader('traceparent', "00-{$padre}-00f067aa0ba902b7-01")->get(route('login'));

		$this->assertSame($padre, $respuesta->headers->get('X-Trace-Id'));
	}

	// La ruta real puede llevar tokens (reset-password/{token}); solo se guarda la plantilla
	public function test_ningun_span_guarda_la_ruta_real_con_tokens(): void
	{
		Route::middleware('web')->get('/_prueba/reset/{token}', fn () => 'ok');

		$this->get('/_prueba/reset/TOKEN-SECRETO-123')->assertOk();

		$spans = $this->exportador->getSpans();
		$this->assertNotEmpty($spans);
		foreach ($spans as $span) {
			$this->assertStringNotContainsString('TOKEN-SECRETO-123', $span->getName());
			foreach ($span->getAttributes()->toArray() as $valor) {
				$this->assertStringNotContainsString('TOKEN-SECRETO-123', (string) $valor);
			}
		}
		$raiz = $this->spans_que_empiezan_con('GET ')[0];
		$this->assertSame('_prueba/reset/{token}', $raiz->getAttributes()->get('url.template'));
	}
}
