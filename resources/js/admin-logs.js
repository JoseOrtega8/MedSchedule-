/**
 * MedSchedule - Panel de Logs (Admin Panel)
 *
 * Soporta dos formatos de respuesta (fix #60, defensivo ante AS5):
 *   1) Arreglo plano (formato viejo): payload.logs = [ ... ]  -> paginacion client-side.
 *   2) Objeto paginado de Laravel (formato nuevo): payload.logs = { data, current_page, last_page, total, per_page }
 *      -> paginacion server-side (refetch por pagina conservando filtros).
 * Se detecta el formato en tiempo de ejecucion, por lo que la vista no se rompe
 * cuando el backend migre al shape paginado.
 */

const adminLogsState = {
    logs: [],
    filteredLogs: [],
    expandedLogId: null,
    currentPage: 1,
    pageSize: 20,
    // Modo server: true cuando el backend responde con objeto paginado.
    serverMode: false,
    // Metadatos de paginacion del servidor (solo en serverMode).
    serverMeta: {
        currentPage: 1,
        lastPage: 1,
        total: 0,
    },
    // Evita refetch concurrentes mientras hay una peticion en curso.
    isLoading: false,
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

/**
 * Descarga los logs. En serverMode envia pagina + filtros como query params
 * para que el backend pagine y filtre; en modo cliente descarga todo una vez.
 */
async function loadAdminLogsData(params = {}) {
    const baseEndpoint = window.adminLogsDataUrl || "/admin/logs/data";
    const query = new URLSearchParams();

    if (params.page) query.set("page", params.page);
    if (params.action) query.set("action", params.action);
    if (params.model) query.set("model", params.model);
    if (params.from) query.set("from", params.from);
    if (params.to) query.set("to", params.to);

    const separator = baseEndpoint.includes("?") ? "&" : "?";
    const endpoint = query.toString() ? `${baseEndpoint}${separator}${query.toString()}` : baseEndpoint;

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

/**
 * Normaliza el shape de logs. Devuelve { logs, pagination }.
 * - pagination es null cuando el formato es arreglo plano (modo cliente).
 * - pagination trae metadatos cuando es objeto paginado de Laravel (modo server).
 */
function normalizeLogsPayload(payload) {
    // El endpoint envuelve los logs bajo la llave "logs"; si no existe, usar el payload directo.
    const raw = payload && payload.logs !== undefined ? payload.logs : payload;

    // Formato viejo: arreglo plano.
    if (Array.isArray(raw)) {
        return { logs: raw, pagination: null };
    }

    // Formato nuevo: objeto paginado de Laravel { data, current_page, last_page, total }.
    if (raw && typeof raw === "object" && Array.isArray(raw.data)) {
        return {
            logs: raw.data,
            pagination: {
                currentPage: Number(raw.current_page) || 1,
                lastPage: Number(raw.last_page) || 1,
                total: Number(raw.total) || raw.data.length,
                perPage: Number(raw.per_page) || raw.data.length,
            },
        };
    }

    // Shape desconocido: degradar a vacio sin romper la vista.
    return { logs: [], pagination: null };
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

/**
 * Aplica filtros. En modo cliente filtra el arreglo completo en memoria;
 * en serverMode dispara un refetch de la pagina 1 conservando los filtros.
 */
function applyFilters() {
    if (adminLogsState.serverMode) {
        fetchServerPage(1);
        return;
    }

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

/**
 * Refetch de una pagina en serverMode. Conserva los filtros actuales
 * y muestra indicador de carga mientras llega la respuesta.
 */
function fetchServerPage(page) {
    if (adminLogsState.isLoading) return;

    const filters = getCurrentFilters();
    setLoading(true);

    loadAdminLogsData({ page: page, ...filters })
        .then(function (payload) {
            const { logs, pagination } = normalizeLogsPayload(payload);
            adminLogsState.logs = logs;
            adminLogsState.filteredLogs = logs;

            if (pagination) {
                adminLogsState.serverMeta = pagination;
                adminLogsState.currentPage = pagination.currentPage;
            }

            renderLogsTable();
        })
        .catch(function (error) {
            console.error("No se pudo cargar la pagina de logs:", error);
            renderLogsError();
        })
        .finally(function () {
            setLoading(false);
        });
}

/** Indicador de carga entre paginas. */
function setLoading(isLoading) {
    adminLogsState.isLoading = isLoading;

    const info = document.getElementById("adminLogsPageInfo");
    if (info && isLoading) {
        info.textContent = "Cargando...";
    }

    const pagination = document.getElementById("adminLogsPagination");
    if (pagination) {
        pagination.classList.toggle("is-loading", isLoading);
    }
}

function renderLogsError() {
    const tbody = document.getElementById("adminLogsTableBody");
    if (tbody) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="logs-empty-state">No fue posible cargar los logs.</td>
            </tr>
        `;
    }
    renderLogsPageInfo(0, 0, 0);
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

    // En serverMode los datos ya vienen paginados por el backend; en modo cliente se corta aqui.
    let total;
    let totalPages;
    let start;
    let rows;

    if (adminLogsState.serverMode) {
        total = adminLogsState.serverMeta.total;
        totalPages = adminLogsState.serverMeta.lastPage;
        adminLogsState.currentPage = adminLogsState.serverMeta.currentPage;
        start = (adminLogsState.currentPage - 1) * adminLogsState.pageSize;
        rows = adminLogsState.filteredLogs;
    } else {
        total = adminLogsState.filteredLogs.length;
        totalPages = Math.max(1, Math.ceil(total / adminLogsState.pageSize));
        adminLogsState.currentPage = Math.min(adminLogsState.currentPage, totalPages);
        start = (adminLogsState.currentPage - 1) * adminLogsState.pageSize;
        const end = start + adminLogsState.pageSize;
        rows = adminLogsState.filteredLogs.slice(start, end);
    }

    const end = start + rows.length;

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

/** Navega a una pagina: refetch en serverMode, corte en memoria en modo cliente. */
function goToPage(page) {
    if (adminLogsState.serverMode) {
        fetchServerPage(page);
        return;
    }
    adminLogsState.currentPage = page;
    renderLogsTable();
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
                goToPage(adminLogsState.currentPage - 1);
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
                    goToPage(page);
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
                goToPage(adminLogsState.currentPage + 1);
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
            const { logs, pagination } = normalizeLogsPayload(payload);

            // Activa modo server cuando el backend responde paginado.
            adminLogsState.serverMode = pagination !== null;
            adminLogsState.logs = logs;
            adminLogsState.filteredLogs = logs.slice();

            if (pagination) {
                adminLogsState.serverMeta = pagination;
                adminLogsState.currentPage = pagination.currentPage;
                adminLogsState.pageSize = pagination.perPage || adminLogsState.pageSize;
            }

            adminLogsState.defaults.from = filters.defaultFrom || "";
            adminLogsState.defaults.to = filters.defaultTo || "";

            buildSelectOptions("logActionFilter", filters.actions || [], "Todas las acciones");
            buildSelectOptions("logModelFilter", filters.models || [], "Todos los modelos");

            const dateFrom = document.getElementById("logDateFrom");
            const dateTo = document.getElementById("logDateTo");
            if (dateFrom) dateFrom.value = adminLogsState.defaults.from;
            if (dateTo) dateTo.value = adminLogsState.defaults.to;

            if (adminLogsState.serverMode) {
                // Ya tenemos la primera pagina; solo renderizar.
                renderLogsTable();
            } else {
                applyFilters();
            }
        })
        .catch(function (error) {
            console.error("No se pudieron cargar los logs:", error);
            renderLogsError();
        });
});
