<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="csrf-token" content="{{ csrf_token() }}"/>
  <title>MedSchedule - Gestion de Especialidades</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <script>
    window.specialtiesDataUrl = "{{ route('admin.specialties.data') }}";
    window.specialtiesBaseUrl = "{{ url('/admin/especialidades') }}";
  </script>
  @vite([
    'resources/css/app.css',
    'resources/css/especialidades.css',
    'resources/js/topbar-date.js',
    'resources/js/especialidades.js',
  ])
</head>
<body>

<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

<button class="mobile-toggle" onclick="toggleSidebar()">
  <i class="bi bi-list" style="font-size: 24px;"></i>
</button>

<div class="app-wrapper">
  <x-sidebar active="admin-specialties" variant="admin" />

  <div class="content-wrapper">
    <x-topbar
      title="Gestion de Especialidades"
      icon="bi bi-heart-pulse"
      subtitle="Admin / Especialidades"
      :show-avatar-menu="true"
      badge-text="admin"
      badge-tone="danger"
      avatar-text="JC"
      avatar-color="#1976d2"
    />

    <div class="dashboard-content p-4 specialties-shell">
      <div class="row g-4 align-items-start">
        <div class="col-xl-7">
          <div class="card border-0 shadow-sm specialties-panel">
            <div class="card-body p-4">
              <div class="specialties-panel-header">
                <h6 class="specialties-title mb-0">
                  <i class="bi bi-list-ul"></i>
                  <span>Especialidades</span>
                </h6>

                <button class="btn btn-primary btn-sm specialties-new-btn" id="btnNewSpecialty" type="button">
                  <i class="bi bi-plus-lg"></i>
                  Nueva
                </button>
              </div>

              <div class="specialties-list" id="specialtiesList" data-testid="specialties-list">
                <div class="text-center py-5 text-muted">Cargando especialidades...</div>
              </div>

              <p class="specialties-note mb-0">
                <i class="bi bi-info-circle"></i>
                Solo se puede eliminar si no tiene doctores asignados
              </p>
            </div>
          </div>
        </div>

        <div class="col-xl-5">
          <div class="card border-0 shadow-sm specialty-form-panel">
            <div class="card-body p-4">
              <h6 class="specialties-title mb-4" id="specialtyFormHeading">
                <i class="bi bi-plus-circle"></i>
                <span>Nueva Especialidad</span>
              </h6>

              <div class="alert d-none specialty-feedback" id="specialtyFeedback" role="alert"></div>

              <form id="specialtyForm" data-testid="specialty-form">
                <div class="mb-3">
                  <label class="form-label specialty-label" for="specialtyName">name <span>(unico)</span></label>
                  <input
                    class="form-control specialty-input"
                    id="specialtyName"
                    name="name"
                    type="text"
                    placeholder="Ej. Dermatologia"
                    maxlength="80"
                    required
                  />
                  <div class="invalid-feedback" id="specialtyNameFeedback">
                    Ya existe una especialidad con ese nombre.
                  </div>
                </div>

                <div class="mb-3">
                  <label class="form-label specialty-label" for="specialtyDescription">description</label>
                  <textarea
                    class="form-control specialty-input specialty-textarea"
                    id="specialtyDescription"
                    name="description"
                    rows="3"
                    placeholder="Descripcion de la especialidad..."
                    required
                  ></textarea>
                </div>

                <div class="mb-4">
                  <label class="form-label specialty-label" for="specialtyStatusToggle">status</label>
                  <div class="specialty-status-toggle">
                    <div class="form-check form-switch mb-0">
                      <input class="form-check-input" id="specialtyStatusToggle" name="statusToggle" type="checkbox" checked />
                      <label class="form-check-label specialty-status-label" for="specialtyStatusToggle" id="specialtyStatusLabel">
                        1 - activa
                      </label>
                    </div>
                  </div>
                </div>

                <div class="d-grid gap-2">
                  <button class="btn btn-primary specialty-submit-btn" id="saveSpecialtyBtn" type="submit">
                    Guardar Especialidad
                  </button>
                  <button class="btn btn-outline-secondary d-none" id="cancelEditBtn" type="button">
                    Cancelar edicion
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

</body>
</html>
