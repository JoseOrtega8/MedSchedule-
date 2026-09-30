<?php

namespace App\Listeners\Auditoria;

use App\Services\Auditoria\RegistradorAuditoria;
use Illuminate\Auth\Events\PasswordReset;

class AuditarRestablecimientoPassword
{
	public function __construct(private RegistradorAuditoria $registrador)
	{
	}

	public function handle(PasswordReset $evento): void
	{
		$this->registrador->registrar(
			'password_restablecida',
			$evento->user,
			descripcion: 'El usuario restableció su contraseña',
			user_id: $evento->user->getAuthIdentifier(),
		);
	}
}
