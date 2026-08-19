<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="csrf-token" content="{{ csrf_token() }}">

	<title>Restablecer contraseña - MedSchedule</title>

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

		.toggle-password {
			cursor: pointer;
		}

		.password-strength {
			height: 4px;
			border-radius: 2px;
			background: #e9ecef;
			overflow: hidden;
			margin-top: 6px;
		}

		.password-strength-bar {
			height: 100%;
			width: 0%;
			transition: width .25s ease, background-color .25s ease;
		}
	</style>
</head>

<body>

	<div class="card login-card shadow-lg">
		<div class="card-body p-4">

			<div class="text-center mb-4">
				<div class="brand text-primary">MedSchedule</div>
				<p class="text-muted small mb-0">Establecer nueva contraseña</p>
			</div>

			@if ($errors->any())
				<div class="alert alert-danger py-2 small" role="alert">
					<i class="bi bi-exclamation-triangle me-1"></i>{{ $errors->first() }}
				</div>
			@endif

			<form method="POST" action="{{ route('password.update') }}" id="resetPasswordForm" novalidate>
				@csrf

				{{-- Token y correo requeridos por el flujo de reset de Breeze --}}
				<input type="hidden" name="token" value="{{ $request->route('token') }}">

				<div class="mb-3">
					<label for="email" class="form-label">Correo</label>
					<input
						type="email"
						id="email"
						name="email"
						class="form-control"
						value="{{ old('email', $request->email) }}"
						required
						autofocus
						readonly>
				</div>

				<div class="mb-3">
					<label for="password" class="form-label">Nueva contraseña</label>
					<div class="input-group">
						<input
							type="password"
							id="password"
							name="password"
							class="form-control"
							required
							minlength="8"
							placeholder="Mínimo 8 caracteres">
						<span class="input-group-text toggle-password" data-target="password">
							<i class="bi bi-eye"></i>
						</span>
					</div>
					<div class="password-strength">
						<div class="password-strength-bar" id="strengthBar"></div>
					</div>
					<div class="invalid-feedback" id="passwordError">
						La contraseña debe tener al menos 8 caracteres.
					</div>
				</div>

				<div class="mb-3">
					<label for="password_confirmation" class="form-label">Confirmar contraseña</label>
					<div class="input-group">
						<input
							type="password"
							id="password_confirmation"
							name="password_confirmation"
							class="form-control"
							required
							placeholder="Repite tu nueva contraseña">
						<span class="input-group-text toggle-password" data-target="password_confirmation">
							<i class="bi bi-eye"></i>
						</span>
					</div>
					<div class="invalid-feedback" id="confirmError">
						Las contraseñas no coinciden.
					</div>
				</div>

				<div class="d-grid mb-3">
					<button class="btn btn-primary" type="submit" id="submitBtn">
						<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
						Restablecer contraseña
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
		// Mostrar/ocultar contraseña
		document.querySelectorAll('.toggle-password').forEach(function (icon) {
			icon.addEventListener('click', function () {
				const targetId = this.getAttribute('data-target');
				const input = document.getElementById(targetId);
				const isPassword = input.type === 'password';
				input.type = isPassword ? 'text' : 'password';
				this.querySelector('i').classList.toggle('bi-eye');
				this.querySelector('i').classList.toggle('bi-eye-slash');
			});
		});

		// Barra simple de fuerza de contraseña (visual, no bloquea el envío)
		const passwordInput = document.getElementById('password');
		const strengthBar = document.getElementById('strengthBar');

		passwordInput.addEventListener('input', function () {
			const val = this.value;
			let score = 0;
			if (val.length >= 8) score++;
			if (/[A-Z]/.test(val)) score++;
			if (/[0-9]/.test(val)) score++;
			if (/[^A-Za-z0-9]/.test(val)) score++;

			const percent = (score / 4) * 100;
			const colors = ['#dc3545', '#fd7e14', '#ffc107', '#198754'];
			strengthBar.style.width = percent + '%';
			strengthBar.style.backgroundColor = colors[Math.max(score - 1, 0)];

			this.classList.remove('is-invalid');
		});

		document.getElementById('resetPasswordForm').addEventListener('submit', function (e) {
			const password = document.getElementById('password');
			const confirm = document.getElementById('password_confirmation');
			const submitBtn = document.getElementById('submitBtn');

			let isValid = true;

			if (password.value.trim().length < 8) {
				password.classList.add('is-invalid');
				isValid = false;
			} else {
				password.classList.remove('is-invalid');
			}

			if (confirm.value !== password.value || confirm.value.trim() === '') {
				confirm.classList.add('is-invalid');
				isValid = false;
			} else {
				confirm.classList.remove('is-invalid');
			}

			if (!isValid) {
				e.preventDefault();
				return;
			}

			submitBtn.classList.add('is-loading');
			submitBtn.disabled = true;
		});

		document.getElementById('password_confirmation').addEventListener('input', function () {
			this.classList.remove('is-invalid');
		});
	</script>

</body>

</html>