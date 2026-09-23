<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>MedSchedule - Auditoría</title>
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
			<x-topbar title="Auditoría" icon="bi bi-shield-check" subtitle="Admin / Auditoría"
				:show-avatar-menu="true" badge-text="admin" badge-tone="danger" avatar-text="AD" avatar-color="#1976d2" />

			<div class="dashboard-content p-4">
				{{-- Estado de integridad: ultimo resultado de auditoria:verificar --}}
				@if ($integridad === null)
					<div class="alert alert-secondary" data-testid="integridad">Integridad aún no verificada. Ejecutar <code>php artisan auditoria:verificar</code>.</div>
				@elseif ($integridad['estado'] === 'integra')
					<div class="alert alert-success" data-testid="integridad"><i class="bi bi-shield-check"></i> Cadena íntegra · {{ $integridad['revisados'] }} registros · verificada {{ $integridad['verificado_en'] }}</div>
				@else
					<div class="alert alert-danger" data-testid="integridad"><i class="bi bi-shield-exclamation"></i> Cadena comprometida en el registro <a href="{{ route('admin.auditoria.show', $integridad['id_roto']) }}">#{{ $integridad['id_roto'] }}</a> ({{ $integridad['fecha_rota'] }}) · verificada {{ $integridad['verificado_en'] }}</div>
				@endif

				<form method="GET" action="{{ route('admin.auditoria') }}" class="card border-0 shadow-sm mb-4">
					<div class="card-body row g-3 align-items-end">
						<div class="col-lg-2 col-md-4">
							<label class="form-label" for="user_id">Usuario</label>
							<select class="form-select" id="user_id" name="user_id">
								<option value="">Todos</option>
								@foreach ($usuarios as $usuario)
									<option value="{{ $usuario->id }}" @selected(($filtros['user_id'] ?? null) == $usuario->id)>{{ $usuario->name }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-lg-2 col-md-4">
							<label class="form-label" for="action">Acción</label>
							<select class="form-select" id="action" name="action">
								<option value="">Todas</option>
								@foreach ($acciones as $accion)
									<option value="{{ $accion }}" @selected(($filtros['action'] ?? null) === $accion)>{{ $accion }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-lg-2 col-md-4">
							<label class="form-label" for="entidad">Entidad</label>
							<select class="form-select" id="entidad" name="entidad">
								<option value="">Todas</option>
								@foreach (array_keys($entidades) as $alias)
									<option value="{{ $alias }}" @selected(($filtros['entidad'] ?? null) === $alias)>{{ $alias }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-lg-2 col-md-4">
							<label class="form-label" for="desde">Desde</label>
							<input class="form-control" id="desde" name="desde" type="date" value="{{ $filtros['desde'] ?? '' }}" />
						</div>
						<div class="col-lg-2 col-md-4">
							<label class="form-label" for="hasta">Hasta</label>
							<input class="form-control" id="hasta" name="hasta" type="date" value="{{ $filtros['hasta'] ?? '' }}" />
						</div>
						<div class="col-lg-2 col-md-4">
							<label class="form-label" for="ip">IP</label>
							<input class="form-control" id="ip" name="ip" type="text" value="{{ $filtros['ip'] ?? '' }}" />
						</div>
						<div class="col-12 d-flex gap-2">
							<button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
							<a class="btn btn-outline-secondary" href="{{ route('admin.auditoria') }}">Limpiar</a>
							<a class="btn btn-outline-success ms-auto" href="{{ route('admin.auditoria.exportar', request()->query()) }}"><i class="bi bi-filetype-csv"></i> Exportar CSV</a>
						</div>
						@if ($errors->any())
							<div class="col-12"><div class="alert alert-warning mb-0">{{ implode(' ', $errors->all()) }}</div></div>
						@endif
					</div>
				</form>

				<div class="card border-0 shadow-sm">
					<div class="table-responsive">
						<table class="table table-hover align-middle mb-0">
							<thead>
								<tr><th>#</th><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Entidad</th><th>IP</th><th>Descripción</th><th></th></tr>
							</thead>
							<tbody>
								@forelse ($registros as $registro)
									<tr>
										<td>{{ $registro->id }}</td>
										<td>{{ $registro->created_at?->format('Y-m-d H:i:s') }}</td>
										<td>{{ $registro->user?->name ?? 'Sistema' }}</td>
										<td><span class="badge bg-secondary">{{ $registro->action }}</span></td>
										<td>{{ class_basename((string) $registro->model_type) }} {{ $registro->model_id ? '#' . $registro->model_id : '' }}</td>
										<td>{{ $registro->ip_address }}</td>
										<td>{{ $registro->description }}</td>
										<td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.auditoria.show', $registro) }}">Detalle</a></td>
									</tr>
								@empty
									<tr><td colspan="8" class="text-center text-muted py-4">Sin registros para estos filtros.</td></tr>
								@endforelse
							</tbody>
						</table>
					</div>
					<div class="p-3">{{ $registros->links('pagination::bootstrap-5') }}</div>
				</div>
			</div>
		</div>
	</div>
</body>

</html>
