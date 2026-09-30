<?php

namespace App\Services\Auditoria;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

// Punto unico de escritura de auditoria: quien, que, sobre que, desde donde
class RegistradorAuditoria
{
	public function registrar(
		string $accion,
		?Model $modelo = null,
		array $anteriores = [],
		array $nuevos = [],
		?string $descripcion = null,
		?int $user_id = null,
	): ActivityLog {
		$peticion = app()->bound('request') ? request() : null;
		$agente = $peticion?->userAgent();

		return ActivityLog::create([
			'user_id' => $user_id ?? auth()->id(),
			'action' => $accion,
			'model_type' => $modelo?->getMorphClass(),
			'model_id' => $modelo?->getKey(),
			'description' => $descripcion,
			'ip_address' => $peticion?->ip(),
			'user_agent' => $agente === null ? null : mb_substr($agente, 0, 255),
			'old_values' => $anteriores === [] ? null : $anteriores,
			'new_values' => $nuevos === [] ? null : $nuevos,
		]);
	}
}
