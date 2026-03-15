<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityLogController extends Controller
{
	/**
	 * Listar logs con filtros por action, model_type y rango de created_at
	 */
	public function index(Request $request)
	{
		if (!Auth::user()->hasRole('admin')) {
			abort(403);
		}

		$query = ActivityLog::with('user')
			->orderBy('created_at', 'desc');

		if ($request->filled('action')) {
			$query->where('action', $request->action);
		}

		if ($request->filled('model_type')) {
			$query->where('model_type', $request->model_type);
		}

		if ($request->filled('user_id')) {
			$query->where('user_id', $request->user_id);
		}

		if ($request->filled('date_from')) {
			$query->whereDate('created_at', '>=', $request->date_from);
		}

		if ($request->filled('date_to')) {
			$query->whereDate('created_at', '<=', $request->date_to);
		}

		$logs = $query->paginate(20);

		return view('admin.logs.index', compact('logs'));
	}

	/**
	 * Ver detalle de un log incluyendo old_values y new_values JSON
	 */
	public function show($id)
	{
		if (!Auth::user()->hasRole('admin')) {
			abort(403);
		}

		$log = ActivityLog::with('user')->findOrFail($id);

		return view('admin.logs.show', compact('log'));
	}

	/**
	 * Ver todos los logs de un usuario específico
	 */
	public function getByUser($user_id)
	{
		if (!Auth::user()->hasRole('admin')) {
			abort(403);
		}

		$logs = ActivityLog::with('user')
			->where('user_id', $user_id)
			->orderBy('created_at', 'desc')
			->paginate(20);

		return view('admin.logs.index', compact('logs'));
	}
}
