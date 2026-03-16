<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DoctorProfileController extends Controller
{
    public function indexData(Request $request): JsonResponse
    {
        return response()->json([
            'doctor' => $this->doctorProfilePayload($request),
            'specialties' => $this->specialtiesPayload($request),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'string', 'regex:/^[0-9+\-\s()]{8,20}$/'],
            'specialty_id' => ['required', 'string', 'max:80'],
            'license_number' => ['required', 'string', 'max:80'],
            'consultation_duration' => ['required', 'integer', 'min:10', 'max:120'],
            'bio' => ['required', 'string', 'min:20', 'max:1200'],
        ]);

        $profile = $this->doctorProfilePayload($request);

        if ($this->hasDuplicateLicenseNumber(
            $validated['license_number'],
            (int) ($profile['id'] ?? 0),
            $request,
        )) {
            return response()->json([
                'message' => 'Ya existe un perfil profesional con esa cedula.',
                'errors' => [
                    'license_number' => ['Ya existe un perfil profesional con esa cedula.'],
                ],
            ], 422);
        }

        $profile = [
            ...$profile,
            'name' => $validated['name'],
            'last_name' => $validated['last_name'],
            'phone' => $validated['phone'],
            'specialty_id' => $validated['specialty_id'],
            'license_number' => Str::upper($validated['license_number']),
            'consultation_duration' => (int) $validated['consultation_duration'],
            'bio' => $validated['bio'],
        ];

        $request->session()->put('doctor_profile_payload', $profile);

        return response()->json([
            'message' => 'Perfil actualizado correctamente.',
            'doctor' => $profile,
        ]);
    }

    public function updatePhoto(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'photo_data_url' => ['required', 'string'],
        ]);

        $photoDataUrl = $validated['photo_data_url'];

        if (! str_starts_with($photoDataUrl, 'data:image/')) {
            return response()->json([
                'message' => 'La foto enviada no es valida.',
            ], 422);
        }

        $profile = $this->doctorProfilePayload($request);
        $profile['photo_data_url'] = $photoDataUrl;

        $request->session()->put('doctor_profile_payload', $profile);

        return response()->json([
            'message' => 'Foto de perfil actualizada correctamente.',
            'doctor' => $profile,
        ]);
    }

    private function doctorProfilePayload(Request $request): array
    {
        $profile = $request->session()->get('doctor_profile_payload');

        if (! is_array($profile)) {
            $profile = $this->defaultDoctorProfile();
            $request->session()->put('doctor_profile_payload', $profile);
        }

        return [
            ...$this->defaultDoctorProfile(),
            ...$profile,
        ];
    }

    private function specialtiesPayload(Request $request): array
    {
        $specialties = $request->session()->get('admin_specialties_payload');

        if (is_array($specialties)) {
            return collect($specialties)
                ->filter(fn (array $specialty) => (int) ($specialty['status'] ?? 0) === 1)
                ->map(fn (array $specialty) => [
                    'id' => Str::of($specialty['name'] ?? '')
                        ->lower()
                        ->ascii()
                        ->replace(' ', '_')
                        ->value(),
                    'label' => $specialty['name'] ?? '',
                ])
                ->values()
                ->all();
        }

        return [
            ['id' => 'cardiologia', 'label' => 'Cardiologia'],
            ['id' => 'oftalmologia', 'label' => 'Oftalmologia'],
            ['id' => 'neurologia', 'label' => 'Neurologia'],
            ['id' => 'pediatria', 'label' => 'Pediatria'],
        ];
    }

    private function hasDuplicateLicenseNumber(string $licenseNumber, int $currentDoctorId, Request $request): bool
    {
        $normalized = Str::upper(trim($licenseNumber));

        foreach ($this->licenseRegistry($request) as $entry) {
            if ((int) ($entry['doctor_id'] ?? 0) === $currentDoctorId) {
                continue;
            }

            if (Str::upper((string) ($entry['license_number'] ?? '')) === $normalized) {
                return true;
            }
        }

        return false;
    }

    private function licenseRegistry(Request $request): array
    {
        $registry = $request->session()->get('doctor_profile_license_registry');

        if (! is_array($registry)) {
            $registry = [
                ['doctor_id' => 14, 'license_number' => 'CED-123456'],
                ['doctor_id' => 2, 'license_number' => 'CED-998877'],
                ['doctor_id' => 3, 'license_number' => 'MED-332211'],
            ];
            $request->session()->put('doctor_profile_license_registry', $registry);
        }

        return $registry;
    }

    private function defaultDoctorProfile(): array
    {
        return [
            'id' => 14,
            'name' => 'Miguel',
            'last_name' => 'Garcia',
            'email' => 'mgarcia@mail.com',
            'phone' => '662-987-6543',
            'role' => 'doctor',
            'specialty_id' => 'cardiologia',
            'license_number' => 'CED-123456',
            'consultation_duration' => 30,
            'bio' => 'Cardiologo con 10 anos de experiencia en el IMSS.',
            'photo_data_url' => null,
        ];
    }
}
