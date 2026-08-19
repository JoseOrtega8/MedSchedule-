<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
	/**
	 * Handle an incoming request.
	 *
	 * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
	 */
	public function handle(Request $request, Closure $next): Response
	{
		$response = $next($request);

		$response->headers->set('X-Frame-Options', 'SAMEORIGIN');
		$response->headers->set('X-Content-Type-Options', 'nosniff');
		$response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
		$response->headers->set('X-XSS-Protection', '1; mode=block');

		// En local, el servidor de Vite (npm run dev) sirve los assets desde
		// otro origen (127.0.0.1:5173) para hot-reload. En producción los
		// assets ya están compilados y se sirven desde el mismo dominio,
		// por lo que esa excepción no aplica ni hace falta.
		$viteDevServer = app()->environment('local')
			? '127.0.0.1:5173 localhost:5173 ws://127.0.0.1:5173 ws://localhost:5173'
			: '';

		$response->headers->set(
			'Content-Security-Policy',
			"default-src 'self'; " .
				"script-src 'self' 'unsafe-inline' cdn.jsdelivr.net cdnjs.cloudflare.com {$viteDevServer}; " .
				"style-src 'self' 'unsafe-inline' cdn.jsdelivr.net cdnjs.cloudflare.com fonts.bunny.net {$viteDevServer}; " .
				"font-src 'self' cdnjs.cloudflare.com fonts.bunny.net; " .
				"img-src 'self' data: blob:; " .
				"connect-src 'self' cdnjs.cloudflare.com {$viteDevServer};"
		);

		return $response;
	}
}
