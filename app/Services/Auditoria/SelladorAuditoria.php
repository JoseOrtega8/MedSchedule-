<?php

namespace App\Services\Auditoria;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;
use RuntimeException;

// Sella cada registro de auditoria con un HMAC encadenado al registro anterior.
// Quien altere o borre una fila sin conocer la llave rompe la cadena.
class SelladorAuditoria
{
	private static bool $aviso_llave_emitido = false;

	// Se llama dentro de la transaccion de insercion (ver ActivityLog::save)
	public function sellar(ActivityLog $log): void
	{
		$log->created_at ??= now();
		$log->trace_id ??= $this->trace_id_actual();

		$ultimo = ActivityLog::query()->orderByDesc('id')->lockForUpdate()->first(['id', 'hash']);

		$log->hash_anterior = $ultimo?->hash;
		$log->hash = $this->calcular($log->hash_anterior, $log);
	}

	public function calcular(?string $hash_anterior, ActivityLog $log): string
	{
		return hash_hmac('sha256', ($hash_anterior ?? '') . '|' . $this->canonico($log), $this->llave());
	}

	// Recorre la cadena completa y devuelve el primer eslabon roto
	public function verificar(): array
	{
		$anterior = null;
		$sellado_visto = false;
		$revisados = 0;
		$historicos = 0;

		foreach (ActivityLog::query()->orderBy('id')->lazyById(500) as $log) {
			$revisados++;

			// Filas anteriores a la adopcion del sello: quedan fuera de la cadena
			if ($log->hash === null && !$sellado_visto) {
				$historicos++;
				continue;
			}
			$sellado_visto = true;

			$intacto = $log->hash !== null
				&& $log->hash_anterior === $anterior
				&& hash_equals($this->calcular($anterior, $log), $log->hash);

			if (!$intacto) {
				return [
					'estado' => 'rota',
					'id_roto' => $log->id,
					'fecha_rota' => $log->created_at?->toIso8601String(),
					'revisados' => $revisados,
					'sin_sellar_historicos' => $historicos,
				];
			}

			$anterior = $log->hash;
		}

		return [
			'estado' => 'integra',
			'id_roto' => null,
			'fecha_rota' => null,
			'revisados' => $revisados,
			'sin_sellar_historicos' => $historicos,
		];
	}

	// Representacion estable de la fila: claves ordenadas, fechas ISO 8601 UTC
	private function canonico(ActivityLog $log): string
	{
		$datos = [
			'action' => $log->action,
			'created_at' => $log->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
			'description' => $log->description,
			'ip_address' => $log->ip_address,
			'model_id' => $log->model_id === null ? null : (int) $log->model_id,
			'model_type' => $log->model_type,
			'new_values' => $this->ordenar($log->new_values),
			'old_values' => $this->ordenar($log->old_values),
			'trace_id' => $log->trace_id,
			'user_agent' => $log->user_agent,
			'user_id' => $log->user_id === null ? null : (int) $log->user_id,
		];

		return json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
	}

	// MySQL reordena las claves de las columnas JSON; se ordenan antes de sellar
	private function ordenar(mixed $valor): mixed
	{
		if (!is_array($valor)) {
			return $valor;
		}
		ksort($valor);

		return array_map(fn ($elemento) => $this->ordenar($elemento), $valor);
	}

	private function trace_id_actual(): ?string
	{
		return app()->bound('request') ? request()->attributes->get('trace_id') : null;
	}

	private function llave(): string
	{
		$llave = (string) config('auditoria.llave_hmac');
		if ($llave !== '') {
			return $llave;
		}

		if (app()->environment('production')) {
			throw new RuntimeException('Falta AUDIT_HMAC_KEY: la auditoria no puede sellarse en produccion.');
		}

		// Fuera de produccion se deriva de APP_KEY para no romper los entornos del equipo
		if (!self::$aviso_llave_emitido) {
			self::$aviso_llave_emitido = true;
			Log::warning('AUDIT_HMAC_KEY no configurada; se deriva de APP_KEY (solo desarrollo).');
		}

		return hash_hmac('sha256', 'auditoria', (string) config('app.key'));
	}
}
