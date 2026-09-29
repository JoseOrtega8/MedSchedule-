<?php

namespace Tests\Feature\Trazas;

use App\Logging\AgregarContextoTraza;
use App\Observability\Trazas\Trazas;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\View\ViewException;
use Monolog\Level;
use Monolog\LogRecord;
use PDOException;
use RuntimeException;

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

	// La excepcion que Laravel pone en context['exception'] se serializa sin
	// PII: solo clase, mensaje redactado, archivo y linea (sin trace)
	public function test_canal_json_redacta_la_excepcion_del_contexto(): void
	{
		$archivo = storage_path('logs/prueba-canal-json-'.uniqid().'.json');

		try {
			config(['logging.channels.json.handler_with.stream' => $archivo]);
			Log::forgetChannel('json');

			Log::channel('json')->error('fallo', [
				'exception' => new RuntimeException('fallo para ana@example.com con alergia a penicilina'),
			]);

			$lineas = file($archivo);
			$linea = end($lineas);
			$ultima = json_decode($linea, true);

			$this->assertStringNotContainsString('ana@example.com', $linea);
			$this->assertSame(RuntimeException::class, $ultima['context']['exception']['class']);
			$this->assertSame('fallo para [redactado] con alergia a penicilina', $ultima['context']['exception']['message']);
			$this->assertSame(__FILE__, $ultima['context']['exception']['file']);
			$this->assertIsInt($ultima['context']['exception']['line']);
			$this->assertArrayNotHasKey('trace', $ultima['context']['exception']);
		} finally {
			if (file_exists($archivo)) {
				unlink($archivo);
			}
		}
	}

	// Imita lo que hace Handler::reportThrowable: report() escribe el log con
	// $e->getMessage() como mensaje principal. En una QueryException ese
	// mensaje trae los bindings interpolados (SQL: ... allergies = Penicilina-Grave
	// ...); la linea escrita no debe contenerlos, solo la sentencia con '?'
	public function test_report_de_query_exception_no_expone_bindings_en_el_canal_json(): void
	{
		$archivo = storage_path('logs/prueba-canal-json-'.uniqid().'.json');

		try {
			config(['logging.channels.json.handler_with.stream' => $archivo]);
			config(['logging.default' => 'json']);
			Log::forgetChannel('json');

			$excepcion = new QueryException(
				'mysql',
				'update patient_profiles set allergies = ? where id = ?',
				['Penicilina-Grave', 5],
				new PDOException('dup')
			);

			report($excepcion);

			$lineas = file($archivo);
			$linea = end($lineas);

			$this->assertStringNotContainsString('Penicilina-Grave', $linea);
			$this->assertStringContainsString(
				'update patient_profiles set allergies = ? where id = ?',
				$linea
			);
		} finally {
			if (file_exists($archivo)) {
				unlink($archivo);
			}
		}
	}

	// Una QueryException ENVUELTA (ej. un catch generico que reenvia el
	// mensaje) tambien debe quedar segura: MensajeSeguro::de_excepcion debe
	// recorrer getPrevious() y no solo mirar el mensaje propio de la excepcion
	// reportada. Se verifica la LINEA COMPLETA (json_encode de todo el
	// registro), no solo el campo 'message'.
	public function test_report_de_excepcion_que_envuelve_query_exception_no_expone_bindings_en_el_canal_json(): void
	{
		$archivo = storage_path('logs/prueba-canal-json-'.uniqid().'.json');

		try {
			config(['logging.channels.json.handler_with.stream' => $archivo]);
			config(['logging.default' => 'json']);
			Log::forgetChannel('json');

			$consulta = new QueryException(
				'mysql',
				'update patient_profiles set allergies = ? where id = ?',
				['Penicilina-Grave', 5],
				new PDOException('dup')
			);
			$envoltura = new RuntimeException('fallo guardando perfil: ' . $consulta->getMessage(), 0, $consulta);

			report($envoltura);

			$lineas = file($archivo);
			$linea = end($lineas);

			$this->assertStringNotContainsString('Penicilina-Grave', $linea);
			$this->assertStringContainsString(
				'update patient_profiles set allergies = ? where id = ?',
				$linea
			);
		} finally {
			if (file_exists($archivo)) {
				unlink($archivo);
			}
		}
	}

	// Illuminate\View\ViewException REAL (una consulta que falla dentro de
	// Blade): su mensaje es $e->getMessage().' (View: ...)' (ver vendor
	// Illuminate\View\Engines\CompilerEngine::getMessage())
	public function test_report_de_view_exception_no_expone_bindings_en_el_canal_json(): void
	{
		$archivo = storage_path('logs/prueba-canal-json-'.uniqid().'.json');

		try {
			config(['logging.channels.json.handler_with.stream' => $archivo]);
			config(['logging.default' => 'json']);
			Log::forgetChannel('json');

			$consulta = new QueryException(
				'mysql',
				'update patient_profiles set allergies = ? where id = ?',
				['Penicilina-Grave', 5],
				new PDOException('dup')
			);
			$vista = new ViewException(
				$consulta->getMessage() . ' (View: /ruta/vista.blade.php)',
				0,
				1,
				__FILE__,
				__LINE__,
				$consulta
			);

			report($vista);

			$lineas = file($archivo);
			$linea = end($lineas);

			$this->assertStringNotContainsString('Penicilina-Grave', $linea);
			$this->assertStringContainsString('(View: /ruta/vista.blade.php)', $linea);
		} finally {
			if (file_exists($archivo)) {
				unlink($archivo);
			}
		}
	}

	// Los canales 'single'/'daily' (driver homonimo) no leen 'processors' de
	// config/logging.php: sin el tap AplicarRedaccion, storage/logs/laravel.log
	// quedaba sin redactar
	public function test_report_de_query_exception_no_expone_bindings_en_el_canal_single(): void
	{
		$archivo = storage_path('logs/prueba-canal-single-'.uniqid().'.log');

		try {
			config(['logging.channels.single.path' => $archivo]);
			config(['logging.default' => 'single']);
			Log::forgetChannel('single');

			$excepcion = new QueryException(
				'mysql',
				'update patient_profiles set allergies = ? where id = ?',
				['Penicilina-Grave', 5],
				new PDOException('dup')
			);

			report($excepcion);

			$contenido = file_get_contents($archivo);

			$this->assertStringNotContainsString('Penicilina-Grave', $contenido);
			$this->assertStringContainsString(
				'update patient_profiles set allergies = ? where id = ?',
				$contenido
			);
		} finally {
			if (file_exists($archivo)) {
				unlink($archivo);
			}
		}
	}
}
