<?php

namespace App\Console\Commands;

use App\Services\Auditoria\SelladorAuditoria;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class VerificarAuditoria extends Command
{
	protected $signature = 'auditoria:verificar';

	protected $description = 'Verifica la cadena de sellos de la auditoria y reporta el primer registro alterado';

	public function handle(SelladorAuditoria $sellador): int
	{
		$resultado = $sellador->verificar();

		// El visor muestra el ultimo resultado sin recorrer la tabla en cada visita
		Cache::forever('auditoria.integridad', $resultado + ['verificado_en' => now()->toIso8601String()]);

		$this->info("Registros revisados: {$resultado['revisados']}");

		if ($resultado['estado'] === 'rota') {
			$this->error("Cadena rota. Registro roto: {$resultado['id_roto']} ({$resultado['fecha_rota']})");

			return self::FAILURE;
		}

		$this->info('Cadena íntegra.');

		return self::SUCCESS;
	}
}
