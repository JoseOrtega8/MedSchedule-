<?php

namespace Tests\Unit\Logging;

use App\Logging\RedactarDatosSensibles;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

class RedactarDatosSensiblesTest extends TestCase
{
	private function registro(array $contexto): LogRecord
	{
		return new LogRecord(new DateTimeImmutable(), 'pruebas', Level::Info, 'mensaje', $contexto);
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
}
