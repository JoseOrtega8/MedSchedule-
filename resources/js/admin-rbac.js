/**
 * MedSchedule - Gestion de Roles y Permisos (Admin Panel)
 * Uses temporary mock data and local persistence until a real backend exists.
 */

const adminRbacState = {
    users: [],
    roles: [],
    permissions: [],
};

const ADMIN_RBAC_STORAGE_KEY = "medschedule.admin.rbac.mock.v1";

function escapeHtml(value) {
    return String(value)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#39;");
}

function normalizeKey(value) {
    return String(value || "")
        .trim()
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/[^a-z0-9]+/g, "_")
        .replace(/^_+|_+$/g, "");
}

function toggleSidebar() {
    const sidebar = document.getElementById("sidebar");
    const overlay = document.getElementById("overlay");
    if (sidebar) sidebar.classList.toggle("show");
    if (overlay) overlay.classList.toggle("show");
}

window.toggleSidebar = toggleSidebar;

async function loadAdminRbacData() {
    const endpoint = window.adminRbacDataUrl || "/admin/rbac/data";
    const response = await fetch(endpoint, {
        headers: {
            Accept: "application/json",
        },
    });

    if (!response.ok) {
        throw new Error(`Error HTTP ${response.status}`);
    }

    return response.json();
}

function readStoredRbacState() {
    // TEMPORARY LOCAL PERSISTENCE.
    // Remove localStorage usage when the real RBAC backend is available.
    try {
        const raw = window.localStorage.getItem(ADMIN_RBAC_STORAGE_KEY);
        if (!raw) return null;
        const parsed = JSON.parse(raw);
        if (!parsed || typeof parsed !== "object") return null;
        return parsed;
    } catch (error) {
        console.warn("No se pudo leer RBAC del almacenamiento local:", error);
        return null;
    }
}

function persistRbacState() {
    try {
        window.localStorage.setItem(
            ADMIN_RBAC_STORAGE_KEY,
            JSON.stringify({
                users: adminRbacState.users,
                roles: adminRbacState.roles,
                permissions: adminRbacState.permissions,
            }),
        );
    } catch (error) {
        console.warn("No se pudo persistir RBAC en almacenamiento local:", error);
    }
}

function showFeedback(message, tone) {
    const feedback = document.getElementById("adminRbacFeedback");
    if (!feedback) return;

    feedback.className = `alert admin-rbac-feedback alert-${tone}`;
    feedback.textContent = message;
}

function getRole(roleId) {
    return adminRbacState.roles.find(function (role) {
        return role.id === roleId;
    }) || null;
}

function getPermission(permissionId) {
    return adminRbacState.permissions.find(function (permission) {
        return permission.id === permissionId;
    }) || null;
}

function getRoleUserCount(roleId) {
    return adminRbacState.users.filter(function (user) {
        return user.roleId === roleId;
    }).length;
}

function getRoleColor(roleId) {
    const map = {
        admin: "#1976d2",
        doctor: "#28a745",
        patient: "#fd7e14",
    };

    return map[roleId] || "#6b7280";
}

function computeInitials(name) {
    return String(name || "")
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map(function (part) {
            return part.charAt(0).toUpperCase();
        })
        .join("") || "US";
}

function closeAllModals() {
    document.querySelectorAll(".rbac-modal.is-open").forEach(function (modal) {
        modal.classList.remove("is-open");
    });
    document.getElementById("rbacModalBackdrop")?.classList.remove("is-open");
}

function openModal(id) {
    closeAllModals();
    document.getElementById("rbacModalBackdrop")?.classList.add("is-open");
    document.getElementById(id)?.classList.add("is-open");
}

function renderRoleOptions(selectId, selectedValue) {
    const select = document.getElementById(selectId);
    if (!select) return;

    select.innerHTML = adminRbacState.roles.map(function (role) {
        return `<option value="${escapeHtml(role.id)}">${escapeHtml(role.label)}</option>`;
    }).join("");

    if (selectedValue) {
        select.value = selectedValue;
    }
}

function renderUsers() {
    const tbody = document.getElementById("rbacUsersTableBody");
    if (!tbody) return;

    if (!adminRbacState.users.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center text-muted py-4">No hay usuarios registrados.</td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = adminRbacState.users.map(function (user) {
        const role = getRole(user.roleId);
        return `
            <tr>
                <td>
                    <div class="rbac-user-cell">
                        <span class="rbac-user-avatar" style="background: ${escapeHtml(user.color)};">
                            ${escapeHtml(user.initials)}
                        </span>
                        <span class="rbac-user-name">${escapeHtml(user.name)}</span>
                    </div>
                </td>
                <td><span class="rbac-email-text">${escapeHtml(user.email)}</span></td>
                <td><span class="rbac-role-badge ${escapeHtml(role?.tone || "admin")}">${escapeHtml(role?.label || user.roleId)}</span></td>
                <td><span class="rbac-status-badge ${escapeHtml(user.status)}">${escapeHtml(user.status)}</span></td>
                <td>
                    <button
                        class="btn btn-outline-primary btn-sm rbac-action-btn"
                        type="button"
                        data-action="change-role"
                        data-user-id="${escapeHtml(user.id)}"
                    >
                        <i class="bi bi-shield"></i>
                        Cambiar Rol
                    </button>
                </td>
            </tr>
        `;
    }).join("");
}

function renderRoles() {
    const list = document.getElementById("rbacRolesList");
    if (!list) return;

    list.innerHTML = adminRbacState.roles.map(function (role) {
        const permissionChips = role.permissionIds.map(function (permissionId) {
            const permission = getPermission(permissionId);
            if (!permission) return "";

            return `
                <span class="rbac-permission-chip${permission.enabled ? "" : " is-disabled"}">
                    <span>${escapeHtml(permission.label)}</span>
                    <button
                        class="rbac-chip-remove"
                        type="button"
                        data-action="remove-role-permission"
                        data-role-id="${escapeHtml(role.id)}"
                        data-permission-id="${escapeHtml(permission.id)}"
                        aria-label="Quitar permiso"
                    >
                        &times;
                    </button>
                </span>
            `;
        }).join("");

        return `
            <article class="rbac-role-card ${escapeHtml(role.tone)}">
                <div class="rbac-role-card-header">
                    <span class="rbac-role-tag ${escapeHtml(role.tone)}">
                        <i class="${escapeHtml(role.icon)}"></i>
                        ${escapeHtml(role.label)}
                    </span>
                    <span class="rbac-role-count">${escapeHtml(getRoleUserCount(role.id))} usuario${getRoleUserCount(role.id) === 1 ? "" : "s"}</span>
                </div>

                <div class="rbac-role-permissions">
                    ${permissionChips}
                    <button
                        class="rbac-chip-add"
                        type="button"
                        data-action="open-assign-permission"
                        data-role-id="${escapeHtml(role.id)}"
                    >
                        + permiso
                    </button>
                </div>
            </article>
        `;
    }).join("");
}

function renderPermissions() {
    const list = document.getElementById("rbacPermissionsList");
    if (!list) return;

    list.innerHTML = adminRbacState.permissions.map(function (permission) {
        return `
            <div class="rbac-system-permission">
                <div>
                    <p class="rbac-system-permission-title">${escapeHtml(permission.label)}</p>
                    <p class="rbac-system-permission-desc">${escapeHtml(permission.description)}</p>
                </div>
                <label class="rbac-switch">
                    <input
                        type="checkbox"
                        data-action="toggle-permission"
                        data-permission-id="${escapeHtml(permission.id)}"
                        ${permission.enabled ? "checked" : ""}
                    />
                    <span class="rbac-switch-slider"></span>
                </label>
            </div>
        `;
    }).join("");
}

function renderAll() {
    renderUsers();
    renderRoles();
    renderPermissions();
}

function resetUserForm() {
    const form = document.getElementById("rbacUserForm");
    if (!form) return;
    form.reset();
    renderRoleOptions("rbacUserRole", adminRbacState.roles[0]?.id);
}

function openNewUserModal() {
    resetUserForm();
    openModal("rbacUserModal");
}

function openRoleModal(userId) {
    const user = adminRbacState.users.find(function (item) {
        return item.id === userId;
    });
    if (!user) return;

    const userIdField = document.getElementById("rbacRoleUserId");
    const userNameField = document.getElementById("rbacRoleUserName");
    if (userIdField) userIdField.value = String(user.id);
    if (userNameField) userNameField.value = user.name;
    renderRoleOptions("rbacRoleSelect", user.roleId);
    openModal("rbacRoleModal");
}

function openPermissionModal() {
    document.getElementById("rbacPermissionForm")?.reset();
    const enabledInput = document.getElementById("rbacPermissionEnabled");
    if (enabledInput) enabledInput.checked = true;
    openModal("rbacPermissionModal");
}

function openAssignPermissionModal(roleId) {
    const role = getRole(roleId);
    if (!role) return;

    const availablePermissions = adminRbacState.permissions.filter(function (permission) {
        return !role.permissionIds.includes(permission.id);
    });

    if (!availablePermissions.length) {
        showFeedback("Ese rol ya tiene todos los permisos disponibles.", "warning");
        return;
    }

    const roleIdField = document.getElementById("rbacAssignRoleId");
    const roleNameField = document.getElementById("rbacAssignRoleName");
    const permissionSelect = document.getElementById("rbacAssignPermissionSelect");

    if (roleIdField) roleIdField.value = role.id;
    if (roleNameField) roleNameField.value = role.label;
    if (permissionSelect) {
        permissionSelect.innerHTML = availablePermissions.map(function (permission) {
            return `<option value="${escapeHtml(permission.id)}">${escapeHtml(permission.label)}</option>`;
        }).join("");
    }

    openModal("rbacAssignPermissionModal");
}

function createUser(event) {
    event.preventDefault();

    const formData = new FormData(event.currentTarget);
    const name = String(formData.get("name") || "").trim();
    const email = String(formData.get("email") || "").trim().toLowerCase();
    const roleId = String(formData.get("roleId") || "");
    const status = String(formData.get("status") || "activo");

    if (!name) {
        showFeedback("El nombre del usuario es obligatorio.", "danger");
        return;
    }

    if (!email) {
        showFeedback("El correo del usuario es obligatorio.", "danger");
        return;
    }

    const exists = adminRbacState.users.some(function (user) {
        return user.email.toLowerCase() === email;
    });

    if (exists) {
        showFeedback("Ya existe un usuario con ese correo.", "danger");
        return;
    }

    adminRbacState.users.push({
        id: adminRbacState.users.reduce(function (max, user) {
            return Math.max(max, Number(user.id) || 0);
        }, 0) + 1,
        initials: computeInitials(name),
        color: getRoleColor(roleId),
        name,
        email,
        roleId,
        status,
    });

    persistRbacState();
    renderAll();
    closeAllModals();
    showFeedback("Usuario creado correctamente.", "success");
}

function changeUserRole(event) {
    event.preventDefault();

    const formData = new FormData(event.currentTarget);
    const userId = Number(formData.get("userId"));
    const roleId = String(formData.get("roleId") || "");

    const user = adminRbacState.users.find(function (item) {
        return item.id === userId;
    });
    if (!user) return;

    user.roleId = roleId;
    user.color = getRoleColor(roleId);
    persistRbacState();
    renderAll();
    closeAllModals();
    showFeedback("Rol actualizado correctamente.", "success");
}

function createPermission(event) {
    event.preventDefault();

    const formData = new FormData(event.currentTarget);
    const key = normalizeKey(formData.get("key"));
    const description = String(formData.get("description") || "").trim();
    const enabled = Boolean(formData.get("enabled"));

    if (!key) {
        showFeedback("La clave del permiso es obligatoria.", "danger");
        return;
    }

    if (!description) {
        showFeedback("La descripcion del permiso es obligatoria.", "danger");
        return;
    }

    const exists = adminRbacState.permissions.some(function (permission) {
        return permission.id === key;
    });

    if (exists) {
        showFeedback("Ya existe un permiso con esa clave.", "danger");
        return;
    }

    adminRbacState.permissions.push({
        id: key,
        label: key,
        description,
        enabled,
    });

    persistRbacState();
    renderAll();
    closeAllModals();
    showFeedback("Permiso creado correctamente.", "success");
}

function assignPermissionToRole(event) {
    event.preventDefault();

    const formData = new FormData(event.currentTarget);
    const roleId = String(formData.get("roleId") || "");
    const permissionId = String(formData.get("permissionId") || "");

    const role = getRole(roleId);
    if (!role || !permissionId) return;

    if (role.permissionIds.includes(permissionId)) {
        showFeedback("El rol ya tiene asignado ese permiso.", "warning");
        return;
    }

    role.permissionIds.push(permissionId);
    persistRbacState();
    renderAll();
    closeAllModals();
    showFeedback("Permiso asignado correctamente.", "success");
}

function removePermissionFromRole(roleId, permissionId) {
    const role = getRole(roleId);
    if (!role) return;

    role.permissionIds = role.permissionIds.filter(function (id) {
        return id !== permissionId;
    });

    persistRbacState();
    renderAll();
    showFeedback("Permiso removido del rol.", "success");
}

function togglePermissionEnabled(permissionId, enabled) {
    const permission = getPermission(permissionId);
    if (!permission) return;

    permission.enabled = enabled;
    persistRbacState();
    renderAll();
    showFeedback(
        enabled ? "Permiso habilitado." : "Permiso deshabilitado.",
        "success",
    );
}

function handleUsersClick(event) {
    const trigger = event.target.closest("[data-action='change-role']");
    if (!trigger) return;

    openRoleModal(Number(trigger.getAttribute("data-user-id")));
}

function handleRolesClick(event) {
    const trigger = event.target.closest("[data-action]");
    if (!trigger) return;

    const action = trigger.getAttribute("data-action");
    if (action === "remove-role-permission") {
        removePermissionFromRole(
            String(trigger.getAttribute("data-role-id") || ""),
            String(trigger.getAttribute("data-permission-id") || ""),
        );
        return;
    }

    if (action === "open-assign-permission") {
        openAssignPermissionModal(String(trigger.getAttribute("data-role-id") || ""));
    }
}

function handlePermissionsChange(event) {
    const toggle = event.target.closest("[data-action='toggle-permission']");
    if (!toggle) return;

    togglePermissionEnabled(
        String(toggle.getAttribute("data-permission-id") || ""),
        Boolean(toggle.checked),
    );
}

function bindModalHelpers() {
    document.querySelectorAll("[data-close-modal]").forEach(function (button) {
        button.addEventListener("click", closeAllModals);
    });

    document.getElementById("rbacModalBackdrop")?.addEventListener("click", closeAllModals);
    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            closeAllModals();
        }
    });
}

function bindEvents() {
    document.getElementById("btnOpenNewUserModal")?.addEventListener("click", openNewUserModal);
    document.getElementById("btnOpenNewPermissionModal")?.addEventListener("click", openPermissionModal);
    document.getElementById("rbacUsersTableBody")?.addEventListener("click", handleUsersClick);
    document.getElementById("rbacRolesList")?.addEventListener("click", handleRolesClick);
    document.getElementById("rbacPermissionsList")?.addEventListener("change", handlePermissionsChange);
    document.getElementById("rbacUserForm")?.addEventListener("submit", createUser);
    document.getElementById("rbacRoleForm")?.addEventListener("submit", changeUserRole);
    document.getElementById("rbacPermissionForm")?.addEventListener("submit", createPermission);
    document.getElementById("rbacAssignPermissionForm")?.addEventListener("submit", assignPermissionToRole);
    bindModalHelpers();
}

document.addEventListener("DOMContentLoaded", function () {
    bindEvents();

    loadAdminRbacData()
        .then(function (payload) {
            const stored = readStoredRbacState();
            if (stored) {
                adminRbacState.users = stored.users || [];
                adminRbacState.roles = stored.roles || [];
                adminRbacState.permissions = stored.permissions || [];
            } else {
                adminRbacState.users = payload.users || [];
                adminRbacState.roles = payload.roles || [];
                adminRbacState.permissions = payload.permissions || [];
            }

            renderAll();
        })
        .catch(function (error) {
            console.error("No se pudo cargar el RBAC:", error);

            const stored = readStoredRbacState();
            if (stored) {
                adminRbacState.users = stored.users || [];
                adminRbacState.roles = stored.roles || [];
                adminRbacState.permissions = stored.permissions || [];
                renderAll();
                showFeedback("Se cargo el estado RBAC guardado localmente.", "warning");
                return;
            }

            showFeedback("No fue posible cargar el mock remoto de RBAC.", "danger");
        });
});
