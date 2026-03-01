<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>MedSchedule - Dashboard</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <script>
    window.dashboardDataUrl = "{{ route('dashboard.data') }}";
  </script>
  @vite(['resources/css/app.css', 'resources/js/about.js', 'resources/js/topbar-date.js', 'resources/js/dashboard.js'])
</head>
<body>

<!-- Overlay para móvil -->
<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

<!-- Botón hamburguesa móvil -->
<button class="mobile-toggle" onclick="toggleSidebar()">
  <i class="bi bi-list" style="font-size: 24px;"></i>
</button>

<div class="app-wrapper">
  <!-- SIDEBAR -->
  <x-sidebar active="dashboard" />

  <!-- CONTENT WRAPPER -->
  <div class="content-wrapper">

    <!-- TOPBAR -->
    <x-topbar
      title="Dashboard"
      icon="bi bi-speedometer2"
      date-text="Miércoles, 12 Feb 2026"
      :show-avatar-menu="true"
    />

    <!-- DASHBOARD CONTENT -->
    <div class="dashboard-content p-4">

      <!-- Stat Cards -->
      <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-6">
          <div class="stat-card blue animate-card" data-card-index="0">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="stat-label">TOTAL USUARIOS</div>
                <div class="stat-number" id="statTotalUsers">--</div>
                <div class="stat-detail" id="statTotalUsersDetail">Cargando...</div>
              </div>
              <div class="stat-icon">
                <i class="bi bi-people"></i>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-3 col-md-6">
          <div class="stat-card teal animate-card" data-card-index="1">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="stat-label">CITAS HOY</div>
                <div class="stat-number" id="statAppointmentsToday">--</div>
                <div class="stat-detail" id="statAppointmentsTodayDetail">Cargando...</div>
              </div>
              <div class="stat-icon">
                <i class="bi bi-calendar-check"></i>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-3 col-md-6">
          <div class="stat-card green animate-card" data-card-index="2">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="stat-label">DOCTORES ACTIVOS</div>
                <div class="stat-number" id="statActiveDoctors">--</div>
                <div class="stat-detail" id="statActiveDoctorsDetail">Cargando...</div>
              </div>
              <div class="stat-icon">
                <i class="bi bi-person-badge"></i>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-3 col-md-6">
          <div class="stat-card orange animate-card" data-card-index="3">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="stat-label">CITAS ESTE MES</div>
                <div class="stat-number" id="statAppointmentsMonth">--</div>
                <div class="stat-detail" id="statAppointmentsMonthDetail">Cargando...</div>
              </div>
              <div class="stat-icon">
                <i class="bi bi-graph-up-arrow"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Citas Recientes + Logs -->
      <div class="row g-3">

        <!-- Citas Recientes -->
        <div class="col-lg-8">
          <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-calendar-check me-2 text-primary"></i>Citas recientes</h6>
                <button class="btn btn-outline-primary btn-sm" type="button" id="btnRefreshDashboard">Actualizar</button>
              </div>
              <div class="table-responsive">
                <table class="table custom-table mb-0">
                  <thead>
                    <tr>
                      <th>Paciente</th>
                      <th>Doctor</th>
                      <th>Fecha</th>
                      <th>Estado</th>
                    </tr>
                  </thead>
                  <tbody id="recentAppointmentsBody">
                    <tr>
                      <td colspan="4" class="text-center text-muted py-4">Cargando citas...</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div class="d-flex justify-content-between align-items-center mt-3">
                <small class="text-muted" id="appointmentsPageInfo">Cargando...</small>
                <nav aria-label="Paginación de citas recientes">
                  <ul class="pagination pagination-sm mb-0" id="appointmentsPagination"></ul>
                </nav>
              </div>
            </div>
          </div>
        </div>

        <!-- Logs Recientes -->
        <div class="col-lg-4">
          <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
              <h6 class="fw-bold mb-3"><i class="bi bi-journal-text me-2 text-primary"></i>Logs recientes</h6>

              <div class="log-list" id="activityLogs">
                <div class="text-muted small">Cargando logs...</div>
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
