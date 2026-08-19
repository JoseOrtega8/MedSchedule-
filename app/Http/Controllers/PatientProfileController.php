<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PatientProfileController extends Controller
{
	/**
	 * Ver el perfil médico del paciente autenticado.
	 */
	public function show(Request $request): JsonResponse
	{
		$profile = $this->profileForUser($request->user());

		return response()->json([
			'profile' => $this->formatProfile($profile),
		]);
	}

	/**
	 * Actualizar el perfil médico del paciente autenticado.
	 */
	public function update(Request $request): JsonResponse
	{
		$validated = $request->validate([
			'birth_date' => ['nullable', 'date'],
			'blood_type' => ['nullable', 'in:A+,A-,B+,B-,AB+,AB-,O+,O-'],
			'allergies' => ['nullable', 'string', 'max:2000'],
			'chronic_conditions' => ['nullable', 'string', 'max:2000'],
			'emergency_contact_name' => ['nullable', 'string', 'max:150'],
			'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
			'curp' => ['nullable', 'string', 'size:18', 'alpha_num'],
		]);

		$user = $request->user();
		$profile = $this->profileForUser($user);

		// curp única, sin contar el propio perfil
		if (!empty($validated['curp'])) {
			$curpExists = PatientProfile::where('curp', $validated['curp'])
				->where('id', '!=', $profile->id)
				->exists();

			if ($curpExists) {
				return response()->json([
					'message' => 'Ya existe un paciente registrado con ese CURP.',
					'errors' => [
						'curp' => ['Este CURP ya está en uso.'],
					],
				], 422);
			}
		}

		$oldValues = $profile->only(array_keys($validated));

		$profile->update($validated);

		ActivityLog::create([
			'user_id' => $user->id,
			'action' => 'update',
			'model_type' => PatientProfile::class,
			'model_id' => $profile->id,
			'description' => 'El paciente actualizó su perfil médico',
			'ip_address' => $request->ip(),
			'user_agent' => $request->userAgent(),
			'old_values' => $oldValues,
			'new_values' => $validated,
		]);

		return response()->json([
			'message' => 'Perfil médico actualizado correctamente.',
			'profile' => $this->formatProfile($profile->fresh()),
		]);
	}

	/**
	 * Subir/actualizar la foto de perfil del paciente autenticado.
	 */
	public function updatePhoto(Request $request): JsonResponse
	{
		$validated = $request->validate([
			'photo' => ['required', 'image', 'max:4096'], // máx 4MB
		]);

		$user = $request->user();
		$profile = $this->profileForUser($user);

		// Borrar foto anterior si existía
		if ($profile->photo_path && Storage::disk('public')->exists($profile->photo_path)) {
			Storage::disk('public')->delete($profile->photo_path);
		}

		$path = $request->file('photo')->store('patient_photos', 'public');

		$profile->update(['photo_path' => $path]);

		ActivityLog::create([
			'user_id' => $user->id,
			'action' => 'update',
			'model_type' => PatientProfile::class,
			'model_id' => $profile->id,
			'description' => 'El paciente actualizó su foto de perfil',
			'ip_address' => $request->ip(),
			'user_agent' => $request->userAgent(),
		]);

		return response()->json([
			'message' => 'Foto de perfil actualizada correctamente.',
			'profile' => $this->formatProfile($profile->fresh()),
		]);
	}

	/**
	 * Ver el perfil médico de un paciente específico (solo doctor).
	 */
	public function showForDoctor(Request $request, int $id): JsonResponse
	{
		$patient = User::role('patient')->findOrFail($id);
		$profile = $this->profileForUser($patient);

		return response()->json([
			'profile' => $this->formatProfile($profile),
		]);
	}

	/**
	 * Obtiene (o crea) el perfil médico de un usuario.
	 */
	private function profileForUser(User $user): PatientProfile
	{
		return PatientProfile::firstOrCreate(['user_id' => $user->id]);
	}

	/**
	 * Da forma a la respuesta JSON del perfil, incluyendo la URL pública de la foto.
	 */
	private function formatProfile(PatientProfile $profile): array
	{
		return [
			'id' => $profile->id,
			'user_id' => $profile->user_id,
			'birth_date' => $profile->birth_date,
			'blood_type' => $profile->blood_type,
			'allergies' => $profile->allergies,
			'chronic_conditions' => $profile->chronic_conditions,
			'emergency_contact_name' => $profile->emergency_contact_name,
			'emergency_contact_phone' => $profile->emergency_contact_phone,
			'curp' => $profile->curp,
			'photo_url' => $profile->photo_path
				? Storage::disk('public')->url($profile->photo_path)
				: null,
		];
	}
}
