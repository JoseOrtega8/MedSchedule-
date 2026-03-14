<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="csrf-token" content="{{ csrf_token() }}"/>
  <title>MedSchedule - Mis Horarios</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <script>
    window.schedulesDataUrl = "{{ route('doctor.schedules.data') }}";
    window.schedulesBaseUrl = "{{ url('/doctor/horarios') }}";
    window.userMenuRoutes = {
      profile: "{{ route('doctor.profile') }}",
    };
  </script>
  @vite([
    'resources/css/app.css',
    'resources/css/horarios.css',
    'resources/js/topbar-date.js',
    'resources/js/horarios.js',
  ])
</head>
<body>

<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

<button class="mobile-toggle" onclick="toggleSidebar()">
  <i class="bi bi-list" style="font-size: 24px;"></i>
</button>

<div class="app-wrapper">
  <x-sidebar active="doctor-schedules" variant="doctor" />

  <div class="content-wrapper">
    <x-topbar
      title="Mis Horarios"
      icon="bi bi-clock"
      subtitle="Doctor / Horarios disponibles"
      :show-avatar-menu="true"
      badge-text="doctor"
      badge-tone="success"
      avatar-text="MG"
      avatar-color="#28a745"
    />

    <div class="dashboard-content p-4 schedules-shell">
      <div class="row g-4 align-items-start">
        <div class="col-xl-7">
          <div class="card border-0 shadow-sm schedules-panel">
            <div class="card-body p-4">
              <div class="schedules-panel-header">
                <h6 class="schedules-title mb-0">
                  <i class="bi bi-list-ul"></i>
                  <span>Horarios registrados</span>
                </h6>

                <input
                  class="form-control schedules-filter-input"
                  id="scheduleDateFilter"
                  type="date"
                  aria-label="Filtrar horarios por fecha"
                />
              </div>

              <div class="schedules-list" id="schedulesList" data-testid="doctor-schedules-list">
                <div class="text-center py-5 text-muted">Cargando horarios...</div>
              </div>

              <p class="schedules-note mb-0">
                <i class="bi bi-info-circle"></i>
                Los horarios con status blocked muestran acciones deshabilitadas porque ya tienen cita agendada
              </p>
            </div>
          </div>
        </div>

        <div class="col-xl-5">
          <div class="card border-0 shadow-sm schedule-form-panel">
            <div class="card-body p-4">
              <h6 class="schedules-title mb-4" id="scheduleFormHeading">
                <i class="bi bi-plus-circle"></i>
                <span>Agregar Horario</span>
              </h6>

              <div class="alert d-none schedule-feedback" id="scheduleFeedback" role="alert"></div>

              <form id="scheduleForm" data-testid="doctor-schedule-form">
                <div class="mb-3">
                  <label class="form-label schedule-label" for="scheduleDate">date</label>
                  <input class="form-control schedule-input" id="scheduleDate" name="date" type="date" required />
                </div>

                <div class="mb-3">
                  <label class="form-label schedule-label" for="scheduleStartTime">start_time</label>
                  <input class="form-control schedule-input" id="scheduleStartTime" name="start_time" type="time" step="1800" required />
                </div>

                <div class="mb-2">
                  <label class="form-label schedule-label" for="scheduleEndTime">
                    end_time <span>(calculado automaticamente)</span>
                  </label>
                  <input class="form-control schedule-input" id="scheduleEndTime" name="end_time" type="time" readonly />
                </div>

                <p class="schedule-help-text" id="scheduleDurationHint">start_time + consultation_duration (doctor_profiles)</p>

                <div class="d-grid gap-2">
                  <button class="btn btn-primary schedule-submit-btn" id="saveScheduleBtn" type="submit">
                    Guardar Horario
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="schedule-toast-container" id="scheduleToastContainer" aria-live="polite" aria-atomic="true"></div>

</body>
</html>
