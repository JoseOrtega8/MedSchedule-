<?php

namespace Tests\Unit\Logging;

use App\Logging\AplicarRedaccion;
use Illuminate\Log\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

// Logger PSR-3 minimo, sin pushProcessor() (no es Monolog). Simula un canal
// que un usuario podria configurar con un driver propio.
class LoggerPsrSimple extends AbstractLogger
{
	public array $mensajes = [];

	public function log($level, \Stringable|string $message, array $context = []): void
	{
		$this->mensajes[] = [$level, $message, $context];
	}
}

class AplicarRedaccionTest extends TestCase
{
	// El tap se registra sobre canales 'single'/'daily' (Monolog) sin
	// problema; el caso de riesgo es un logger PSR-3 que no sea Monolog:
	// pushProcessor() se reenvia via __call() de Illuminate\Log\Logger hacia
	// el logger subyacente y, sin el guard, lanza "Call to undefined method".
	public function test_logger_psr_sin_pushprocessor_no_lanza(): void
	{
		$logger = new Logger(new LoggerPsrSimple());

		(new AplicarRedaccion())($logger);

		// Si llegamos aqui sin excepcion, el guard funciono; ademas el
		// logger sigue operando con normalidad.
		$logger->info('mensaje de prueba');
		$this->assertCount(1, $logger->getLogger()->mensajes);
	}
}
