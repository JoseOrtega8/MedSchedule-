<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>MedSchedule - Panel de Logs</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <script>
    window.adminLogsDataUrl = "{{ route('admin.logs.data') }}";
  </script>
  @vite([
    'resources/css/app.css',
    'resources/css/admin-logs.css',
    'resources/js/topbar-date.js',
    'resources/js/admin-logs.js',
  ])
</head>
<body>

<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

<button class="mobile-toggle" onclick="toggleSidebar()">
  <i class="bi bi-list" style="font-size: 24px;"></i>
</button>

<div class="app-wrapper">
  <x-sidebar active="admin-logs" variant="admin" />

  <div class="content-wrapper">
    <x-topbar
      title="Panel de Logs"
      icon="bi bi-journal-text"
      subtitle="Admin / Activity Logs"
      :show-avatar-menu="true"
      badge-text="admin"
      badge-tone="danger"
      avatar-text="JC"
      avatar-color="#1976d2"
    />

    <div class="dashboard-content p-4 admin-logs-shell">
      <div class="card border-0 shadow-sm logs-filters-panel mb-4" data-testid="admin-logs-filters">
        <div class="card-body p-3">
          <div class="row g-3 align-items-end">
            <div class="col-lg-2 col-md-6">
              <label class="form-label logs-filter-label" for="logActionFilter">Accion</label>
              <select class="form-select logs-filter-input" id="logActionFilter"></select>
            </div>
            <div class="col-lg-2 col-md-6">
              <label class="form-label logs-filter-label" for="logModelFilter">Modelo</label>
              <select class="form-select logs-filter-input" id="logModelFilter"></select>
            </div>
            <div class="col-lg-2 col-md-6">
              <label class="form-label logs-filter-label" for="logDateFrom">Desde</label>
              <input class="form-control logs-filter-input" id="logDateFrom" type="date" />
            </div>
            <div class="col-lg-2 col-md-6">
              <label class="form-label logs-filter-label" for="logDateTo">Hasta</label>
              <input class="form-control logs-filter-input" id="logDateTo" type="date" />
            </div>
            <div class="col-lg-4 col-md-12 d-flex justify-content-lg-end">
              <button class="btn btn-outline-secondary logs-clear-btn" id="clearLogFiltersBtn" type="button">
                <i class="bi bi-arrow-counterclockwise"></i>
                Limpiar filtros
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="card border-0 shadow-sm logs-table-panel" data-testid="admin-logs-table">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table logs-table mb-0">
              <thead>
                <tr>
                  <th>user_id</th>
                  <th>action</th>
                  <th>model_type</th>
                  <th>description</th>
                  <th>ip_address</th>
                  <th>user_agent</th>
                  <th>created_at</th>
                  <th class="text-center">Detalle</th>
                </tr>
              </thead>
              <tbody id="adminLogsTableBody">
                <tr>
                  <td colspan="8" class="text-center text-muted py-4">Cargando logs...</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="logs-table-footer">
            <span class="logs-page-info" id="adminLogsPageInfo">Cargando...</span>
            <nav aria-label="Paginacion de logs">
              <ul class="pagination pagination-sm mb-0" id="adminLogsPagination"></ul>
            </nav>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

</body>
</html>
