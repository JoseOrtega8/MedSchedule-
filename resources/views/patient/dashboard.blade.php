<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="UTF-8">
	<title>Dashboard Paciente — MedSchedule</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light p-4">
	<div class="container-fluid">
		<h4 class="mb-4">Dashboard Paciente</h4>

		<!-- TARJETAS -->
		<div class="row g-3 mb-4" id="stats"></div>

		<!-- PROXIMAS CITAS -->
		<div class="card border-0 shadow-sm">
			<div class="card-body">
				<h6 class="fw-bold mb-3">Próximas Citas</h6>
				<table class="table table-sm" style="font-size:13px">
					<thead class="table-light">
						<tr>
							<th>appointment_date</th>
							<th>start_time</th>
							<th>end_time</th>
							<th>reason</th>
							<th>status</th>
						</tr>
					</thead>
					<tbody id="proximasCitas"></tbody>
				</table>
			</div>
		</div>
	</div>

	<script>
		async function loadDashboard() {
			const res = await fetch('/patient/dashboard/data');
			const data = await res.json();

			const perfil = data.perfil;

			document.getElementById('stats').innerHTML = `
        <div class="col-md-3">
            <div class="card text-white border-0 shadow-sm" style="background:#0d2137">
                <div class="card-body">
                    <h6>Bienvenido</h6>
                    <div class="fw-bold">${perfil ? 'blood_type: ' + perfil.blood_type : 'Sin perfil'}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white border-0 shadow-sm bg-warning">
                <div class="card-body"><h6>Pendientes</h6><h2>${data.citas_por_status.pending ?? 0}</h2></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white border-0 shadow-sm bg-success">
                <div class="card-body"><h6>Confirmadas</h6><h2>${data.citas_por_status.confirmed ?? 0}</h2></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white border-0 shadow-sm bg-secondary">
                <div class="card-body"><h6>Completadas</h6><h2>${data.citas_por_status.completed ?? 0}</h2></div>
            </div>
        </div>
    `;

			document.getElementById('proximasCitas').innerHTML = data.proximas_citas.length ? data.proximas_citas.map(c => `
        <tr>
            <td>${c.appointment_date}</td>
            <td>${c.start_time}</td>
            <td>${c.end_time}</td>
            <td>${c.reason}</td>
            <td><span class="badge bg-primary">${c.status}</span></td>
        </tr>
    `).join('') : '<tr><td colspan="5" class="text-center text-muted">No tienes citas próximas</td></tr>';
		}

		loadDashboard();
	</script>
</body>

</html>