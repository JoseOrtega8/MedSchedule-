<?php

namespace App\Http\Requests;

use App\Http\Controllers\AuditoriaController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltrarAuditoriaRequest extends FormRequest
{
	// La ruta ya exige role:admin
	public function authorize(): bool
	{
		return true;
	}

	public function rules(): array
	{
		return [
			'user_id' => ['nullable', 'integer', 'exists:users,id'],
			'action' => ['nullable', 'string', 'max:100'],
			'entidad' => ['nullable', Rule::in(array_keys(AuditoriaController::ENTIDADES))],
			'desde' => ['nullable', 'date'],
			'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
			'ip' => ['nullable', 'ip'],
		];
	}
}
