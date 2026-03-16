/**
 * MedSchedule - Mi Agenda (Doctor Panel)
 * Handles date navigation, async loading, appointment updates and local history modal UI.
 */

const agendaState = {
    currentWeekStart: null,
    selectedDate: null,
    agendaItems: [],
};

const DAY_NAMES = ["DOM", "LUN", "MAR", "MIE", "JUE", "VIE", "SAB"];
const DAY_FULL_NAMES = [
    "Domingo", "Lunes", "Martes", "Miercoles", "Jueves", "Viernes", "Sabado",
];
const MONTH_NAMES = [
    "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
    "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre",
];

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

function formatDateKey(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, "0");
    const d = String(date.getDate()).padStart(2, "0");
    return `${y}-${m}-${d}`;
}

function getMonday(date) {
    const d = new Date(date);
    const day = d.getDay();
    const diff = d.getDate() - day + (day === 0 ? -6 : 1);
    d.setDate(diff);
    d.setHours(0, 0, 0, 0);
    return d;
}

function normalizeTime(value) {
    return String(value || "").slice(0, 5);
}

function timeToMinutes(value) {
    const [hours, minutes] = String(value || "00:00").split(":").map(Number);
    return (hours * 60) + minutes;
}

function isBlockedItem(item) {
    return item.schedule_status === "blocked";
}

function getAgendaItemsForDate(dateKey) {
    return agendaState.agendaItems
        .filter(function (item) {
            return item.date === dateKey;
        })
        .sort(function (a, b) {
            return timeToMinutes(a.start_time) - timeToMinutes(b.start_time);
        });
}

function syncDatePicker() {
    const datePicker = document.getElementById("agendaDatePicker");
    if (datePicker) {
        datePicker.value = agendaState.selectedDate || "";
    }
}

function showAgendaToast(message, tone) {
    const container = document.getElementById("agendaToastContainer");
    if (!container) return;

    const toast = document.createElement("div");
    toast.className = `agenda-toast ${tone || "success"}`;
    toast.innerHTML = `
        <div class="agenda-toast-content">
            <span>${escapeHtml(message)}</span>
            <button type="button" class="agenda-toast-close" aria-label="Cerrar notificacion">&times;</button>
        </div>
    `;

    container.appendChild(toast);
    requestAnimationFrame(function () {
        toast.classList.add("show");
    });

    const removeToast = function () {
        toast.classList.remove("show");
        window.setTimeout(function () {
            toast.remove();
        }, 180);
    };

    toast.querySelector(".agenda-toast-close")?.addEventListener("click", removeToast);
    window.setTimeout(removeToast, 3200);
}

function openHistoryModal(appointmentId) {
    const item = agendaState.agendaItems.find(function (entry) {
        return Number(entry.id) === Number(appointmentId);
    });
    if (!item || isBlockedItem(item)) return;

    const content = document.getElementById("agendaHistoryContent");
    const backdrop = document.getElementById("agendaHistoryBackdrop");
    const modal = document.getElementById("agendaHistoryModal");
    const history = item.appointment_history || [];

    if (content) {
        if (!history.length) {
            content.innerHTML = `<div class="text-muted">No hay historial registrado para esta cita.</div>`;
        } else {
            content.innerHTML = history.map(function (entry) {
                return `
                    <div class="agenda-history-entry">
                        <div class="agenda-history-date">${escapeHtml(entry.date)}</div>
                        <div class="agenda-history-event">${escapeHtml(entry.event)}</div>
                        <span class="badge-status ${escapeHtml(entry.status)}">${escapeHtml(entry.status)}</span>
                    </div>
                `;
            }).join("");
        }
    }

    backdrop?.classList.add("show");
    modal?.classList.add("show");
}

function closeHistoryModal() {
    document.getElementById("agendaHistoryBackdrop")?.classList.remove("show");
    document.getElementById("agendaHistoryModal")?.classList.remove("show");
}

async function loadAgendaData() {
    const endpoint = window.agendaDataUrl || "/doctor/agenda/data";
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

function updateStats(items) {
    const appointments = items.filter(function (item) {
        return !isBlockedItem(item);
    });

    const stats = {
        total: appointments.length,
        confirmed: appointments.filter(function (item) { return item.status === "confirmed"; }).length,
        pending: appointments.filter(function (item) { return item.status === "pending"; }).length,
        cancelled: appointments.filter(function (item) { return item.status === "cancelled"; }).length,
    };

    const totalNode = document.getElementById("statCitasHoy");
    const confirmedNode = document.getElementById("statConfirmadas");
    const pendingNode = document.getElementById("statPendientes");
    const cancelledNode = document.getElementById("statCanceladas");

    if (totalNode) totalNode.textContent = stats.total;
    if (confirmedNode) confirmedNode.textContent = stats.confirmed;
    if (pendingNode) pendingNode.textContent = stats.pending;
    if (cancelledNode) cancelledNode.textContent = stats.cancelled;
}

function renderWeekDays() {
    const container = document.getElementById("weekDays");
    if (!container || !agendaState.currentWeekStart) return;

    container.innerHTML = "";
    const monday = new Date(agendaState.currentWeekStart);

    for (let i = 0; i < 7; i += 1) {
        const day = new Date(monday);
        day.setDate(monday.getDate() + i);
        const dateKey = formatDateKey(day);
        const isActive = dateKey === agendaState.selectedDate;

        const button = document.createElement("button");
        button.type = "button";
        button.className = `day-btn${isActive ? " active" : ""}`;
        button.dataset.date = dateKey;
        button.innerHTML = `
            <span class="day-name">${DAY_NAMES[day.getDay()]}</span>
            <span class="day-number">${day.getDate()}</span>
        `;

        button.addEventListener("click", function () {
            setSelectedDate(dateKey);
        });

        container.appendChild(button);
    }
}

function renderAppointments() {
    const container = document.getElementById("appointmentsList");
    const label = document.getElementById("agendaDayLabel");
    if (!container || !agendaState.selectedDate) return;

    const selectedDate = new Date(`${agendaState.selectedDate}T00:00:00`);
    const items = getAgendaItemsForDate(agendaState.selectedDate);

    if (label) {
        label.textContent = `${DAY_FULL_NAMES[selectedDate.getDay()]} ${selectedDate.getDate()} de ${MONTH_NAMES[selectedDate.getMonth()]}`;
    }

    updateStats(items);

    if (!items.length) {
        container.innerHTML = `
            <div class="text-center py-5 text-muted">
                <i class="bi bi-calendar-x" style="font-size: 48px; opacity: 0.3;"></i>
                <p class="mt-3 mb-0">No hay citas o bloques de horario para esta fecha.</p>
            </div>
        `;
        return;
    }

    container.innerHTML = items.map(function (item, index) {
        const delay = Math.min(index * 60, 300);

        if (isBlockedItem(item)) {
            return `
                <div class="appointment-item blocked-slot" style="animation: cardFadeInUp 0.4s ease ${delay}ms forwards; opacity: 0;">
                    <div class="appointment-accent blocked"></div>
                    <div class="appointment-body">
                        <div class="appointment-time">
                            <div class="time-start">${escapeHtml(normalizeTime(item.start_time))}</div>
                            <div class="time-end">.. ${escapeHtml(normalizeTime(item.end_time))}</div>
                        </div>
                        <div class="blocked-label">
                            <i class="bi bi-lock-fill"></i>
                            <span>${escapeHtml(item.note || "schedules.status = blocked")}</span>
                        </div>
                    </div>
                </div>
            `;
        }

        const historyButton = `
            <button class="btn-action history" type="button" data-action="history" data-id="${escapeHtml(item.id)}">
                <i class="bi bi-file-earmark-text"></i> Ver historial
            </button>
        `;

        const actionButtons = item.status === "pending"
            ? `
                ${historyButton}
                <button class="btn-action confirm" type="button" data-action="confirm" data-id="${escapeHtml(item.id)}">
                    <i class="bi bi-check-lg"></i> Confirmar
                </button>
                <button class="btn-action cancel" type="button" data-action="cancel" data-id="${escapeHtml(item.id)}">
                    <i class="bi bi-x-lg"></i> Cancelar
                </button>
            `
            : historyButton;

        return `
            <div class="appointment-item" data-id="${escapeHtml(item.id)}" style="animation: cardFadeInUp 0.4s ease ${delay}ms forwards; opacity: 0;">
                <div class="appointment-accent ${escapeHtml(item.status)}"></div>
                <div class="appointment-body">
                    <div class="appointment-time">
                        <div class="time-start">${escapeHtml(normalizeTime(item.start_time))}</div>
                        <div class="time-end">.. ${escapeHtml(normalizeTime(item.end_time))}</div>
                    </div>
                    <div class="appointment-avatar" style="background: ${escapeHtml(item.color)};">
                        ${escapeHtml(item.initials)}
                    </div>
                    <div class="appointment-info">
                        <p class="patient-name">${escapeHtml(item.patient)}</p>
                        <p class="patient-detail">${escapeHtml(item.specialty)} · reason: ${escapeHtml(item.reason)}</p>
                    </div>
                    <div class="appointment-actions">
                        <span class="badge-status ${escapeHtml(item.status)}">${escapeHtml(item.status)}</span>
                        ${actionButtons}
                    </div>
                </div>
            </div>
        `;
    }).join("");
}

function setSelectedDate(dateKey) {
    if (!dateKey) return;

    agendaState.selectedDate = dateKey;
    agendaState.currentWeekStart = getMonday(new Date(`${dateKey}T00:00:00`));
    syncDatePicker();
    renderWeekDays();
    renderAppointments();
}

async function updateAppointmentStatus(id, status) {
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
        const endpoint = `${window.appointmentUpdateBase || "/appointments"}/${id}`;

        const response = await fetch(endpoint, {
            method: "PATCH",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrfToken || "",
            },
            body: JSON.stringify({ status }),
        });

        const payload = await response.json();
        if (!response.ok) {
            throw new Error(payload.message || `Error HTTP ${response.status}`);
        }

        agendaState.agendaItems = agendaState.agendaItems.map(function (item) {
            return Number(item.id) === Number(id) ? payload.appointment : item;
        });

        renderAppointments();
        showAgendaToast(payload.message, "success");
    } catch (error) {
        console.error("No se pudo actualizar la cita:", error);
        showAgendaToast(error.message || "No fue posible actualizar la cita.", "danger");
    }
}

function handleAppointmentsClick(event) {
    const trigger = event.target.closest("[data-action]");
    if (!trigger) return;

    const id = Number(trigger.dataset.id || 0);
    const action = trigger.dataset.action;

    if (!id) return;

    if (action === "confirm") {
        updateAppointmentStatus(id, "confirmed");
        return;
    }

    if (action === "cancel") {
        updateAppointmentStatus(id, "cancelled");
        return;
    }

    if (action === "history") {
        openHistoryModal(id);
    }
}

document.addEventListener("DOMContentLoaded", function () {
    document.getElementById("appointmentsList")?.addEventListener("click", handleAppointmentsClick);
    document.getElementById("agendaDatePicker")?.addEventListener("change", function () {
        setSelectedDate(this.value);
    });
    document.getElementById("closeAgendaHistoryModal")?.addEventListener("click", closeHistoryModal);
    document.getElementById("agendaHistoryBackdrop")?.addEventListener("click", closeHistoryModal);

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            closeHistoryModal();
        }
    });

    document.getElementById("btnPrevWeek")?.addEventListener("click", function () {
        const previousWeek = new Date(agendaState.currentWeekStart);
        previousWeek.setDate(previousWeek.getDate() - 7);
        setSelectedDate(formatDateKey(previousWeek));
    });

    document.getElementById("btnNextWeek")?.addEventListener("click", function () {
        const nextWeek = new Date(agendaState.currentWeekStart);
        nextWeek.setDate(nextWeek.getDate() + 7);
        setSelectedDate(formatDateKey(nextWeek));
    });

    loadAgendaData()
        .then(function (payload) {
            agendaState.agendaItems = payload.agenda_items || [];
            const referenceDate = payload.reference_date || new Date().toISOString().slice(0, 10);
            setSelectedDate(referenceDate);
        })
        .catch(function (error) {
            console.error("No se pudo cargar la agenda:", error);
            const list = document.getElementById("appointmentsList");
            if (list) {
                list.innerHTML = `
                    <div class="text-center py-5 text-danger">
                        No fue posible cargar la agenda temporal.
                    </div>
                `;
            }
        });
});
