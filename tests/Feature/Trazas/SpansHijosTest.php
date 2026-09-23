<?php

namespace Tests\Feature\Trazas;

use App\Observability\Trazas\Trazas;
use App\Models\User;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;

class SpansHijosTest extends TrazasTestCase
{
	use RefreshDatabase;

	public function test_consultas_de_la_peticion_son_spans_hijos_sin_bindings(): void
	{
		$usuario = User::factory()->create(['email' => 'paciente.secreto@example.com']);

		\Illuminate\Support\Facades\Route::middleware('web')->get('/_prueba/consulta', function () {
			DB::table('users')->where('email', 'paciente.secreto@example.com')->first();
			return 'ok';
		});

		$this->get('/_prueba/consulta')->assertOk();

		$raiz = $this->spans_que_empiezan_con('GET ')[0];
		$consultas = $this->spans_que_empiezan_con('db.query');

		$this->assertNotEmpty($consultas);
		foreach ($consultas as $consulta) {
			$this->assertSame($raiz->getTraceId(), $consulta->getTraceId());
			$this->assertStringNotContainsString('paciente.secreto', (string) $consulta->getAttributes()->get('db.statement'));
		}
		$this->assertTrue(collect($consultas)->contains(
			fn ($s) => str_contains((string) $s->getAttributes()->get('db.statement'), 'where `email` = ?')
		));
	}

	public function test_job_genera_su_propio_span(): void
	{
		config(['queue.default' => 'sync']);

		dispatch(function () {
			DB::select('select 1');
		});

		$this->assertNotEmpty($this->spans_que_empiezan_con('job '));
	}

	public function test_en_span_registra_y_propaga_excepciones(): void
	{
		$trazas = app(Trazas::class);

		try {
			$trazas->en_span('google_calendar.prueba', fn () => throw new RuntimeException('fallo externo'));
			$this->fail('Debio propagar la excepcion');
		} catch (RuntimeException $error) {
			$this->assertSame('fallo externo', $error->getMessage());
		}

		$span = $this->spans_que_empiezan_con('google_calendar.prueba')[0];
		$this->assertSame('Error', $span->getStatus()->getCode());
	}

	// Simula el camino de Worker::handleJobException: al job le quedan intentos,
	// Laravel dispara JobExceptionOccurred y lo libera de vuelta a la cola SIN
	// disparar JobProcessed ni JobFailed. El span del job debe cerrarse igual.
	public function test_reintento_de_job_cierra_su_span_via_exception_occurred(): void
	{
		$job = Mockery::mock(Job::class);
		$job->shouldReceive('getJobId')->andReturn('job-de-prueba-1');
		$job->shouldReceive('resolveName')->andReturn('App\\Jobs\\JobDePrueba');
		$job->shouldReceive('getQueue')->andReturn('default');
		$job->shouldReceive('payload')->andReturn([]);

		event(new JobProcessing('sync', $job));
		event(new JobExceptionOccurred('sync', $job, new RuntimeException('fallo con reintento pendiente')));

		$spans = $this->spans_que_empiezan_con('job ');
		$this->assertNotEmpty($spans, 'El span del job debio exportarse aunque el job se libere para reintento');
		$this->assertSame('Error', $spans[0]->getStatus()->getCode());
		$this->assertNull(app(Trazas::class)->trace_id_actual(), 'El scope del span del job debio liberarse');
	}
}
