<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="UTF-8">
	<title>Dashboard Doctor — MedSchedule</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light p-4">
	<div class="container-fluid">
		<h4 class="mb-4">Dashboard Doctor</h4>

		<!-- TARJETAS -->
		<div class="row g-3 mb-4" id="stats"></div>

		<!-- CITAS HOY -->
		<div class="card border-0 shadow-sm">
			<div class="card-body">
				<h6 class="fw-bold mb-3">Citas de Hoy</h6>
				<table class="table table-sm" style="font-size:13px">
					<thead class="table-light">
						<tr>
							<th>Paciente</th>
							<th>start_time</th>
							<th>end_time</th>
							<th>reason</th>
							<th>status</th>
						</tr>
					</thead>
					<tbody id="citasHoy"></tbody>
				</table>
			</div>
		</div>
	</div>

	<script>
		async function loadDashboard() {
			const res = await fetch('/doctor/dashboard/data');
			const data = await res.json();

			document.getElementById('stats').innerHTML = `
        <div class="col-md-4">
            <div class="card text-white border-0 shadow-sm bg-success">
                <div class="card-body"><h6>Citas Hoy</h6><h2>${data.citas_hoy.length}</h2></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white border-0 shadow-sm" style="background:#0d2137">
                <div class="card-body"><h6>Pacientes Atendidos</h6><h2>${data.total_pacientes_atendidos}</h2></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white border-0 shadow-sm bg-warning">
                <div class="card-body"><h6>Citas Pendientes</h6><h2>${data.citas_por_status.pending ?? 0}</h2></div>
            </div>
        </div>
    `;

			document.getElementById('citasHoy').innerHTML = data.citas_hoy.length ? data.citas_hoy.map(c => `
        <tr>
            <td>Paciente #${c.patient_id}</td>
            <td>${c.start_time}</td>
            <td>${c.end_time}</td>
            <td>${c.reason}</td>
            <td><span class="badge bg-primary">${c.status}</span></td>
        </tr>
    `).join('') : '<tr><td colspan="5" class="text-center text-muted">No hay citas para hoy</td></tr>';
		}

		loadDashboard();
	</script>
</body>

</html>