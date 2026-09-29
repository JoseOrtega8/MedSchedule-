<?php

namespace Tests\Unit\Logging;

use App\Logging\RedactarDatosSensibles;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use PDOException;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

class RedactarDatosSensiblesTest extends TestCase
{
	private function registro(array $contexto, string $mensaje = 'mensaje'): LogRecord
	{
		return new LogRecord(new DateTimeImmutable(), 'pruebas', Level::Info, $mensaje, $contexto);
	}

	public function test_enmascara_claves_sensibles_a_cualquier_profundidad(): void
	{
		$resultado = (new RedactarDatosSensibles())($this->registro([
			'password' => 'secreta',
			'Authorization' => 'Bearer abc',
			'paciente' => [
				'curp' => 'XXXX000000HSRXXX00',
				'allergies' => 'Penicilina',
				'nombre' => 'Ana',
			],
		]));

		$this->assertSame('[redactado]', $resultado->context['password']);
		$this->assertSame('[redactado]', $resultado->context['Authorization']);
		$this->assertSame('[redactado]', $resultado->context['paciente']['curp']);
		$this->assertSame('[redactado]', $resultado->context['paciente']['allergies']);
		$this->assertSame('Ana', $resultado->context['paciente']['nombre']);
	}

	public function test_enmascara_correo_dentro_del_mensaje(): void
	{
		$resultado = (new RedactarDatosSensibles())($this->registro([], 'reset para ana@ejemplo.com'));

		$this->assertSame('reset para [redactado]', $resultado->message);
	}

	public function test_enmascara_credencial_bearer_dentro_del_mensaje(): void
	{
		$resultado = (new RedactarDatosSensibles())($this->registro([], 'token invalido Bearer abc.def'));

		$this->assertSame('token invalido [redactado]', $resultado->message);
	}

	public function test_enmascara_curp_dentro_del_mensaje(): void
	{
		$resultado = (new RedactarDatosSensibles())($this->registro([], 'paciente CURP XXXX000000HSRXXX00 actualizado'));

		$this->assertSame('paciente CURP [redactado] actualizado', $resultado->message);
	}

	public function test_no_modifica_texto_sin_pii(): void
	{
		$resultado = (new RedactarDatosSensibles())($this->registro([], 'cita confirmada correctamente'));

		$this->assertSame('cita confirmada correctamente', $resultado->message);
	}

	public function test_enmascara_correo_en_valor_de_context_con_clave_no_sensible(): void
	{
		$resultado = (new RedactarDatosSensibles())($this->registro([
			'mensaje' => 'contactar a ana@ejemplo.com',
		]));

		$this->assertSame('contactar a [redactado]', $resultado->context['mensaje']);
	}

	public function test_enmascara_claves_que_contienen_token_por_subcadena(): void
	{
		$resultado = (new RedactarDatosSensibles())($this->registro([
			'remember_token' => 'abc123',
			'access_token' => 'def456',
			'Refresh_Token' => 'ghi789',
			'api_secret' => 'jkl000',
			'nombre' => 'Ana',
		]));

		$this->assertSame('[redactado]', $resultado->context['remember_token']);
		$this->assertSame('[redactado]', $resultado->context['access_token']);
		$this->assertSame('[redactado]', $resultado->context['Refresh_Token']);
		$this->assertSame('[redactado]', $resultado->context['api_secret']);
		$this->assertSame('Ana', $resultado->context['nombre']);
	}

	public function test_query_exception_no_expone_los_valores_de_binding(): void
	{
		$excepcion = new QueryException(
			'mysql',
			'update patient_profiles set allergies = ? where id = ?',
			['Penicilina-Grave', 77],
			new PDOException('SQLSTATE[HY000]: fallo simulado')
		);

		$resultado = (new RedactarDatosSensibles())($this->registro(['exception' => $excepcion]));

		$serializado = json_encode($resultado->context);
		$this->assertStringNotContainsString('Penicilina-Grave', $serializado);
		$this->assertSame(QueryException::class, $resultado->context['exception']['class']);
		$this->assertSame(
			'QueryException: update patient_profiles set allergies = ? where id = ?',
			$resultado->context['exception']['message']
		);
		$this->assertArrayHasKey('file', $resultado->context['exception']);
		$this->assertArrayHasKey('line', $resultado->context['exception']);
	}

	// El mensaje principal del registro (LogRecord::message) es lo que
	// Illuminate\Foundation\Exceptions\Handler::reportThrowable escribe con
	// $e->getMessage(): en una QueryException trae los bindings interpolados
	// y debe quedar sustituido por el texto seguro, no solo pasado por PATRONES
	public function test_mensaje_del_registro_igual_al_de_la_query_exception_queda_seguro(): void
	{
		$excepcion = new QueryException(
			'mysql',
			'update patient_profiles set allergies = ? where id = ?',
			['Penicilina-Grave', 5],
			new PDOException('dup')
		);

		$resultado = (new RedactarDatosSensibles())($this->registro(
			['exception' => $excepcion],
			$excepcion->getMessage()
		));

		$this->assertStringNotContainsString('Penicilina-Grave', $resultado->message);
		$this->assertSame(
			'QueryException: update patient_profiles set allergies = ? where id = ?',
			$resultado->message
		);
	}

	// Una excepcion generica que encadena una QueryException como causa
	// (getPrevious()) tambien debe quedar sustituida en el mensaje del registro
	public function test_mensaje_del_registro_con_query_exception_encadenada_como_causa(): void
	{
		$consulta = new QueryException(
			'mysql',
			'update patient_profiles set allergies = ? where id = ?',
			['Penicilina-Grave', 5],
			new PDOException('dup')
		);
		$externa = new \RuntimeException('fallo guardando perfil: ' . $consulta->getMessage(), 0, $consulta);

		$resultado = (new RedactarDatosSensibles())($this->registro(
			['exception' => $externa],
			$externa->getMessage()
		));

		$this->assertStringNotContainsString('Penicilina-Grave', $resultado->message);
	}

	public function test_enmascara_claves_nuevas_api_key_credential_phone(): void
	{
		$resultado = (new RedactarDatosSensibles())($this->registro([
			'api_key' => 'abc123',
			'credential' => 'xyz',
			'phone' => '555-0100',
			'cache_key' => 'valor-inocente',
		]));

		$this->assertSame('[redactado]', $resultado->context['api_key']);
		$this->assertSame('[redactado]', $resultado->context['credential']);
		$this->assertSame('[redactado]', $resultado->context['phone']);
		$this->assertSame('valor-inocente', $resultado->context['cache_key']);
	}
}
