<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    public function handle(Request $request, Closure $next): Response
    {
        // TEMPORARY MOCK ACCESS CONTROL.
        // Replace this session fallback when the real authentication and roles backend is available.
        $currentUser = $request->session()->get('mock_current_user');

        if (! is_array($currentUser)) {
            $currentUser = [
                'id' => 1,
                'name' => 'Admin Mock',
                'role' => 'admin',
            ];
            $request->session()->put('mock_current_user', $currentUser);
        }

        if (($currentUser['role'] ?? null) !== 'admin') {
            abort(403, 'Esta pantalla solo esta disponible para usuarios con rol admin.');
        }

        return $next($request);
    }
}
