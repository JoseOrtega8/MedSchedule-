<?php

namespace App\Support;

// Celdas de CSV que una hoja de calculo no interpreta como formula
final class CsvSeguro
{
	private const INICIOS_PELIGROSOS = ['=', '+', '-', '@', "\t", "\r"];

	public static function celda(mixed $valor): string
	{
		if (is_array($valor)) {
			$valor = json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}
		$texto = (string) ($valor ?? '');

		if ($texto !== '' && in_array($texto[0], self::INICIOS_PELIGROSOS, true)) {
			return "'" . $texto;
		}

		return $texto;
	}
}
