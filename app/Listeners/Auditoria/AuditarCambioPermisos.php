<?php

namespace App\Listeners\Auditoria;

use App\Services\Auditoria\RegistradorAuditoria;
use Illuminate\Support\Collection;
use Spatie\Permission\Events\PermissionAttached;
use Spatie\Permission\Events\PermissionDetached;
use Spatie\Permission\Models\Permission;

class AuditarCambioPermisos
{
	public function __construct(private RegistradorAuditoria $registrador)
	{
	}

	public function handle(PermissionAttached|PermissionDetached $evento): void
	{
		$asignado = $evento instanceof PermissionAttached;
		$nombres = ['permisos' => $this->nombres($evento->permissionsOrIds)];

		$this->registrador->registrar(
			$asignado ? 'permiso_asignado' : 'permiso_retirado',
			$evento->model,
			$asignado ? [] : $nombres,
			$asignado ? $nombres : [],
			$asignado ? 'Se asignaron permisos' : 'Se retiraron permisos',
		);
	}

	// Spatie entrega modelos, ids o colecciones segun la llamada
	private function nombres(mixed $permisos): array
	{
		$lista = $permisos instanceof Collection ? $permisos->all() : (is_array($permisos) ? $permisos : [$permisos]);

		return array_values(array_map(
			fn ($permiso) => $permiso instanceof Permission ? $permiso->name : (Permission::find($permiso)?->name ?? (string) $permiso),
			$lista
		));
	}
}
