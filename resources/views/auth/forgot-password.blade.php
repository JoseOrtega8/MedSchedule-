<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="csrf-token" content="{{ csrf_token() }}">

	<title>Recuperar contraseña - MedSchedule</title>

	<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

	<style>
		body {
			background: linear-gradient(135deg, #1e3c72, #2a5298);
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 24px 0;
		}

		.login-card {
			width: 100%;
			max-width: 420px;
			border-radius: 12px;
			opacity: 0;
			transform: translateY(16px);
			animation: fadeInUp .45s ease forwards;
		}

		@keyframes fadeInUp {
			to {
				opacity: 1;
				transform: translateY(0);
			}
		}

		.brand {
			font-weight: 700;
			font-size: 22px;
		}

		.btn-primary {
			transition: transform .15s ease, box-shadow .15s ease;
		}

		.btn-primary:hover:not(:disabled) {
			transform: translateY(-1px);
			box-shadow: 0 6px 14px rgba(13, 110, 253, .35);
		}

		.btn-primary:active:not(:disabled) {
			transform: translateY(0);
		}

		.alert-success,
		.alert-danger {
			opacity: 0;
			animation: fadeInUp .35s ease forwards;
		}

		.back-link {
			transition: color .15s ease;
		}

		.spinner-border-sm {
			display: none;
			margin-right: 6px;
		}

		.is-loading .spinner-border-sm {
			display: inline-block;
		}
	</style>
</head>

<body>

	<div class="card login-card shadow-lg">
		<div class="card-body p-4">

			<div class="text-center mb-4">
				<div class="brand text-primary">MedSchedule</div>
				<p class="text-muted small mb-0">Recuperar contraseña</p>
			</div>

			<p class="text-muted small text-center mb-4">
				¿Olvidaste tu contraseña? No hay problema. Ingresa tu correo y te enviaremos
				un enlace para que puedas establecer una nueva.
			</p>

			{{-- Mensaje de éxito de Laravel (session status) --}}
			@if (session('status'))
				<div class="alert alert-success py-2 small" role="alert" id="statusAlert">
					<i class="bi bi-check-circle me-1"></i>{{ session('status') }}
				</div>
			@endif

			{{-- Errores de validación del backend (ej. email no registrado) --}}
			@if ($errors->any())
				<div class="alert alert-danger py-2 small" role="alert">
					<i class="bi bi-exclamation-triangle me-1"></i>{{ $errors->first() }}
				</div>
			@endif

			<form method="POST" action="{{ route('password.email') }}" id="forgotPasswordForm" novalidate>
				@csrf

				<div class="mb-3">
					<label for="email" class="form-label">Correo</label>
					<input
						type="email"
						id="email"
						name="email"
						class="form-control"
						value="{{ old('email') }}"
						required
						autofocus
						placeholder="tucorreo@ejemplo.com">
					<div class="invalid-feedback" id="emailError">
						Ingresa un correo electrónico válido.
					</div>
				</div>

				<div class="d-grid mb-3">
					<button class="btn btn-primary" type="submit" id="submitBtn">
						<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
						Enviar enlace de recuperación
					</button>
				</div>

				<div class="text-center">
					<a href="{{ route('login') }}" class="small text-muted back-link text-decoration-none">
						<i class="bi bi-arrow-left me-1"></i>Regresar al login
					</a>
				</div>

			</form>

		</div>
	</div>

	<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
	<script>
		document.getElementById('forgotPasswordForm').addEventListener('submit', function (e) {
			const emailInput = document.getElementById('email');
			const emailError = document.getElementById('emailError');
			const submitBtn = document.getElementById('submitBtn');
			const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

			let isValid = true;

			if (!emailInput.value.trim() || !emailPattern.test(emailInput.value.trim())) {
				emailInput.classList.add('is-invalid');
				isValid = false;
			} else {
				emailInput.classList.remove('is-invalid');
			}

			if (!isValid) {
				e.preventDefault();
				return;
			}

			// Estado de carga mientras se envía al servidor
			submitBtn.classList.add('is-loading');
			submitBtn.disabled = true;
		});

		// Quitar el estado de error al empezar a corregir
		document.getElementById('email').addEventListener('input', function () {
			this.classList.remove('is-invalid');
		});
	</script>

</body>

</html>