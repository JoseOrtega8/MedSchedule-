<?php

namespace App\Logging;

use Illuminate\Log\Logger;

// Los canales 'single' y 'daily' usan los drivers homonimos de Laravel, que
// NO leen la clave 'processors' de config/logging.php (solo lo hace
// createMonologDriver, el driver del canal 'json'): storage/logs/laravel.log
// se escribia sin contexto de traza ni PII enmascarada. Este 'tap' agrega los
// mismos processors del canal 'json' sobre el Logger ya armado.
class AplicarRedaccion
{
	public function __invoke(Logger $logger): void
	{
		// pushProcessor() antepone (Monolog\Logger::pushProcessor hace
		// array_unshift): el ultimo agregado se ejecuta primero. Para
		// conservar el mismo orden que el canal 'json'
		// ([AgregarContextoTraza, RedactarDatosSensibles]) se agrega
		// RedactarDatosSensibles primero y AgregarContextoTraza al final.
		$logger->pushProcessor(new RedactarDatosSensibles());
		$logger->pushProcessor(new AgregarContextoTraza());
	}
}
