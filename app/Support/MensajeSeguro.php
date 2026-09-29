<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Throwable;

// Helper unico para obtener, a partir de una excepcion o de texto libre, una
// version sin PII. Lo usa RedactarDatosSensibles (mensaje del registro,
// contexto y resumen de excepciones, delegando tambien PATRONES/MASCARA via
// self::redactar_texto()) y los tres puntos que exportan trazas a Tempo
// (evento 'exception' del span en Trazas::en_span, IniciarTraza::handle y
// TrazasServiceProvider::trazar_jobs), asi todos los caminos quedan seguros
// de la misma forma y con una sola definicion de la logica.
class MensajeSeguro
{
	public const MASCARA = '[redactado]';

	// Patrones para detectar PII/credenciales dentro de texto libre. La
	// coincidencia completa se reemplaza por MASCARA, incluida la palabra
	// Bearer/Basic en el segundo patron: asi no se filtra ni el esquema de auth.
	// Cuantificadores posesivos con limite superior (RFC 5321: max 64 en la
	// parte local, max 63 por etiqueta de dominio) para que el motor no
	// intente retroceder. No basta con '++' sin limite: aunque evita el
	// backtracking DENTRO de un intento, la clase de la parte local
	// ([A-Za-z0-9._%+-]) tambien coincide con las letras/puntos del dominio,
	// asi que sobre un dominio muy largo el motor sigue intentando un
	// "posible email" desde cada posicion dentro de el, y cada intento
	// consume-y-falla en O(resto de la cadena): eso es lo que da O(n^2). Con
	// el limite {1,64}+ cada intento fallido cuesta O(1), y el barrido total
	// vuelve a ser O(n). El dominio ademas se reescribe como
	// '(?:etiqueta\.){1,8}+TLD' en vez de una sola clase con '.' seguida de
	// '\.', para que la clase de la etiqueta y el punto literal no compitan
	// por el mismo caracter.
	public const PATRONES = [
		// correos electronicos
		'/[A-Za-z0-9._%+-]{1,64}+@(?:[A-Za-z0-9-]{1,63}+\.){1,8}+[A-Za-z]{2,24}+/',
		// credenciales Bearer/Basic en texto (se enmascara la coincidencia completa)
		'/\b(Bearer|Basic)\s++[A-Za-z0-9._~+\/=-]++/i',
		// CURP mexicana (18 caracteres)
		'/\b[A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z0-9]\d\b/',
	];

	public static function de_excepcion(Throwable $excepcion): string
	{
		if ($excepcion instanceof QueryException) {
			return self::de_query_exception($excepcion);
		}

		return self::redactar_texto(self::sustituir_causas_encadenadas($excepcion->getMessage(), $excepcion));
	}

	// Unico lugar que aplica PATRONES/MASCARA sobre texto libre: tanto esta
	// clase como RedactarDatosSensibles::redactar_texto delegan aqui, para no
	// duplicar la logica en dos sitios (ni tener una dependencia circular
	// entre App\Support y App\Logging).
	public static function redactar_texto(string $texto): string
	{
		return preg_replace(self::PATRONES, self::MASCARA, $texto);
	}

	// Recorre TODA la cadena de causas (getPrevious()) de $excepcion, no solo
	// un nivel. Por cada QueryException encontrada sustituye, dentro de
	// $texto, toda ocurrencia de:
	// - su getMessage() completo (el mensaje que arma Laravel, con los
	//   bindings interpolados en el SQL; ver QueryException::formatMessage)
	// - el getMessage() de SU PDOException previa (getPrevious() de la
	//   QueryException: el mensaje crudo del driver, p.ej.
	//   "Duplicate entry 'Penicilina-Grave' for key ...")
	// por el texto seguro de esa consulta. Cubre tanto una excepcion que
	// reenvia el mensaje completo de Laravel (ej.
	// RuntimeException('fallo: '.$q->getMessage())) como una que solo copia
	// el mensaje crudo del driver sin el formato de Laravel, y tambien
	// Illuminate\View\ViewException, cuyo mensaje es
	// $e->getMessage().' (View: ...)' (ver vendor
	// Illuminate\View\Engines\CompilerEngine::getMessage()/handleViewException()).
	private static function sustituir_causas_encadenadas(string $texto, Throwable $excepcion): string
	{
		$causa = $excepcion->getPrevious();

		while ($causa !== null) {
			if ($causa instanceof QueryException) {
				$seguro = self::de_query_exception($causa);
				$texto = str_replace($causa->getMessage(), $seguro, $texto);

				$previa = $causa->getPrevious();
				if ($previa !== null) {
					$texto = str_replace($previa->getMessage(), $seguro, $texto);
				}
			}

			$causa = $causa->getPrevious();
		}

		return $texto;
	}

	// getMessage() de una QueryException interpola los bindings dentro del SQL
	// (ver Illuminate\Database\QueryException::formatMessage): por eso el texto
	// seguro usa getSql(), la sentencia parametrizada sin bindings, con sus
	// literales incrustados (si los hubiera) tambien enmascarados.
	private static function de_query_exception(QueryException $excepcion): string
	{
		$texto = 'QueryException: ' . self::enmascarar_literales($excepcion->getSql());
		$sqlstate = $excepcion->getCode();

		if (!empty($sqlstate)) {
			$texto .= ' [SQLSTATE ' . $sqlstate . ']';
		}

		return $texto;
	}

	// Enmascara, dentro de una sentencia SQL, literales entre comillas simples
	// o dobles ('...' o "...") y numeros sueltos tras '=' o dentro de
	// 'IN (...)'. Regex acotadas (clase de caracteres negada sin
	// cuantificadores anidados: [^'\\]*, no (a|b)*) para evitar backtracking
	// catastrofico. El patron de 'IN (...)' usa cuantificadores posesivos
	// tanto en el prefijo ('\s*+', '\(\s*+') como en el cuerpo ('[\d,\s]++'):
	// sin esto, el '\s*' del prefijo y el espacio dentro de la clase
	// '[\d,\s]+' se solapan y el motor prueba todas las formas de repartir
	// los espacios entre ambos antes de fallar, lo que es cuadratico en la
	// cantidad de espacios.
	//
	// Limite conocido: no es un parser SQL. Una sentencia bien parametrizada
	// (con '?') no deberia tener nada que enmascarar aqui; esto es una red de
	// seguridad extra por si algun binding se incrusto igual, no una garantia
	// contra cualquier forma de incrustar un valor (p.ej. comillas escapadas
	// de forma no estandar, o un valor numerico que no siga a '=' ni a 'IN').
	private static function enmascarar_literales(string $sql): string
	{
		$sql = preg_replace('/\'[^\'\\\\]*\'/', "'?'", $sql);
		$sql = preg_replace('/"[^"\\\\]*"/', '"?"', $sql);
		$sql = preg_replace('/(=\s*)\d+(\.\d+)?/', '$1?', $sql);
		$sql = preg_replace('/(\bIN\s*+\(\s*+)[\d,\s]++(\))/i', '$1?$2', $sql);

		return $sql;
	}
}
