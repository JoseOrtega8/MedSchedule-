<?php

namespace Tests\Feature\Trazas;

use App\Logging\AgregarContextoTraza;
use App\Observability\Trazas\Trazas;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use Monolog\Level;
use Monolog\LogRecord;

class ContextoLogTest extends TrazasTestCase
{
	public function test_agrega_trace_id_y_span_id_del_span_activo(): void
	{
		$trazas = app(Trazas::class);
		$registro = new LogRecord(new DateTimeImmutable(), 'pruebas', Level::Info, 'mensaje');

		$resultado = $trazas->en_span('prueba.log', fn () => (new AgregarContextoTraza())($registro));

		$span = $this->spans_que_empiezan_con('prueba.log')[0];
		$this->assertSame($span->getTraceId(), $resultado->extra['trace_id']);
		$this->assertSame($span->getSpanId(), $resultado->extra['span_id']);
	}

	public function test_sin_span_activo_no_agrega_trace_id(): void
	{
		$registro = new LogRecord(new DateTimeImmutable(), 'pruebas', Level::Info, 'mensaje');

		$resultado = (new AgregarContextoTraza())($registro);

		$this->assertArrayNotHasKey('trace_id', $resultado->extra);
	}

	// Prueba de integracion: verifica que el canal 'json' real (no solo los
	// processors de forma aislada) escriba lineas redactadas y con contexto de traza
	public function test_canal_json_escribe_linea_redactada_con_contexto_de_traza(): void
	{
		$archivo = storage_path('logs/prueba-canal-json-'.uniqid().'.json');

		try {
			config(['logging.channels.json.handler_with.stream' => $archivo]);
			Log::forgetChannel('json');

			$this->app->make(Trazas::class)->en_span(
				'prueba.canal',
				fn () => Log::channel('json')->info('prueba', ['password' => 'x', 'email' => 'a@b.c'])
			);

			$lineas = file($archivo);
			$ultima = json_decode(end($lineas), true);

			$span = $this->spans_que_empiezan_con('prueba.canal')[0];
			$this->assertSame('[redactado]', $ultima['context']['password']);
			$this->assertSame('[redactado]', $ultima['context']['email']);
			$this->assertSame($span->getTraceId(), $ultima['extra']['trace_id']);
		} finally {
			if (file_exists($archivo)) {
				unlink($archivo);
			}
		}
	}
}
