<!DOCTYPE html>
<html lang="es">

<head>

	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">

	<title>MedSchedule</title>

	<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">

	<style>
		.hero {
			height: 100vh;
			background: linear-gradient(135deg, #1976d2, #0d47a1);
			color: white;
			display: flex;
			align-items: center;
		}

		.hero h1 {
			font-weight: 700;
		}
	</style>

</head>

<body>

	<nav class="navbar navbar-light bg-white shadow-sm">
		<div class="container">
			<span class="fw-bold">MedSchedule</span>

			<a href="{{ route('login') }}" class="btn btn-primary">
				Iniciar sesión
			</a>
		</div>
	</nav>

	<section class="hero">
		<div class="container">

			<div class="row align-items-center">

				<div class="col-lg-6">
					<h1>Gestión moderna de citas médicas</h1>

					<p class="mt-3">
						Administra pacientes, doctores y citas desde un solo panel.
					</p>

					<a href="{{ route('login') }}" class="btn btn-light btn-lg mt-3">
						Entrar al sistema
					</a>
				</div>

				<div class="col-lg-6 text-center">
					<img src="/img/doctor.svg" style="max-width:400px">
				</div>

			</div>

		</div>
	</section>

</body>

</html>