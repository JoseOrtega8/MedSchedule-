<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>MedSchedule - Línea de tiempo</title>
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
			<x-topbar title="Línea de tiempo" icon="bi bi-shield-check" subtitle="Admin / Auditoría / Línea de tiempo"
				:show-avatar-menu="true" badge-text="admin" badge-tone="danger" avatar-text="AD" avatar-color="#1976d2" />

			<div class="dashboard-content p-4">
				<h2 class="h5 mb-3">{{ $entidad }} #{{ $id }}</h2>
				<ul class="list-group">
					@forelse ($registros as $registro)
						<li class="list-group-item d-flex justify-content-between align-items-center">
							<span>
								<span class="badge bg-secondary">{{ $registro->action }}</span>
								{{ $registro->created_at?->format('Y-m-d H:i:s') }} · {{ $registro->user?->name ?? 'Sistema' }}
							</span>
							<a class="btn btn-sm btn-outline-primary" href="{{ route('admin.auditoria.show', $registro) }}">Detalle</a>
						</li>
					@empty
						<li class="list-group-item text-muted">Sin eventos para esta entidad.</li>
					@endforelse
				</ul>
			</div>
		</div>
	</div>
</body>

</html>
