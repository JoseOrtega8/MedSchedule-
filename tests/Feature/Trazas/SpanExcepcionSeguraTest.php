<?php

namespace Tests\Feature\Trazas;

use App\Observability\Trazas\Trazas;
use Illuminate\Database\QueryException;
use PDOException;
use RuntimeException;

// Trazas::en_span ya no usa recordException(): una QueryException no debe
// exponer los bindings de la consulta en los spans exportados a Tempo
class SpanExcepcionSeguraTest extends TrazasTestCase
{
	public function test_query_exception_en_span_no_expone_bindings_y_registra_evento_exception(): void
	{
		$trazas = app(Trazas::class);

		try {
			$trazas->en_span('prueba.query', function () {
				throw new QueryException(
					'mysql',
					'update patient_profiles set allergies = ? where id = ?',
					['Penicilina-Grave', 5],
					new PDOException('dup')
				);
			});
			$this->fail('Se esperaba que la QueryException se propagara');
		} catch (QueryException $e) {
			// Se espera: en_span relanza la excepcion tras registrar el span
		}

		$spans = $this->spans_que_empiezan_con('prueba.query');
		$this->assertCount(1, $spans);
		$span = $spans[0];

		foreach ($span->getAttributes()->toArray() as $valor) {
			$this->assertStringNotContainsString('Penicilina-Grave', (string) $valor);
		}

		$eventos = $span->getEvents();
		$this->assertNotEmpty($eventos);

		$evento_excepcion = null;
		foreach ($eventos as $evento) {
			$this->assertStringNotContainsString('Penicilina-Grave', json_encode($evento->getAttributes()->toArray()));
			if ($evento->getName() === 'exception') {
				$evento_excepcion = $evento;
			}
		}

		$this->assertNotNull($evento_excepcion, 'Se esperaba un evento "exception" en el span');
		$this->assertSame(QueryException::class, $evento_excepcion->getAttributes()->get('exception.type'));
		$this->assertStringContainsString(
			'update patient_profiles set allergies = ? where id = ?',
			(string) $evento_excepcion->getAttributes()->get('exception.message')
		);
	}

	// Una excepcion cualquiera tambien debe seguir generando el evento
	// 'exception' (no se rompe el comportamiento existente para otros errores)
	public function test_excepcion_generica_en_span_registra_evento_exception(): void
	{
		$trazas = app(Trazas::class);

		try {
			$trazas->en_span('prueba.generica', function () {
				throw new RuntimeException('fallo generico');
			});
			$this->fail('Se esperaba que la excepcion se propagara');
		} catch (RuntimeException $e) {
			// Se espera
		}

		$span = $this->spans_que_empiezan_con('prueba.generica')[0];
		$eventos = $span->getEvents();
		$this->assertCount(1, $eventos);
		$this->assertSame('exception', $eventos[0]->getName());
		$this->assertSame(RuntimeException::class, $eventos[0]->getAttributes()->get('exception.type'));
		$this->assertSame('fallo generico', $eventos[0]->getAttributes()->get('exception.message'));
	}
}
