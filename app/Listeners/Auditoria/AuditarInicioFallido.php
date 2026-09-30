<?php

namespace App\Listeners\Auditoria;

use App\Services\Auditoria\RegistradorAuditoria;
use App\Support\Enmascarar;
use Illuminate\Auth\Events\Failed;

class AuditarInicioFallido
{
	public function __construct(private RegistradorAuditoria $registrador)
	{
	}

	public function handle(Failed $evento): void
	{
		$correo = (string) ($evento->credentials['email'] ?? '');

		$this->registrador->registrar(
			'login_fallido',
			descripcion: 'Intento de inicio de sesión fallido para ' . Enmascarar::correo($correo),
			user_id: $evento->user?->getAuthIdentifier(),
		);
	}
}
