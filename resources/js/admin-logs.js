/**
 * MedSchedule - Panel de Logs (Admin Panel)
 * Loads temporary mock data asynchronously and handles client-side filtering.
 */

const adminLogsState = {
    logs: [],
    filteredLogs: [],
    expandedLogId: null,
    currentPage: 1,
    pageSize: 20,
    defaults: {
        from: "",
        to: "",
    },
};

function escapeHtml(value) {
    return String(value)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#39;");
}

function toggleSidebar() {
    const sidebar = document.getElementById("sidebar");
    const overlay = document.getElementById("overlay");
    if (sidebar) sidebar.classList.toggle("show");
    if (overlay) overlay.classList.toggle("show");
}

window.toggleSidebar = toggleSidebar;

async function loadAdminLogsData() {
    const endpoint = window.adminLogsDataUrl || "/admin/logs/data";
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

function buildSelectOptions(selectId, items, defaultLabel) {
    const select = document.getElementById(selectId);
    if (!select) return;

    select.innerHTML = [
        `<option value="">${defaultLabel}</option>`,
        ...items.map(function (item) {
            return `<option value="${escapeHtml(item)}">${escapeHtml(item)}</option>`;
        }),
    ].join("");
}

function getCurrentFilters() {
    return {
        action: document.getElementById("logActionFilter")?.value || "",
        model: document.getElementById("logModelFilter")?.value || "",
        from: document.getElementById("logDateFrom")?.value || "",
        to: document.getElementById("logDateTo")?.value || "",
    };
}

function getLogDate(log) {
    return String(log.createdAt || "").slice(0, 10);
}

function applyFilters() {
    const filters = getCurrentFilters();

    adminLogsState.filteredLogs = adminLogsState.logs.filter(function (log) {
        if (filters.action && log.action !== filters.action) {
            return false;
        }

        if (filters.model && log.modelType !== filters.model) {
            return false;
        }

        const logDate = getLogDate(log);
        if (filters.from && logDate < filters.from) {
            return false;
        }

        if (filters.to && logDate > filters.to) {
            return false;
        }

        return true;
    });

    adminLogsState.currentPage = 1;
    renderLogsTable();
}

function formatJson(value) {
    if (Array.isArray(value) && value.length === 0) {
        return "{}";
    }

    if (value && typeof value === "object" && Object.keys(value).length === 0) {
        return "{}";
    }

    return JSON.stringify(value, null, 2);
}

function renderLogsTable() {
    const tbody = document.getElementById("adminLogsTableBody");
    if (!tbody) return;

    const total = adminLogsState.filteredLogs.length;
    const totalPages = Math.max(1, Math.ceil(total / adminLogsState.pageSize));
    adminLogsState.currentPage = Math.min(adminLogsState.currentPage, totalPages);

    const start = (adminLogsState.currentPage - 1) * adminLogsState.pageSize;
    const end = start + adminLogsState.pageSize;
    const rows = adminLogsState.filteredLogs.slice(start, end);

    if (!rows.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="logs-empty-state">No hay logs que coincidan con los filtros.</td>
            </tr>
        `;
        renderLogsPagination(0);
        renderLogsPageInfo(0, 0, 0);
        return;
    }

    let html = "";

    rows.forEach(function (log) {
        const isExpanded = adminLogsState.expandedLogId === log.id;

        html += `
            <tr>
                <td>
                    <div class="log-user-cell">
                        <span class="log-user-avatar" style="background: ${escapeHtml(log.userColor)};">
                            ${escapeHtml(log.userId)}
                        </span>
                        <span class="log-user-name">${escapeHtml(log.userName)}</span>
                    </div>
                </td>
                <td><span class="log-action-badge ${escapeHtml(log.action)}">${escapeHtml(log.action)}</span></td>
                <td>${escapeHtml(log.modelType)}</td>
                <td>${escapeHtml(log.description)}</td>
                <td>${escapeHtml(log.ipAddress)}</td>
                <td>${escapeHtml(log.userAgent)}</td>
                <td>${escapeHtml(log.createdAt)}</td>
                <td class="text-center">
                    <button
                        class="logs-detail-btn"
                        type="button"
                        data-action="toggle-detail"
                        data-id="${escapeHtml(log.id)}"
                        aria-label="Ver detalle"
                    >
                        <i class="bi ${isExpanded ? "bi-eye-slash" : "bi-eye"}"></i>
                    </button>
                </td>
            </tr>
        `;

        if (isExpanded) {
            html += `
                <tr class="logs-detail-row">
                    <td colspan="8">
                        <div class="logs-detail-header">
                            <i class="bi bi-braces"></i>
                            <span>Detalle - old_values / new_values JSON</span>
                        </div>
                        <div class="logs-detail-grid">
                            <div class="logs-json-box">
                                <h6>old_values</h6>
                                <pre>${escapeHtml(formatJson(log.oldValues || {}))}</pre>
                            </div>
                            <div class="logs-json-box">
                                <h6>new_values</h6>
                                <pre>${escapeHtml(formatJson(log.newValues || {}))}</pre>
                            </div>
                        </div>
                    </td>
                </tr>
            `;
        }
    });

    tbody.innerHTML = html;
    renderLogsPagination(totalPages);
    renderLogsPageInfo(start + 1, Math.min(end, total), total);
}

function createPageButton(label, disabled, onClick, isActive) {
    const li = document.createElement("li");
    li.className = `page-item${disabled ? " disabled" : ""}${isActive ? " active" : ""}`;

    const button = document.createElement("button");
    button.type = "button";
    button.className = "page-link";
    button.textContent = label;
    button.disabled = disabled;
    if (!disabled) {
        button.addEventListener("click", onClick);
    }

    li.appendChild(button);
    return li;
}

function renderLogsPagination(totalPages) {
    const pagination = document.getElementById("adminLogsPagination");
    if (!pagination) return;

    pagination.innerHTML = "";

    if (totalPages <= 1) return;

    pagination.appendChild(
        createPageButton(
            "<",
            adminLogsState.currentPage === 1,
            function () {
                adminLogsState.currentPage -= 1;
                renderLogsTable();
            },
            false,
        ),
    );

    for (let page = 1; page <= totalPages; page += 1) {
        pagination.appendChild(
            createPageButton(
                String(page),
                false,
                function () {
                    adminLogsState.currentPage = page;
                    renderLogsTable();
                },
                page === adminLogsState.currentPage,
            ),
        );
    }

    pagination.appendChild(
        createPageButton(
            ">",
            adminLogsState.currentPage === totalPages,
            function () {
                adminLogsState.currentPage += 1;
                renderLogsTable();
            },
            false,
        ),
    );
}

function renderLogsPageInfo(start, end, total) {
    const info = document.getElementById("adminLogsPageInfo");
    if (!info) return;

    if (!total) {
        info.textContent = "0 registros";
        return;
    }

    info.textContent = `Mostrando ${start}-${end} de ${total} registros - paginacion ${adminLogsState.pageSize} por pagina`;
}

function handleTableClick(event) {
    const trigger = event.target.closest("[data-action='toggle-detail']");
    if (!trigger) return;

    const id = Number(trigger.getAttribute("data-id"));
    if (!id) return;

    adminLogsState.expandedLogId = adminLogsState.expandedLogId === id ? null : id;
    renderLogsTable();
}

function clearFilters() {
    const action = document.getElementById("logActionFilter");
    const model = document.getElementById("logModelFilter");
    const from = document.getElementById("logDateFrom");
    const to = document.getElementById("logDateTo");

    if (action) action.value = "";
    if (model) model.value = "";
    if (from) from.value = adminLogsState.defaults.from;
    if (to) to.value = adminLogsState.defaults.to;

    applyFilters();
}

function bindFilters() {
    ["logActionFilter", "logModelFilter", "logDateFrom", "logDateTo"].forEach(function (id) {
        const node = document.getElementById(id);
        if (!node) return;
        node.addEventListener("change", applyFilters);
    });

    document.getElementById("clearLogFiltersBtn")?.addEventListener("click", clearFilters);
    document.getElementById("adminLogsTableBody")?.addEventListener("click", handleTableClick);
}

document.addEventListener("DOMContentLoaded", function () {
    bindFilters();

    loadAdminLogsData()
        .then(function (payload) {
            const filters = payload.filters || {};
            adminLogsState.logs = payload.logs || [];
            adminLogsState.filteredLogs = adminLogsState.logs.slice();
            adminLogsState.defaults.from = filters.defaultFrom || "";
            adminLogsState.defaults.to = filters.defaultTo || "";

            buildSelectOptions("logActionFilter", filters.actions || [], "Todas las acciones");
            buildSelectOptions("logModelFilter", filters.models || [], "Todos los modelos");

            const dateFrom = document.getElementById("logDateFrom");
            const dateTo = document.getElementById("logDateTo");
            if (dateFrom) dateFrom.value = adminLogsState.defaults.from;
            if (dateTo) dateTo.value = adminLogsState.defaults.to;

            applyFilters();
        })
        .catch(function (error) {
            console.error("No se pudieron cargar los logs:", error);
            const tbody = document.getElementById("adminLogsTableBody");
            if (tbody) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="logs-empty-state">No fue posible cargar los logs temporales.</td>
                    </tr>
                `;
            }
            renderLogsPageInfo(0, 0, 0);
        });
});
