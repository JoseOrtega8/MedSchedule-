<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>MedSchedule - Detalle de auditoría</title>
	<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet" />
	<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
	@vite(['resources/css/app.css', 'resources/js/topbar-date.js'])
</head>

<body>
	<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>
	<button class="mobile-toggle" onclick="toggleSidebar()"><i class="bi bi-list" style="font-size: 24px;"></i></button>

	<div class="app-wrapper">
		<x-sidebar active="admin-auditoria" variant="admin" />

		<div class="content-wrapper">
			<x-topbar title="Detalle de auditoría" icon="bi bi-shield-check" subtitle="Admin / Auditoría / Detalle"
				:show-avatar-menu="true" badge-text="admin" badge-tone="danger" avatar-text="AD" avatar-color="#1976d2" />

			<div class="dashboard-content p-4">
				<div class="card border-0 shadow-sm mb-4">
					<div class="card-body">
						<h2 class="h5">Registro #{{ $registro->id }} · <span class="badge bg-secondary">{{ $registro->action }}</span></h2>
						<p class="mb-1"><strong>Quién:</strong> {{ $registro->user?->name ?? 'Sistema' }}</p>
						<p class="mb-1"><strong>Cuándo:</strong> {{ $registro->created_at?->format('Y-m-d H:i:s') }} UTC</p>
						<p class="mb-1"><strong>Desde:</strong> {{ $registro->ip_address }} · {{ $registro->user_agent }}</p>
						<p class="mb-1"><strong>Qué:</strong> {{ $registro->description }}</p>
						@if ($entidad && $registro->model_id)
							<p class="mb-1"><a href="{{ route('admin.auditoria.entidad', ['entidad' => $entidad, 'id' => $registro->model_id]) }}">Ver línea de tiempo de {{ $entidad }} #{{ $registro->model_id }}</a></p>
						@endif
						@if ($url_traza)
							<p class="mb-0"><a href="{{ $url_traza }}" target="_blank" rel="noopener">Ver traza de la petición</a></p>
						@endif
					</div>
				</div>

				<div class="card border-0 shadow-sm">
					<table class="table mb-0">
						<thead><tr><th>Campo</th><th>Antes</th><th>Después</th></tr></thead>
						<tbody>
							@forelse ($campos as $campo)
								<tr>
									<td><code>{{ $campo }}</code></td>
									<td class="text-danger">{{ is_array($anteriores[$campo] ?? null) ? json_encode($anteriores[$campo]) : ($anteriores[$campo] ?? '—') }}</td>
									<td class="text-success">{{ is_array($nuevos[$campo] ?? null) ? json_encode($nuevos[$campo]) : ($nuevos[$campo] ?? '—') }}</td>
								</tr>
							@empty
								<tr><td colspan="3" class="text-muted">Este evento no registra cambios de campos.</td></tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</body>

</html>
