<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="UTF-8">
	<title>Dashboard Admin — MedSchedule</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="bg-light p-4">
	<div class="container-fluid">
		<h4 class="mb-4">Dashboard Administrador</h4>

		<!-- TARJETAS -->
		<div class="row g-3 mb-4" id="stats"></div>

		<!-- GRAFICA -->
		<div class="row g-3 mb-4">
			<div class="col-md-6">
				<div class="card border-0 shadow-sm">
					<div class="card-body">
						<h6 class="fw-bold mb-3">Usuarios registrados por fecha</h6>
						<canvas id="usersChart"></canvas>
					</div>
				</div>
			</div>
			<div class="col-md-6">
				<div class="card border-0 shadow-sm">
					<div class="card-body">
						<h6 class="fw-bold mb-3">Citas por estado</h6>
						<canvas id="appointmentsChart"></canvas>
					</div>
				</div>
			</div>
		</div>

		<!-- ACTIVIDAD RECIENTE -->
		<div class="card border-0 shadow-sm">
			<div class="card-body">
				<h6 class="fw-bold mb-3">Actividad Reciente</h6>
				<table class="table table-sm" style="font-size:13px">
					<thead class="table-light">
						<tr>
							<th>Usuario</th>
							<th>Acción</th>
							<th>Descripción</th>
							<th>Fecha</th>
						</tr>
					</thead>
					<tbody id="activityTable"></tbody>
				</table>
			</div>
		</div>
	</div>

	<script>
		async function loadDashboard() {
			const res = await fetch('/admin/dashboard/data');
			const data = await res.json();

			// Tarjetas
			document.getElementById('stats').innerHTML = `
        <div class="col-md-3">
            <div class="card text-white border-0 shadow-sm" style="background:#0d2137">
                <div class="card-body"><h6>Total Usuarios</h6><h2>${data.total_users}</h2></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white border-0 shadow-sm bg-success">
                <div class="card-body"><h6>Doctores</h6><h2>${data.total_doctors}</h2></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white border-0 shadow-sm bg-warning">
                <div class="card-body"><h6>Pacientes</h6><h2>${data.total_patients}</h2></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white border-0 shadow-sm bg-info">
                <div class="card-body"><h6>Citas Totales</h6><h2>${Object.values(data.appointments_by_status).reduce((a,b)=>a+b,0)}</h2></div>
            </div>
        </div>
    `;

			// Actividad reciente
			document.getElementById('activityTable').innerHTML = data.recent_activity.map(log => `
        <tr>
            <td>${log.user ? log.user.name + ' ' + log.user.last_name : 'Sistema'}</td>
            <td><span class="badge bg-primary">${log.action}</span></td>
            <td>${log.description}</td>
            <td class="text-muted">${log.created_at}</td>
        </tr>
    `).join('');
		}

		async function loadCharts() {
			// Usuarios chart
			const resUsers = await fetch('/admin/dashboard/users-chart');
			const usersData = await resUsers.json();
			new Chart(document.getElementById('usersChart'), {
				type: 'line',
				data: {
					labels: usersData.map(d => d.date),
					datasets: [{
						label: 'Usuarios registrados',
						data: usersData.map(d => d.total),
						borderColor: '#0d2137',
						tension: 0.3,
						fill: true,
						backgroundColor: 'rgba(13,33,55,0.1)'
					}]
				}
			});

			// Appointments chart
			const resApp = await fetch('/admin/dashboard/appointments-chart');
			const appData = await resApp.json();
			const statuses = [...new Set(appData.map(d => d.status))];
			const dates = [...new Set(appData.map(d => d.date))];
			const colors = {
				pending: '#ffc107',
				confirmed: '#0d6efd',
				completed: '#28a745',
				cancelled: '#dc3545'
			};
			new Chart(document.getElementById('appointmentsChart'), {
				type: 'bar',
				data: {
					labels: dates,
					datasets: statuses.map(status => ({
						label: status,
						data: dates.map(date => {
							const found = appData.find(d => d.date === date && d.status === status);
							return found ? found.total : 0;
						}),
						backgroundColor: colors[status] || '#6c757d'
					}))
				},
				options: {
					scales: {
						x: {
							stacked: true
						},
						y: {
							stacked: true
						}
					}
				}
			});
		}

		loadDashboard();
		loadCharts();
	</script>
</body>

</html>