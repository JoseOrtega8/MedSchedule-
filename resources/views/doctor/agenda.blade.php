<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="csrf-token" content="{{ csrf_token() }}"/>
  <title>MedSchedule - Mi Agenda</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <script>
    window.agendaDataUrl = "{{ route('doctor.agenda.data') }}";
    window.appointmentUpdateBase = "{{ url('/appointments') }}";
    window.userMenuRoutes = {
      profile: "{{ route('doctor.profile') }}",
    };
  </script>
  @vite(['resources/css/app.css', 'resources/css/agenda.css', 'resources/js/topbar-date.js', 'resources/js/agenda.js'])
</head>
<body>

<!-- Overlay para móvil -->
<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

<!-- Botón hamburguesa móvil -->
<button class="mobile-toggle" onclick="toggleSidebar()">
  <i class="bi bi-list" style="font-size: 24px;"></i>
</button>

<div class="app-wrapper">
  <x-sidebar active="doctor-agenda" variant="doctor" />

  <!-- CONTENT WRAPPER -->
  <div class="content-wrapper">
    <x-topbar
      title="Mi Agenda"
      icon="bi bi-calendar-week"
      subtitle="Doctor / Agenda del dia"
      :show-avatar-menu="true"
      badge-text="doctor"
      badge-tone="success"
      avatar-text="MG"
      avatar-color="#28a745"
    />

    <!-- AGENDA CONTENT -->
    <div class="dashboard-content p-4 agenda-shell">

      <!-- Stat Cards -->
      <div class="row g-3 mb-4" id="agendaStats">
        <div class="col-lg-3 col-md-6">
          <div class="stat-card blue animate-card fade-in-visible agenda-stat">
            <div class="stat-label">Total</div>
            <div class="stat-number" id="statCitasHoy">--</div>
          </div>
        </div>
        <div class="col-lg-3 col-md-6">
          <div class="stat-card green animate-card fade-in-visible agenda-stat">
            <div class="stat-label">Confirmed</div>
            <div class="stat-number" id="statConfirmadas">--</div>
          </div>
        </div>
        <div class="col-lg-3 col-md-6">
          <div class="stat-card orange animate-card fade-in-visible agenda-stat">
            <div class="stat-label">Pending</div>
            <div class="stat-number" id="statPendientes">--</div>
          </div>
        </div>
        <div class="col-lg-3 col-md-6">
          <div class="stat-card red animate-card fade-in-visible agenda-stat">
            <div class="stat-label">Cancelled</div>
            <div class="stat-number" id="statCanceladas">--</div>
          </div>
        </div>
      </div>

      <!-- Selector de días -->
      <div class="card border-0 shadow-sm mb-4 agenda-week-panel">
        <div class="card-body py-3">
          <div class="agenda-week-toolbar mb-3">
            <div class="agenda-date-picker-group">
              <label class="agenda-date-label" for="agendaDatePicker">Fecha específica</label>
              <input class="form-control agenda-date-input" id="agendaDatePicker" type="date"/>
            </div>
          </div>

          <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <button class="btn btn-outline-secondary btn-sm rounded-circle week-nav-btn" id="btnPrevWeek" type="button" aria-label="Semana anterior">
              <i class="bi bi-chevron-left"></i>
            </button>

            <div class="d-flex gap-2 flex-wrap justify-content-center agenda-days" id="weekDays">
              <!-- Los días se generan dinámicamente con JS -->
            </div>

            <button class="btn btn-outline-secondary btn-sm rounded-circle week-nav-btn" id="btnNextWeek" type="button" aria-label="Semana siguiente">
              <i class="bi bi-chevron-right"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Lista de citas -->
      <div class="card border-0 shadow-sm agenda-list-panel">
        <div class="card-body p-4">
          <h6 class="fw-bold mb-4" id="agendaTitle">
            <i class="bi bi-list-check me-2 text-muted"></i>
            Citas - <span id="agendaDayLabel">Cargando...</span>
          </h6>

          <div id="appointmentsList">
            <div class="text-center py-5 text-muted">Cargando agenda...</div>
          </div>

        </div>
      </div>

    </div>
  </div>
</div>

<div class="agenda-toast-container" id="agendaToastContainer" aria-live="polite" aria-atomic="true"></div>

<div class="agenda-modal-backdrop" id="agendaHistoryBackdrop"></div>
<div class="agenda-modal" id="agendaHistoryModal" role="dialog" aria-modal="true" aria-labelledby="agendaHistoryTitle">
  <div class="agenda-modal-card">
    <div class="agenda-modal-header">
      <h6 class="mb-0" id="agendaHistoryTitle">appointment_history</h6>
      <button class="agenda-modal-close" id="closeAgendaHistoryModal" type="button" aria-label="Cerrar historial">&times;</button>
    </div>
    <div class="agenda-modal-body" id="agendaHistoryContent">
      <div class="text-muted">Cargando historial...</div>
    </div>
  </div>
</div>

</body>
</html>
