<?php

namespace App\Listeners\Auditoria;

use App\Services\Auditoria\RegistradorAuditoria;
use Illuminate\Support\Collection;
use Spatie\Permission\Events\RoleAttached;
use Spatie\Permission\Events\RoleDetached;
use Spatie\Permission\Models\Role;

class AuditarCambioRoles
{
	public function __construct(private RegistradorAuditoria $registrador)
	{
	}

	public function handle(RoleAttached|RoleDetached $evento): void
	{
		$asignado = $evento instanceof RoleAttached;
		$nombres = ['roles' => $this->nombres($evento->rolesOrIds)];

		$this->registrador->registrar(
			$asignado ? 'rol_asignado' : 'rol_retirado',
			$evento->model,
			$asignado ? [] : $nombres,
			$asignado ? $nombres : [],
			$asignado ? 'Se asignaron roles' : 'Se retiraron roles',
		);
	}

	// Spatie entrega modelos, ids o colecciones segun la llamada
	private function nombres(mixed $roles): array
	{
		$lista = $roles instanceof Collection ? $roles->all() : (is_array($roles) ? $roles : [$roles]);

		return array_values(array_map(
			fn ($rol) => $rol instanceof Role ? $rol->name : (Role::find($rol)?->name ?? (string) $rol),
			$lista
		));
	}
}
