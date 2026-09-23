<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>MedSchedule - Error</title>
	<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet" />
</head>

<body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">
	{{-- Sin detalle interno: solo el folio para que soporte localice la traza --}}
	<div class="card border-0 shadow-sm p-4 text-center" style="max-width: 480px;">
		<h1 class="h4 mb-3">Ocurrió un error inesperado</h1>
		<p class="text-muted mb-3">Ya quedó registrado. Si necesitas ayuda, comparte este folio con soporte.</p>
		{{-- Con trazas apagadas no hay trace_id: se usa el request_id de respaldo --}}
		@php($folio = request()->attributes->get('trace_id') ?? request()->attributes->get('request_id'))
		<p class="fw-semibold mb-4">Folio: {{ $folio ?? 'no disponible' }}</p>
		<a class="btn btn-primary" href="{{ url('/') }}">Volver al inicio</a>
	</div>
</body>

</html>
