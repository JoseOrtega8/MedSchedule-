<?php

namespace Tests\Unit\Support;

use App\Support\MensajeSeguro;
use Illuminate\Database\QueryException;
use Illuminate\View\ViewException;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MensajeSeguroTest extends TestCase
{
	private function query_exception(string $sql = 'update patient_profiles set allergies = ? where id = ?', array $bindings = ['Penicilina-Grave', 5]): QueryException
	{
		return new QueryException('mysql', $sql, $bindings, new PDOException('dup'));
	}

	public function test_query_exception_directa_da_texto_seguro_sin_bindings(): void
	{
		$resultado = MensajeSeguro::de_excepcion($this->query_exception());

		$this->assertStringNotContainsString('Penicilina-Grave', $resultado);
		$this->assertSame('QueryException: update patient_profiles set allergies = ? where id = ?', $resultado);
	}

	// RuntimeException que reenvia el mensaje COMPLETO de Laravel (con
	// bindings interpolados) de la QueryException que encadena como causa
	public function test_excepcion_que_envuelve_query_exception_con_mensaje_completo_queda_segura(): void
	{
		$consulta = $this->query_exception();
		$externa = new RuntimeException('fallo guardando perfil: ' . $consulta->getMessage(), 0, $consulta);

		$resultado = MensajeSeguro::de_excepcion($externa);

		$this->assertStringNotContainsString('Penicilina-Grave', $resultado);
		$this->assertStringContainsString(
			'QueryException: update patient_profiles set allergies = ? where id = ?',
			$resultado
		);
	}

	// Una excepcion que copia SOLO el mensaje crudo del driver (getPrevious()
	// de la QueryException, p.ej. un "Duplicate entry" real de MySQL) sin el
	// formato completo de Laravel (sin el "(Connection: ..., SQL: ...)")
	public function test_excepcion_que_copia_solo_el_mensaje_del_driver_previo_queda_segura(): void
	{
		$pdo = new PDOException(
			"SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'Penicilina-Grave' for key 'patients_allergies_unique'"
		);
		$consulta = new QueryException(
			'mysql',
			'insert into patient_profiles (allergies) values (?)',
			['Penicilina-Grave'],
			$pdo
		);
		$externa = new RuntimeException('fallo al guardar: ' . $pdo->getMessage(), 0, $consulta);

		$resultado = MensajeSeguro::de_excepcion($externa);

		$this->assertStringNotContainsString('Penicilina-Grave', $resultado);
	}

	// Illuminate\View\ViewException REAL (no un doble): su mensaje es
	// $e->getMessage().' (View: ...)' (ver vendor
	// Illuminate\View\Engines\CompilerEngine::getMessage()/handleViewException())
	public function test_view_exception_real_queda_segura(): void
	{
		$consulta = $this->query_exception();
		$vista = new ViewException($consulta->getMessage() . ' (View: /ruta/vista.blade.php)', 0, 1, __FILE__, __LINE__, $consulta);

		$resultado = MensajeSeguro::de_excepcion($vista);

		$this->assertStringNotContainsString('Penicilina-Grave', $resultado);
		$this->assertStringContainsString('(View: /ruta/vista.blade.php)', $resultado);
		$this->assertStringContainsString('QueryException: update patient_profiles set allergies = ? where id = ?', $resultado);
	}

	// Una excepcion sin ninguna QueryException en su cadena sigue pasando,
	// como antes, solo por PATRONES
	public function test_excepcion_generica_sin_query_exception_pasa_por_patrones(): void
	{
		$resultado = MensajeSeguro::de_excepcion(new RuntimeException('fallo para ana@example.com'));

		$this->assertSame('fallo para [redactado]', $resultado);
	}

	// getSql() no deberia traer literales (es la sentencia parametrizada),
	// pero si alguno se incrusta igual (consulta sin bindings), se enmascara
	public function test_enmascara_literales_incrustados_en_getsql(): void
	{
		$consulta = new QueryException(
			'mysql',
			"update patient_profiles set allergies = 'Penicilina-Grave' where id = 5",
			[],
			new PDOException('dup')
		);

		$resultado = MensajeSeguro::de_excepcion($consulta);

		$this->assertStringNotContainsString('Penicilina-Grave', $resultado);
		$this->assertSame(
			"QueryException: update patient_profiles set allergies = '?' where id = ?",
			$resultado
		);
	}

	public function test_enmascara_lista_numerica_en_in(): void
	{
		$consulta = new QueryException(
			'mysql',
			'delete from patient_profiles where id IN (1,2,3)',
			[],
			new PDOException('dup')
		);

		$resultado = MensajeSeguro::de_excepcion($consulta);

		$this->assertStringNotContainsString('1,2,3', $resultado);
		$this->assertStringContainsString('IN (?)', $resultado);
	}

	// Entradas adversariales: espacios dentro de 'IN (...)' o un dominio muy
	// largo en un correo provocaban backtracking cuadratico (~2.7s) porque
	// \s* se solapaba con [\d,\s]+ / el patron de correo no era lineal. Con
	// cuantificadores posesivos ambas deben resolverse en menos de 200 ms.
	public function test_enmascara_literales_es_lineal_con_espacios_en_in(): void
	{
		$sql = 'delete from patient_profiles where id IN (' . str_repeat(' ', 120000) . 'x)';
		$consulta = new QueryException('mysql', $sql, [], new PDOException('dup'));

		$inicio = hrtime(true);
		MensajeSeguro::de_excepcion($consulta);
		$duracion_ms = (hrtime(true) - $inicio) / 1e6;

		$this->assertLessThan(200, $duracion_ms, "Tardo {$duracion_ms} ms, se esperaba backtracking lineal");
	}

	public function test_patron_de_correo_es_lineal_con_dominio_largo(): void
	{
		$texto = 'fallo para a@' . str_repeat('a.', 60000);

		$inicio = hrtime(true);
		MensajeSeguro::redactar_texto($texto);
		$duracion_ms = (hrtime(true) - $inicio) / 1e6;

		$this->assertLessThan(200, $duracion_ms, "Tardo {$duracion_ms} ms, se esperaba backtracking lineal");
	}
}
