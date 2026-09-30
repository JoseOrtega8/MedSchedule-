<?php

namespace App\Listeners\Auditoria;

use App\Services\Auditoria\RegistradorAuditoria;
use Illuminate\Auth\Events\Logout;

class AuditarCierreSesion
{
	public function __construct(private RegistradorAuditoria $registrador)
	{
	}

	public function handle(Logout $evento): void
	{
		if ($evento->user === null) {
			return;
		}

		$this->registrador->registrar(
			'logout',
			$evento->user,
			descripcion: 'El usuario cerró sesión',
			user_id: $evento->user->getAuthIdentifier(),
		);
	}
}
