<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>MedSchedule - Gestion de Roles y Permisos</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <script>
    window.adminRbacDataUrl = "{{ route('admin.rbac.data') }}";
  </script>
  @vite([
    'resources/css/app.css',
    'resources/css/admin-rbac.css',
    'resources/js/topbar-date.js',
    'resources/js/admin-rbac.js',
  ])
</head>
<body>

<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

<button class="mobile-toggle" onclick="toggleSidebar()">
  <i class="bi bi-list" style="font-size: 24px;"></i>
</button>

<div class="app-wrapper">
  <x-sidebar active="admin-rbac" variant="admin" />

  <div class="content-wrapper">
    <x-topbar
      title="Gestion de Roles y Permisos"
      icon="bi bi-shield-lock"
      subtitle="Admin / Seguridad / RBAC"
      :show-avatar-menu="true"
      badge-text="admin"
      badge-tone="danger"
      avatar-text="A"
      avatar-color="#1976d2"
    />

    <div class="dashboard-content p-4 admin-rbac-shell">
      <div class="alert d-none admin-rbac-feedback" id="adminRbacFeedback" role="alert"></div>

      <div class="card border-0 shadow-sm rbac-users-panel mb-4" data-testid="admin-rbac-users">
        <div class="card-body p-4">
          <div class="rbac-panel-header">
            <h6 class="rbac-panel-title mb-0">
              <i class="bi bi-people"></i>
              <span>Usuarios del Sistema</span>
            </h6>

            <button class="btn btn-primary rbac-primary-btn" id="btnOpenNewUserModal" type="button">
              <i class="bi bi-person-plus"></i>
              Nuevo Usuario
            </button>
          </div>

          <div class="table-responsive">
            <table class="table rbac-users-table mb-0">
              <thead>
                <tr>
                  <th>Usuario</th>
                  <th>Correo</th>
                  <th>Rol actual</th>
                  <th>Estado</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody id="rbacUsersTableBody">
                <tr>
                  <td colspan="5" class="text-center text-muted py-4">Cargando usuarios...</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="row g-4 align-items-start">
        <div class="col-xl-7">
          <div class="card border-0 shadow-sm rbac-roles-panel" data-testid="admin-rbac-roles">
            <div class="card-body p-4">
              <h6 class="rbac-panel-title mb-4">
                <i class="bi bi-diagram-3"></i>
                <span>Roles y sus Permisos</span>
              </h6>

              <div class="rbac-roles-list" id="rbacRolesList">
                <div class="text-center text-muted py-4">Cargando roles...</div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-5">
          <div class="card border-0 shadow-sm rbac-permissions-panel" data-testid="admin-rbac-permissions">
            <div class="card-body p-4">
              <div class="rbac-panel-header">
                <h6 class="rbac-panel-title mb-0">
                  <i class="bi bi-key"></i>
                  <span>Permisos del Sistema</span>
                </h6>

                <button class="btn btn-primary btn-sm rbac-primary-btn" id="btnOpenNewPermissionModal" type="button">
                  <i class="bi bi-plus-lg"></i>
                  Nuevo
                </button>
              </div>

              <div class="rbac-permissions-list" id="rbacPermissionsList">
                <div class="text-center text-muted py-4">Cargando permisos...</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="rbac-modal-backdrop" id="rbacModalBackdrop"></div>

<div class="rbac-modal" id="rbacUserModal" aria-hidden="true">
  <div class="rbac-modal-card">
    <div class="rbac-modal-header">
      <h6 class="mb-0">Nuevo Usuario</h6>
      <button class="rbac-modal-close" type="button" data-close-modal="rbacUserModal">&times;</button>
    </div>
    <form id="rbacUserForm">
      <div class="mb-3">
        <label class="form-label rbac-label" for="rbacUserName">Nombre</label>
        <input class="form-control rbac-input" id="rbacUserName" name="name" type="text" required />
      </div>
      <div class="mb-3">
        <label class="form-label rbac-label" for="rbacUserEmail">Correo</label>
        <input class="form-control rbac-input" id="rbacUserEmail" name="email" type="email" required />
      </div>
      <div class="mb-3">
        <label class="form-label rbac-label" for="rbacUserRole">Rol</label>
        <select class="form-select rbac-input" id="rbacUserRole" name="roleId"></select>
      </div>
      <div class="mb-4">
        <label class="form-label rbac-label" for="rbacUserStatus">Estado</label>
        <select class="form-select rbac-input" id="rbacUserStatus" name="status">
          <option value="activo">activo</option>
          <option value="inactivo">inactivo</option>
        </select>
      </div>
      <div class="rbac-modal-actions">
        <button class="btn btn-outline-secondary" type="button" data-close-modal="rbacUserModal">Cancelar</button>
        <button class="btn btn-primary" type="submit">Guardar Usuario</button>
      </div>
    </form>
  </div>
</div>

<div class="rbac-modal" id="rbacRoleModal" aria-hidden="true">
  <div class="rbac-modal-card">
    <div class="rbac-modal-header">
      <h6 class="mb-0">Cambiar Rol</h6>
      <button class="rbac-modal-close" type="button" data-close-modal="rbacRoleModal">&times;</button>
    </div>
    <form id="rbacRoleForm">
      <input id="rbacRoleUserId" name="userId" type="hidden" />
      <div class="mb-3">
        <label class="form-label rbac-label" for="rbacRoleUserName">Usuario</label>
        <input class="form-control rbac-input" id="rbacRoleUserName" type="text" readonly />
      </div>
      <div class="mb-4">
        <label class="form-label rbac-label" for="rbacRoleSelect">Rol nuevo</label>
        <select class="form-select rbac-input" id="rbacRoleSelect" name="roleId"></select>
      </div>
      <div class="rbac-modal-actions">
        <button class="btn btn-outline-secondary" type="button" data-close-modal="rbacRoleModal">Cancelar</button>
        <button class="btn btn-primary" type="submit">Aplicar Cambio</button>
      </div>
    </form>
  </div>
</div>

<div class="rbac-modal" id="rbacPermissionModal" aria-hidden="true">
  <div class="rbac-modal-card">
    <div class="rbac-modal-header">
      <h6 class="mb-0">Nuevo Permiso</h6>
      <button class="rbac-modal-close" type="button" data-close-modal="rbacPermissionModal">&times;</button>
    </div>
    <form id="rbacPermissionForm">
      <div class="mb-3">
        <label class="form-label rbac-label" for="rbacPermissionKey">Clave</label>
        <input class="form-control rbac-input" id="rbacPermissionKey" name="key" type="text" placeholder="ej. exportar_reportes" required />
      </div>
      <div class="mb-3">
        <label class="form-label rbac-label" for="rbacPermissionDescription">Descripcion</label>
        <textarea class="form-control rbac-input rbac-textarea" id="rbacPermissionDescription" name="description" rows="3" required></textarea>
      </div>
      <div class="form-check form-switch mb-4">
        <input class="form-check-input" id="rbacPermissionEnabled" name="enabled" type="checkbox" checked />
        <label class="form-check-label" for="rbacPermissionEnabled">Permiso habilitado</label>
      </div>
      <div class="rbac-modal-actions">
        <button class="btn btn-outline-secondary" type="button" data-close-modal="rbacPermissionModal">Cancelar</button>
        <button class="btn btn-primary" type="submit">Crear Permiso</button>
      </div>
    </form>
  </div>
</div>

<div class="rbac-modal" id="rbacAssignPermissionModal" aria-hidden="true">
  <div class="rbac-modal-card">
    <div class="rbac-modal-header">
      <h6 class="mb-0">Asignar Permiso</h6>
      <button class="rbac-modal-close" type="button" data-close-modal="rbacAssignPermissionModal">&times;</button>
    </div>
    <form id="rbacAssignPermissionForm">
      <input id="rbacAssignRoleId" name="roleId" type="hidden" />
      <div class="mb-3">
        <label class="form-label rbac-label" for="rbacAssignRoleName">Rol</label>
        <input class="form-control rbac-input" id="rbacAssignRoleName" type="text" readonly />
      </div>
      <div class="mb-4">
        <label class="form-label rbac-label" for="rbacAssignPermissionSelect">Permiso</label>
        <select class="form-select rbac-input" id="rbacAssignPermissionSelect" name="permissionId"></select>
      </div>
      <div class="rbac-modal-actions">
        <button class="btn btn-outline-secondary" type="button" data-close-modal="rbacAssignPermissionModal">Cancelar</button>
        <button class="btn btn-primary" type="submit">Asignar Permiso</button>
      </div>
    </form>
  </div>
</div>

</body>
</html>
