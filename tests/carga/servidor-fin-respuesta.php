<?php

// Router del servidor embebido de PHP que emula fastcgi_finish_request() de
// php-fpm: el cliente recibe la respuesta completa (con Content-Length) antes de
// que Laravel ejecute terminate(), donde se exportan las trazas a Tempo.
// Solo para medicion con `php -S` (la latencia que veria un cliente con php-fpm);
// no se usa en produccion. El servidor sigue siendo de un solo hilo: el trabajo de terminate()
// aun retrasa a la peticion siguiente.
//
// Uso, desde la raiz del proyecto:
//   (cd public && php -S 0.0.0.0:8000 ../tests/carga/servidor-fin-respuesta.php)

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');

// Archivos estaticos de public/ se sirven tal cual (igual que php artisan serve)
if ($uri !== '/' && file_exists(getcwd() . $uri)) {
	return false;
}

ob_start();

// Symfony\Component\HttpFoundation\Response::send() la llama si existe.
// Solo para medir con `php -S`: bajo php-fpm la funcion real ya existe y no se
// redefine.
if (!function_exists('fastcgi_finish_request')) {
	function fastcgi_finish_request(): bool
	{
		$cuerpo = '';
		while (ob_get_level() > 0) {
			$cuerpo = ob_get_clean() . $cuerpo;
		}
		if (!headers_sent()) {
			header('Content-Length: ' . strlen($cuerpo));
		}
		echo $cuerpo;
		flush();

		return true;
	}
}

require getcwd() . '/index.php';
