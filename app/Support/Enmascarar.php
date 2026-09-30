<?php

namespace App\Support;

// Enmascarado de datos personales para auditoria y logs
final class Enmascarar
{
	public static function correo(string $correo): string
	{
		if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
			return '***';
		}

		[$local, $dominio] = explode('@', $correo, 2);

		return mb_substr($local, 0, 1) . '***@' . $dominio;
	}
}
