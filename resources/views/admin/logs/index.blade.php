<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="UTF-8">
	<title>Panel de Logs — MedSchedule</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light p-4">

	<div class="container-fluid">
		<h4 class="mb-4">Panel de Logs de Actividad</h4>

		{{-- FILTROS --}}
		<form method="GET" action="{{ route('admin.logs.index') }}" class="card p-3 mb-4">
			<div class="row g-2">
				<div class="col-md-2">
					<select name="action" class="form-select form-select-sm">
						<option value="">Todas las acciones</option>
						<option value="login" {{ request('action') == 'login' ? 'selected' : '' }}>login</option>
						<option value="logout" {{ request('action') == 'logout' ? 'selected' : '' }}>logout</option>
						<option value="create" {{ request('action') == 'create' ? 'selected' : '' }}>create</option>
						<option value="update" {{ request('action') == 'update' ? 'selected' : '' }}>update</option>
						<option value="delete" {{ request('action') == 'delete' ? 'selected' : '' }}>delete</option>
					</select>
				</div>
				<div class="col-md-2">
					<select name="model_type" class="form-select form-select-sm">
						<option value="">Todos los modelos</option>
						<option value="User" {{ request('model_type') == 'User' ? 'selected' : '' }}>User</option>
						<option value="Appointment" {{ request('model_type') == 'Appointment' ? 'selected' : '' }}>Appointment</option>
						<option value="Schedule" {{ request('model_type') == 'Schedule' ? 'selected' : '' }}>Schedule</option>
					</select>
				</div>
				<div class="col-md-2">
					<input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
				</div>
				<div class="col-md-2">
					<input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
				</div>
				<div class="col-md-2">
					<button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
					<a href="{{ route('admin.logs.index') }}" class="btn btn-secondary btn-sm">Limpiar</a>
				</div>
			</div>
		</form>

		{{-- TABLA --}}
		<div class="card">
			<div class="card-body p-0">
				<table class="table table-sm table-hover mb-0" style="font-size:13px">
					<thead class="table-light">
						<tr>
							<th class="px-3 py-2">Usuario</th>
							<th>action</th>
							<th>model_type</th>
							<th>description</th>
							<th>ip_address</th>
							<th>created_at</th>
							<th>Detalle</th>
						</tr>
					</thead>
					<tbody>
						@forelse($logs as $log)
						<tr>
							<td class="px-3 py-2">
								{{ $log->user ? $log->user->name . ' ' . $log->user->last_name : 'Sistema' }}
							</td>
							<td>
								<span class="badge 
                                @if($log->action == 'login') bg-success
                                @elseif($log->action == 'logout') bg-secondary
                                @elseif($log->action == 'create') bg-info
                                @elseif($log->action == 'update') bg-warning text-dark
                                @elseif($log->action == 'delete') bg-danger
                                @else bg-light text-dark
                                @endif">
									{{ $log->action }}
								</span>
							</td>
							<td class="text-muted">{{ $log->model_type ?? '—' }}</td>
							<td>{{ $log->description }}</td>
							<td class="text-muted">{{ $log->ip_address }}</td>
							<td class="text-muted">{{ $log->created_at }}</td>
							<td>
								<a href="{{ route('admin.logs.show', $log->id) }}" class="btn btn-sm btn-outline-secondary" style="font-size:11px">Ver</a>
							</td>
						</tr>
						@empty
						<tr>
							<td colspan="7" class="text-center text-muted py-4">No hay logs registrados</td>
						</tr>
						@endforelse
					</tbody>
				</table>

				{{-- PAGINACION --}}
				<div class="px-3 py-2 d-flex justify-content-between align-items-center">
					<small class="text-muted">{{ $logs->total() }} registros totales</small>
					{{ $logs->withQueryString()->links() }}
				</div>
			</div>
		</div>
	</div>

</body>

</html>