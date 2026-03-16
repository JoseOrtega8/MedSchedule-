<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">

	<title>Login - MedSchedule</title>

	<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">

	<style>
		body {
			background: linear-gradient(135deg, #1e3c72, #2a5298);
			height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
		}

		.login-card {
			width: 100%;
			max-width: 420px;
			border-radius: 12px;
		}

		.brand {
			font-weight: 700;
			font-size: 22px;
		}
	</style>

</head>

<body>

	<div class="card login-card shadow-lg">
		<div class="card-body p-4">

			<div class="text-center mb-4">
				<div class="brand text-primary">MedSchedule</div>
				<p class="text-muted small">Panel de administración</p>
			</div>

			<form method="POST" action="{{ route('login') }}">
				@csrf

				<div class="mb-3">
					<label class="form-label">Correo</label>
					<input type="email" name="email" class="form-control" required>
				</div>

				<div class="mb-3">
					<label class="form-label">Contraseña</label>
					<input type="password" name="password" class="form-control" required>
				</div>

				<div class="d-grid">
					<button class="btn btn-primary">
						Iniciar sesión
					</button>
				</div>

			</form>

		</div>
	</div>

</body>

</html>