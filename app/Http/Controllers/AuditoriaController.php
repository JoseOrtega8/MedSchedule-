<?php

namespace App\Http\Controllers;

use App\Http\Requests\FiltrarAuditoriaRequest;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\PatientProfile;
use App\Models\Schedule;
use App\Models\Specialty;
use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;
use App\Support\CsvSeguro;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditoriaController extends Controller
{
	// Alias de URL a clase: la URL nunca lleva nombres de clase
	public const ENTIDADES = [
		'cita' => Appointment::class,
		'usuario' => User::class,
		'especialidad' => Specialty::class,
		'horario' => Schedule::class,
		'perfil_doctor' => DoctorProfile::class,
		'perfil_paciente' => PatientProfile::class,
	];

	public function index(FiltrarAuditoriaRequest $request): View
	{
		$filtros = $request->validated();

		return view('admin.auditoria.index', [
			'registros' => $this->consulta($filtros)->paginate(25)->withQueryString(),
			'filtros' => $filtros,
			'entidades' => self::ENTIDADES,
			'acciones' => ActivityLog::query()->distinct()->orderBy('action')->pluck('action'),
			'usuarios' => User::query()->orderBy('name')->get(['id', 'name']),
			'integridad' => Cache::get('auditoria.integridad'),
		]);
	}

	public function show(ActivityLog $registro): View
	{
		$anteriores = $registro->old_values ?? [];
		$nuevos = $registro->new_values ?? [];

		return view('admin.auditoria.show', [
			'registro' => $registro->load('user:id,name'),
			'campos' => array_values(array_unique(array_merge(array_keys($anteriores), array_keys($nuevos)))),
			'anteriores' => $anteriores,
			'nuevos' => $nuevos,
			'entidad' => array_search($registro->model_type, self::ENTIDADES, true) ?: null,
			'url_traza' => $this->url_traza($registro->trace_id),
		]);
	}

	public function linea_tiempo(string $entidad, int $id): View
	{
		abort_unless(isset(self::ENTIDADES[$entidad]), 404);

		return view('admin.auditoria.linea-tiempo', [
			'entidad' => $entidad,
			'id' => $id,
			'registros' => ActivityLog::query()
				->with('user:id,name')
				->where('model_type', self::ENTIDADES[$entidad])
				->where('model_id', $id)
				->orderByDesc('id')
				->get(),
		]);
	}

	public function exportar(FiltrarAuditoriaRequest $request, RegistradorAuditoria $registrador): StreamedResponse
	{
		$filtros = $request->validated();
		$registrador->registrar('auditoria_exportada', nuevos: $filtros, descripcion: 'Exportación CSV de la auditoría');

		return response()->streamDownload(function () use ($filtros) {
			$salida = fopen('php://output', 'w');
			fputcsv($salida, ['id', 'fecha', 'usuario', 'accion', 'entidad', 'id_entidad', 'ip', 'descripcion', 'antes', 'despues', 'trace_id']);

			$this->consulta($filtros)->reorder()->lazyByIdDesc(500)->each(function (ActivityLog $registro) use ($salida) {
				fputcsv($salida, array_map([CsvSeguro::class, 'celda'], [
					$registro->id,
					$registro->created_at?->toIso8601String(),
					$registro->user?->name,
					$registro->action,
					$registro->model_type,
					$registro->model_id,
					$registro->ip_address,
					$registro->description,
					$registro->old_values,
					$registro->new_values,
					$registro->trace_id,
				]));
			});

			fclose($salida);
		}, 'auditoria-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
	}

	private function consulta(array $filtros): Builder
	{
		return ActivityLog::query()
			->with('user:id,name')
			->when($filtros['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
			->when($filtros['action'] ?? null, fn ($q, $v) => $q->where('action', $v))
			->when($filtros['entidad'] ?? null, fn ($q, $v) => $q->where('model_type', self::ENTIDADES[$v]))
			->when($filtros['desde'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', Carbon::parse($v)->startOfDay()))
			->when($filtros['hasta'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', Carbon::parse($v)->endOfDay()))
			->when($filtros['ip'] ?? null, fn ($q, $v) => $q->where('ip_address', $v))
			->orderByDesc('id');
	}

	// Enlace a la traza en Grafana Explore (Tempo), si el registro tiene trace_id
	private function url_traza(?string $trace_id): ?string
	{
		if ($trace_id === null) {
			return null;
		}

		$panel = [
			'datasource' => 'tempo',
			'queries' => [['refId' => 'A', 'queryType' => 'traceql', 'query' => $trace_id]],
			'range' => ['from' => 'now-2d', 'to' => 'now'],
		];

		return rtrim((string) config('auditoria.grafana_url'), '/') . '/explore?left=' . rawurlencode(json_encode($panel));
	}
}
