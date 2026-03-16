const dashboardState = {
    appointments: [],
    currentPage: 1,
    pageSize: 4,
};

function escapeHtml(value) {
    return String(value)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#39;");
}

function getStatusClass(status) {
    const map = {
        confirmada: "badge-confirmada",
        cancelada: "badge-cancelada",
        pendiente: "badge-pendiente",
    };

    return map[String(status).toLowerCase()] ?? "badge-pendiente";
}

function setText(id, value) {
    const node = document.getElementById(id);
    if (!node) return;
    node.textContent = value;
}

function renderStats(stats) {
    if (!stats) return;

    setText("statTotalUsers", stats.totalUsers ?? "--");
    setText("statAppointmentsToday", stats.appointmentsToday ?? "--");
    setText("statActiveDoctors", stats.activeDoctors ?? "--");
    setText("statAppointmentsMonth", stats.appointmentsMonth ?? "--");

    const details = stats.details ?? {};
    setText("statTotalUsersDetail", details.totalUsers ?? "");
    setText("statAppointmentsTodayDetail", details.appointmentsToday ?? "");
    setText("statActiveDoctorsDetail", details.activeDoctors ?? "");
    setText("statAppointmentsMonthDetail", details.appointmentsMonth ?? "");
}

function renderAppointments() {
    const tbody = document.getElementById("recentAppointmentsBody");
    if (!tbody) return;

    const total = dashboardState.appointments.length;
    const totalPages = Math.max(1, Math.ceil(total / dashboardState.pageSize));
    dashboardState.currentPage = Math.min(dashboardState.currentPage, totalPages);

    const start = (dashboardState.currentPage - 1) * dashboardState.pageSize;
    const end = start + dashboardState.pageSize;
    const rows = dashboardState.appointments.slice(start, end);

    if (!rows.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="text-center text-muted py-4">Sin citas recientes</td>
            </tr>
        `;
    } else {
        tbody.innerHTML = rows
            .map(
                (item) => `
                <tr>
                    <td>${escapeHtml(item.patient)}</td>
                    <td>${escapeHtml(item.doctor)}</td>
                    <td>${escapeHtml(item.datetime)}</td>
                    <td><span class="${getStatusClass(item.status)}">${escapeHtml(item.status)}</span></td>
                </tr>
            `
            )
            .join("");
    }

    const pageInfo = document.getElementById("appointmentsPageInfo");
    if (pageInfo) {
        if (!total) {
            pageInfo.textContent = "0 citas";
        } else {
            pageInfo.textContent = `Mostrando ${start + 1}-${Math.min(
                end,
                total
            )} de ${total} citas`;
        }
    }

    renderPagination(totalPages);
}

function createPageButton(label, disabled, onClick, isActive = false) {
    const li = document.createElement("li");
    li.className = `page-item${disabled ? " disabled" : ""}${
        isActive ? " active" : ""
    }`;

    const button = document.createElement("button");
    button.type = "button";
    button.className = "page-link";
    button.textContent = label;
    button.disabled = disabled;
    if (!disabled) button.addEventListener("click", onClick);

    li.appendChild(button);
    return li;
}

function renderPagination(totalPages) {
    const pagination = document.getElementById("appointmentsPagination");
    if (!pagination) return;

    pagination.innerHTML = "";

    if (totalPages <= 1) return;

    pagination.appendChild(
        createPageButton(
            "Anterior",
            dashboardState.currentPage === 1,
            () => {
                dashboardState.currentPage -= 1;
                renderAppointments();
            }
        )
    );

    for (let page = 1; page <= totalPages; page += 1) {
        pagination.appendChild(
            createPageButton(
                String(page),
                false,
                () => {
                    dashboardState.currentPage = page;
                    renderAppointments();
                },
                page === dashboardState.currentPage
            )
        );
    }

    pagination.appendChild(
        createPageButton(
            "Siguiente",
            dashboardState.currentPage === totalPages,
            () => {
                dashboardState.currentPage += 1;
                renderAppointments();
            }
        )
    );
}

function renderLogs(logs = []) {
    const container = document.getElementById("activityLogs");
    if (!container) return;

    if (!logs.length) {
        container.innerHTML = `<div class="text-muted small">Sin actividad reciente</div>`;
        return;
    }

    container.innerHTML = logs
        .map(
            (log) => `
            <div class="log-item">
                <div class="log-icon log-icon-${escapeHtml(log.tone ?? "info")}">
                    <i class="bi ${escapeHtml(log.icon ?? "bi-info-circle")}"></i>
                </div>
                <div class="log-info">
                    <span class="log-text">${escapeHtml(log.text ?? "")}</span>
                    <span class="log-time">${escapeHtml(log.time ?? "")}</span>
                </div>
            </div>
        `
        )
        .join("");
}

function animateCardsOnLoad() {
    document.querySelectorAll(".stat-card.animate-card").forEach((card, index) => {
        card.classList.add("fade-in-start");
        setTimeout(() => {
            card.classList.add("fade-in-visible");
        }, 120 * index + 80);
    });
}

function bindCardHoverEvents() {
    document.querySelectorAll(".stat-card").forEach((card) => {
        card.addEventListener("mouseenter", () => {
            card.classList.add("is-hovered");
        });
        card.addEventListener("mouseleave", () => {
            card.classList.remove("is-hovered");
        });
    });
}

async function loadDashboardData() {
    const endpoint = window.dashboardDataUrl ?? "/dashboard/data";
    const response = await fetch(endpoint, {
        method: "GET",
        headers: {
            Accept: "application/json",
        },
    });

    if (!response.ok) {
        throw new Error(`Error HTTP ${response.status}`);
    }

    const payload = await response.json();

    renderStats(payload.stats);
    dashboardState.appointments = payload.recentAppointments ?? [];
    dashboardState.currentPage = 1;
    renderAppointments();
    renderLogs(payload.activityLogs ?? []);
}

document.addEventListener("DOMContentLoaded", async () => {
    animateCardsOnLoad();
    bindCardHoverEvents();

    document
        .getElementById("btnRefreshDashboard")
        ?.addEventListener("click", async () => {
            await loadDashboardData();
        });

    try {
        await loadDashboardData();
    } catch (error) {
        console.error("No se pudo cargar el dashboard:", error);
        setText("appointmentsPageInfo", "Error al cargar datos");
    }
});
