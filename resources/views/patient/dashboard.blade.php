<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MedSchedule - Dashboard Paciente</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
    <script>
        window.dashboardDataUrl = "{{ route('patient.dashboard.data') }}";
    </script>
    @vite([
        'resources/css/app.css',
        'resources/js/about.js',
        'resources/js/topbar-date.js',
        'resources/js/patient-dashboard.js'
    ])
</head>

<body>
    <div class="overlay" id="overlay" onclick="toggleSidebar()"></div>
    <button class="mobile-toggle" onclick="toggleSidebar()">
        <i class="bi bi-list" style="font-size: 24px;"></i>
    </button>

    <div class="app-wrapper">
        <x-sidebar active="patient-dashboard" variant="patient" />

        <div class="content-wrapper">
            <x-topbar
                title="Dashboard"
                icon="bi bi-speedometer2"
                subtitle="Paciente / Dashboard"
                :show-avatar-menu="true"
                badge-text="paciente"
                badge-tone="success"
                avatar-text="{{ strtoupper(substr(Auth::user()->name,0,2)) }}"
                avatar-color="#0d6efd" />

            <div class="dashboard-content p-4 dashboard-patient-shell">

                <div class="row g-3 mb-4">
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-card orange animate-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-label">Pendientes</div>
                                    <div class="stat-number" id="statPending">--</div>
                                    <div class="stat-detail">Citas pendientes</div>
                                </div>
                                <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-card green animate-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-label">Confirmadas</div>
                                    <div class="stat-number" id="statConfirmed">--</div>
                                    <div class="stat-detail">Citas confirmadas</div>
                                </div>
                                <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-card blue animate-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-label">Completadas</div>
                                    <div class="stat-number" id="statCompleted">--</div>
                                    <div class="stat-detail">Historial de citas</div>
                                </div>
                                <div class="stat-icon"><i class="bi bi-clipboard-check"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <div class="stat-card orange animate-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-label">Canceladas</div>
                                    <div class="stat-number" id="statCancelled">--</div>
                                    <div class="stat-detail">Citas canceladas</div>
                                </div>
                                <div class="stat-icon"><i class="bi bi-x-circle"></i></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-4">
                                <h6 class="fw-bold mb-3"><i class="bi bi-search me-2 text-primary"></i>Buscar Doctores</h6>
                                <select id="specialtyFilter" class="form-select"></select>
                                <div id="doctorList" class="row mt-3"></div>
								

                                <h6 class="fw-bold mt-4 mb-3"><i class="bi bi-calendar-check me-2 text-primary"></i>Disponibilidad</h6>
								<div id="availabilityList" class="mt-3"></div>
                                <div id="doctorCalendar">Seleccione un doctor...</div>

                                <h6 class="fw-bold mt-4 mb-3"><i class="bi bi-plus-circle me-2 text-primary"></i>Agendar Cita</h6>
								<form id="appointmentForm">
									<input type="hidden" id="appointmentDoctor" name="doctor_id">
									<input type="hidden" id="appointmentSpecialty" name="specialty_id">

									<div class="mb-3">
										<label for="appointmentDate" class="form-label">Fecha</label>
										<input type="date" id="appointmentDate" name="date" class="form-control" required>
									</div>

									<div class="mb-3">
										<label for="appointmentTime" class="form-label">Hora</label>
										<input type="time" id="appointmentTime" name="time" class="form-control" required>
									</div>

									<div class="mb-3">
										<label for="appointmentReason" class="form-label">Motivo</label>
										<textarea id="appointmentReason" name="reason" class="form-control"></textarea>
									</div>

									<button type="button" class="btn btn-primary" onclick="agendarCita()">Agendar</button>
							</form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-4">
                                <h6 class="fw-bold mb-3"><i class="bi bi-calendar-event me-2 text-primary"></i>Mis Citas Próximas</h6>
                                <div class="table-responsive">
                                    <table class="table custom-table mb-0">
                                        <thead><tr><th>Fecha</th><th>Hora</th><th>Doctor</th><th>Estado</th><th>Acciones</th></tr></thead>
                                        <tbody id="proximasCitasBody"><tr><td colspan="5" class="text-center text-muted py-4">Cargando citas...</td></tr></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm mt-3">
                            <div class="card-body p-4">
                                <h6 class="fw-bold mb-3"><i class="bi bi-clipboard-data me-2 text-primary"></i>Historial de Citas</h6>
                                <div class="table-responsive">
                                    <table class="table custom-table mb-0">
                                        <thead><tr><th>Fecha</th><th>Doctor</th><th>Observaciones</th></tr></thead>
                                        <tbody id="appointmentHistory"><tr><td colspan="3" class="text-center text-muted py-4">Cargando historial...</td></tr></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</body>
</html>
