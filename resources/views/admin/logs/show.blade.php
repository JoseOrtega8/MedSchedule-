<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="UTF-8">
	<title>Detalle Log — MedSchedule</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light p-4">
	<div class="container">
		<div class="d-flex justify-content-between align-items-center mb-4">
			<h4>Detalle de Log #{{ $log->id }}</h4>
			<a href="{{ route('admin.logs') }}" class="btn btn-secondary btn-sm">← Volver</a>
		</div>
		<div class="card">
			<div class="card-body">
				<div class="row g-3">
					<div class="col-md-6">
						<label class="text-muted" style="font-size:11px">USUARIO</label>
						<div class="fw-semibold">{{ $log->user ? $log->user->name . ' ' . $log->user->last_name : 'Sistema' }}</div>
					</div>
					<div class="col-md-6">
						<label class="text-muted" style="font-size:11px">ACTION</label>
						<div><span class="badge bg-primary">{{ $log->action }}</span></div>
					</div>
					<div class="col-md-6">
						<label class="text-muted" style="font-size:11px">MODEL_TYPE</label>
						<div>{{ $log->model_type ?? '—' }}</div>
					</div>
					<div class="col-md-6">
						<label class="text-muted" style="font-size:11px">MODEL_ID</label>
						<div>{{ $log->model_id ?? '—' }}</div>
					</div>
					<div class="col-md-6">
						<label class="text-muted" style="font-size:11px">IP_ADDRESS</label>
						<div>{{ $log->ip_address ?? '—' }}</div>
					</div>
					<div class="col-md-6">
						<label class="text-muted" style="font-size:11px">USER_AGENT</label>
						<div class="text-muted" style="font-size:12px">{{ $log->user_agent ?? '—' }}</div>
					</div>
					<div class="col-md-12">
						<label class="text-muted" style="font-size:11px">DESCRIPTION</label>
						<div>{{ $log->description }}</div>
					</div>
					<div class="col-md-12">
						<label class="text-muted" style="font-size:11px">CREATED_AT</label>
						<div>{{ $log->created_at }}</div>
					</div>
					<div class="col-md-6">
						<label class="text-muted" style="font-size:11px">OLD_VALUES</label>
						<pre class="border rounded p-2 bg-white" style="font-size:12px;color:#721c24">{{ $log->old_values ? json_encode($log->old_values, JSON_PRETTY_PRINT) : 'null' }}</pre>
					</div>
					<div class="col-md-6">
						<label class="text-muted" style="font-size:11px">NEW_VALUES</label>
						<pre class="border rounded p-2 bg-white" style="font-size:12px;color:#155724">{{ $log->new_values ? json_encode($log->new_values, JSON_PRETTY_PRINT) : 'null' }}</pre>
					</div>
				</div>
			</div>
		</div>
	</div>
</body>

</html>