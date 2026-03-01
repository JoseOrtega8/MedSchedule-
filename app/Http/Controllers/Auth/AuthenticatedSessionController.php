<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
	/**
	 * Display the login view.
	 */
	public function create(): View
	{
		return view('auth.login');
	}

	/**
	 * Handle an incoming authentication request.
	 */
	public function store(LoginRequest $request): RedirectResponse
	{
		$request->authenticate();

		$request->session()->regenerate();

		$user = Auth::user();

		// Registro en activity_logs
		ActivityLog::create([
			'user_id'     => $user->id,
			'action'      => 'login',
			'description' => 'El usuario inició sesión',
			'ip_address'  => $request->ip(),
			'user_agent'  => $request->userAgent(),
		]);

		// Redirección por rol con Spatie
		if ($user->hasRole('admin')) {
			return redirect()->route('admin.dashboard');
		}

		if ($user->hasRole('doctor')) {
			return redirect()->route('doctor.dashboard');
		}

		return redirect()->route('patient.dashboard');
	}

	/**
	 * Destroy an authenticated session.
	 */
	public function destroy(Request $request): RedirectResponse
	{
		$user = Auth::user();

		// Registro en activity_logs
		ActivityLog::create([
			'user_id'     => $user->id,
			'action'      => 'logout',
			'description' => 'El usuario cerró sesión',
			'ip_address'  => $request->ip(),
			'user_agent'  => $request->userAgent(),
		]);

		Auth::guard('web')->logout();

		$request->session()->invalidate();

		$request->session()->regenerateToken();

		return redirect('/');
	}
}
