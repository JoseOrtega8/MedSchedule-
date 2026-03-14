<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SpecialtyController extends Controller
{
    public function indexData(Request $request): JsonResponse
    {
        return response()->json([
            'admin' => [
                'name' => 'Juan Carlos',
                'initials' => 'JC',
                'role' => 'admin',
            ],
            'specialties' => $this->specialtiesPayload($request),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateSpecialty($request);
        $specialties = $this->specialtiesPayload($request);

        if ($this->hasDuplicateName($specialties, $validated['name'])) {
            return response()->json([
                'message' => 'Ya existe una especialidad con ese nombre.',
                'errors' => [
                    'name' => ['Ya existe una especialidad con ese nombre.'],
                ],
            ], 422);
        }

        $specialty = [
            'id' => $this->nextId($specialties),
            'name' => $validated['name'],
            'description' => $validated['description'],
            'status' => (int) $validated['status'],
            'doctorsCount' => 0,
            ...$this->inferPresentation($validated['name']),
        ];

        array_unshift($specialties, $specialty);
        $request->session()->put('admin_specialties_payload', $specialties);

        return response()->json([
            'message' => 'Especialidad creada correctamente.',
            'specialty' => $specialty,
        ]);
    }

    public function update(Request $request, int $specialty): JsonResponse
    {
        $validated = $this->validateSpecialty($request);
        $specialties = $this->specialtiesPayload($request);
        $index = $this->findSpecialtyIndex($specialties, $specialty);

        if ($index === null) {
            return response()->json([
                'message' => 'La especialidad solicitada no existe.',
            ], 404);
        }

        if ($this->hasDuplicateName($specialties, $validated['name'], $specialty)) {
            return response()->json([
                'message' => 'Ya existe una especialidad con ese nombre.',
                'errors' => [
                    'name' => ['Ya existe una especialidad con ese nombre.'],
                ],
            ], 422);
        }

        $specialties[$index] = [
            ...$specialties[$index],
            'name' => $validated['name'],
            'description' => $validated['description'],
            'status' => (int) $validated['status'],
            ...$this->inferPresentation($validated['name']),
        ];

        $request->session()->put('admin_specialties_payload', $specialties);

        return response()->json([
            'message' => 'Especialidad actualizada correctamente.',
            'specialty' => $specialties[$index],
        ]);
    }

    public function destroy(Request $request, int $specialty): JsonResponse
    {
        $specialties = $this->specialtiesPayload($request);
        $index = $this->findSpecialtyIndex($specialties, $specialty);

        if ($index === null) {
            return response()->json([
                'message' => 'La especialidad solicitada no existe.',
            ], 404);
        }

        if ((int) ($specialties[$index]['doctorsCount'] ?? 0) > 0) {
            return response()->json([
                'message' => 'No puedes eliminar una especialidad con doctores asignados.',
            ], 422);
        }

        array_splice($specialties, $index, 1);
        $request->session()->put('admin_specialties_payload', $specialties);

        return response()->json([
            'message' => 'Especialidad eliminada correctamente.',
        ]);
    }

    private function validateSpecialty(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:500'],
            'status' => ['required', Rule::in([0, 1, '0', '1'])],
        ]);
    }

    private function specialtiesPayload(Request $request): array
    {
        $specialties = $request->session()->get('admin_specialties_payload');

        if (! is_array($specialties)) {
            $specialties = $this->defaultSpecialties();
            $request->session()->put('admin_specialties_payload', $specialties);
        }

        return $specialties;
    }

    private function defaultSpecialties(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Cardiologia',
                'description' => 'Enfermedades del corazon',
                'status' => 1,
                'doctorsCount' => 3,
                'icon' => 'bi bi-heart-pulse',
                'tone' => 'primary',
            ],
            [
                'id' => 2,
                'name' => 'Oftalmologia',
                'description' => 'Enfermedades de los ojos',
                'status' => 1,
                'doctorsCount' => 2,
                'icon' => 'bi bi-eye',
                'tone' => 'success',
            ],
            [
                'id' => 3,
                'name' => 'Neurologia',
                'description' => 'Sistema nervioso',
                'status' => 0,
                'doctorsCount' => 0,
                'icon' => 'bi bi-brain',
                'tone' => 'muted',
            ],
        ];
    }

    private function hasDuplicateName(array $specialties, string $name, ?int $exceptId = null): bool
    {
        $normalized = $this->normalizeName($name);

        foreach ($specialties as $specialty) {
            if ($exceptId !== null && (int) ($specialty['id'] ?? 0) === $exceptId) {
                continue;
            }

            if ($this->normalizeName($specialty['name'] ?? '') === $normalized) {
                return true;
            }
        }

        return false;
    }

    private function normalizeName(string $name): string
    {
        return Str::of($name)
            ->lower()
            ->ascii()
            ->trim()
            ->value();
    }

    private function nextId(array $specialties): int
    {
        $maxId = 0;

        foreach ($specialties as $specialty) {
            $maxId = max($maxId, (int) ($specialty['id'] ?? 0));
        }

        return $maxId + 1;
    }

    private function findSpecialtyIndex(array $specialties, int $specialtyId): ?int
    {
        foreach ($specialties as $index => $specialty) {
            if ((int) ($specialty['id'] ?? 0) === $specialtyId) {
                return $index;
            }
        }

        return null;
    }

    private function inferPresentation(string $name): array
    {
        $normalized = $this->normalizeName($name);

        if (str_contains($normalized, 'cardio')) {
            return ['icon' => 'bi bi-heart-pulse', 'tone' => 'primary'];
        }

        if (str_contains($normalized, 'oft') || str_contains($normalized, 'ocular') || str_contains($normalized, 'vision')) {
            return ['icon' => 'bi bi-eye', 'tone' => 'success'];
        }

        if (str_contains($normalized, 'neur')) {
            return ['icon' => 'bi bi-brain', 'tone' => 'muted'];
        }

        if (str_contains($normalized, 'pedia')) {
            return ['icon' => 'bi bi-person-hearts', 'tone' => 'warning'];
        }

        return ['icon' => 'bi bi-hospital', 'tone' => 'primary'];
    }
}
