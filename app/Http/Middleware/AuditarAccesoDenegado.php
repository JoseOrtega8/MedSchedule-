<?php

namespace App\Http\Middleware;

use App\Services\Auditoria\RegistradorAuditoria;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

// Registra los 403 de las rutas de administracion. Va antes de role:admin:
// la excepcion del rol se convierte en respuesta 403 antes de llegar aqui.
class AuditarAccesoDenegado
{
	public function __construct(private RegistradorAuditoria $registrador)
	{
	}

	public function handle(Request $request, Closure $next): Response
	{
		$respuesta = $next($request);

		if ($respuesta->getStatusCode() === Response::HTTP_FORBIDDEN) {
			try {
				$this->registrador->registrar(
					'acceso_denegado',
					descripcion: 'Acceso denegado a ' . $request->method() . ' /' . ltrim($request->path(), '/'),
				);
			} catch (Throwable $error) {
				// El usuario ya recibe su 403; se deja constancia del fallo de auditoria
				Log::error('No se pudo auditar un acceso denegado', ['error' => $error->getMessage()]);
			}
		}

		return $respuesta;
	}
}
