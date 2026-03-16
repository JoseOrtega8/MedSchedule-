/**
 * MedSchedule - Mis Horarios (Doctor Panel)
 * Uses ScheduleController-backed session data until the real backend is available.
 */

const schedulesState = {
    schedules: [],
    selectedDate: "",
    consultationDuration: 30,
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

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
}

async function requestJson(url, options) {
    const response = await fetch(url, {
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": csrfToken(),
            ...(options?.headers || {}),
        },
        ...options,
    });

    const payload = await response.json().catch(function () {
        return {};
    });

    if (!response.ok) {
        const error = new Error(payload.message || `Error HTTP ${response.status}`);
        error.payload = payload;
        throw error;
    }

    return payload;
}

function loadSchedulesData() {
    const endpoint = window.schedulesDataUrl || "/doctor/horarios/data";
    return requestJson(endpoint, {
        method: "GET",
        headers: {
            "Content-Type": "application/json",
        },
    });
}

function timeToMinutes(value) {
    const [hours, minutes] = String(value || "00:00").split(":").map(Number);
    return (hours * 60) + minutes;
}

function minutesToTime(totalMinutes) {
    const safeMinutes = Math.max(0, totalMinutes);
    const hours = Math.floor(safeMinutes / 60) % 24;
    const minutes = safeMinutes % 60;
    return `${String(hours).padStart(2, "0")}:${String(minutes).padStart(2, "0")}`;
}

function calculateEndTime(startTime) {
    if (!startTime) return "";
    return minutesToTime(timeToMinutes(startTime) + schedulesState.consultationDuration);
}

function showFeedback(message, tone) {
    const feedback = document.getElementById("scheduleFeedback");
    if (!feedback) return;

    feedback.className = `alert schedule-feedback alert-${tone}`;
    feedback.textContent = message;
}

function clearFeedback() {
    const feedback = document.getElementById("scheduleFeedback");
    if (!feedback) return;

    feedback.className = "alert d-none schedule-feedback";
    feedback.textContent = "";
}

function showToast(message, tone) {
    const container = document.getElementById("scheduleToastContainer");
    if (!container) return;

    const toast = document.createElement("div");
    toast.className = `schedule-toast ${tone || "success"}`;
    toast.innerHTML = `
        <div class="schedule-toast-icon">
            <i class="bi ${tone === "danger" ? "bi-exclamation-circle" : "bi-check-circle"}"></i>
        </div>
        <div class="schedule-toast-copy">${escapeHtml(message)}</div>
    `;

    container.appendChild(toast);

    window.requestAnimationFrame(function () {
        toast.classList.add("is-visible");
    });

    window.setTimeout(function () {
        toast.classList.remove("is-visible");
        window.setTimeout(function () {
            toast.remove();
        }, 220);
    }, 2600);
}

function syncEndTime() {
    const startInput = document.getElementById("scheduleStartTime");
    const endInput = document.getElementById("scheduleEndTime");
    if (!startInput || !endInput) return;

    endInput.value = calculateEndTime(startInput.value);
}

function updateDurationHint() {
    const hint = document.getElementById("scheduleDurationHint");
    if (!hint) return;

    hint.textContent = `start_time + consultation_duration (${schedulesState.consultationDuration} min)`;
}

function syncSelectedDate(dateValue, options) {
    const config = options || {};
    schedulesState.selectedDate = dateValue || "";

    const filterInput = document.getElementById("scheduleDateFilter");
    const formDateInput = document.getElementById("scheduleDate");

    if (filterInput && !config.skipFilterSync) {
        filterInput.value = schedulesState.selectedDate;
    }

    if (formDateInput && !config.skipFormSync) {
        formDateInput.value = schedulesState.selectedDate;
    }
}

function resetForm(options) {
    const config = options || {};
    const form = document.getElementById("scheduleForm");
    const startInput = document.getElementById("scheduleStartTime");

    if (!form) return;

    form.reset();
    syncSelectedDate(schedulesState.selectedDate, {
        skipFilterSync: true,
    });

    if (startInput) {
        startInput.value = "14:00";
    }

    syncEndTime();

    if (!config.keepFeedback) {
        clearFeedback();
    }
}

function renderSchedules() {
    const list = document.getElementById("schedulesList");
    if (!list) return;

    const schedulesForDate = schedulesState.schedules
        .filter(function (schedule) {
            return schedule.date === schedulesState.selectedDate;
        })
        .sort(function (a, b) {
            return timeToMinutes(a.start_time) - timeToMinutes(b.start_time);
        });

    if (!schedulesForDate.length) {
        list.innerHTML = `
            <div class="schedule-empty-state">
                <i class="bi bi-calendar-x"></i>
                <p class="mb-0">No hay horarios registrados para esta fecha.</p>
            </div>
        `;
        return;
    }

    list.innerHTML = schedulesForDate.map(function (schedule) {
        const isBlocked = schedule.status === "blocked";
        const note = schedule.note
            ? `<p class="schedule-note-text">${escapeHtml(schedule.note)}</p>`
            : "";

        return `
            <article class="schedule-card${isBlocked ? " is-blocked" : ""}">
                <div class="schedule-main">
                    <p class="schedule-time">${escapeHtml(schedule.start_time)} - ${escapeHtml(schedule.end_time)}</p>
                    <div class="schedule-meta-row">
                        <span class="schedule-status-badge ${escapeHtml(schedule.status)}">status: ${escapeHtml(schedule.status)}</span>
                        <p class="schedule-date">date: ${escapeHtml(schedule.date)}</p>
                    </div>
                    ${note}
                </div>

                <div class="schedule-actions">
                    ${isBlocked ? `
                        <span class="schedule-action-tooltip" data-tooltip="Cita agendada">
                            <button class="schedule-action-btn lock" type="button" disabled aria-label="Horario bloqueado">
                                <i class="bi bi-lock"></i>
                            </button>
                        </span>
                        <span class="schedule-action-tooltip" data-tooltip="Cita agendada">
                            <button class="schedule-action-btn delete" type="button" disabled aria-label="Horario con cita agendada">
                                <i class="bi bi-trash"></i>
                            </button>
                        </span>
                    ` : `
                        <button
                            class="schedule-action-btn lock"
                            type="button"
                            data-action="block"
                            data-id="${escapeHtml(schedule.id)}"
                            aria-label="Bloquear horario"
                        >
                            <i class="bi bi-lock"></i>
                        </button>
                        <button
                            class="schedule-action-btn delete"
                            type="button"
                            data-action="delete"
                            data-id="${escapeHtml(schedule.id)}"
                            aria-label="Eliminar horario"
                        >
                            <i class="bi bi-trash"></i>
                        </button>
                    `}
                </div>
            </article>
        `;
    }).join("");
}

function validateSchedule(payload) {
    if (!payload.date) {
        return "La fecha es obligatoria.";
    }

    if (!payload.start_time) {
        return "La hora de inicio es obligatoria.";
    }

    if (!payload.end_time) {
        return "La hora final no se pudo calcular.";
    }

    return null;
}

function upsertScheduleInState(schedule) {
    const existingIndex = schedulesState.schedules.findIndex(function (item) {
        return Number(item.id) === Number(schedule.id);
    });

    if (existingIndex === -1) {
        schedulesState.schedules.push(schedule);
        return;
    }

    schedulesState.schedules[existingIndex] = schedule;
}

async function createSchedule(payload) {
    const endpoint = window.schedulesBaseUrl || "/doctor/horarios";
    return requestJson(endpoint, {
        method: "POST",
        body: JSON.stringify({
            date: payload.date,
            start_time: payload.start_time,
        }),
    });
}

async function blockSchedule(id) {
    const endpoint = `${window.schedulesBaseUrl || "/doctor/horarios"}/${id}`;
    return requestJson(endpoint, {
        method: "PATCH",
        body: JSON.stringify({
            status: "blocked",
        }),
    });
}

async function deleteSchedule(id) {
    const endpoint = `${window.schedulesBaseUrl || "/doctor/horarios"}/${id}`;
    return requestJson(endpoint, {
        method: "DELETE",
    });
}

async function handleFormSubmit(event) {
    event.preventDefault();
    clearFeedback();

    const formData = new FormData(event.currentTarget);
    const payload = {
        date: String(formData.get("date") || ""),
        start_time: String(formData.get("start_time") || ""),
        end_time: calculateEndTime(String(formData.get("start_time") || "")),
    };

    const validationError = validateSchedule(payload);
    if (validationError) {
        showFeedback(validationError, "danger");
        return;
    }

    try {
        const response = await createSchedule(payload);
        upsertScheduleInState(response.schedule);
        syncSelectedDate(response.schedule.date);
        resetForm({ keepFeedback: true });
        renderSchedules();
        showToast(response.message || "Horario creado correctamente.", "success");
    } catch (error) {
        showFeedback(error.message || "No fue posible crear el horario.", "danger");
    }
}

async function handleListClick(event) {
    const trigger = event.target.closest("[data-action]");
    if (!trigger) return;

    const action = trigger.getAttribute("data-action");
    const id = Number(trigger.getAttribute("data-id"));

    if (!id) return;

    if (action === "block") {
        try {
            const response = await blockSchedule(id);
            upsertScheduleInState(response.schedule);
            renderSchedules();
            showToast(response.message || "Horario bloqueado correctamente.", "success");
        } catch (error) {
            showFeedback(error.message || "No fue posible bloquear el horario.", "danger");
        }

        return;
    }

    if (action === "delete") {
        const confirmed = window.confirm("Eliminar este horario disponible?");
        if (!confirmed) return;

        try {
            await deleteSchedule(id);
            schedulesState.schedules = schedulesState.schedules.filter(function (schedule) {
                return Number(schedule.id) !== id;
            });
            renderSchedules();
            showFeedback("Horario eliminado correctamente.", "success");
        } catch (error) {
            showFeedback(error.message || "No fue posible eliminar el horario.", "danger");
        }
    }
}

function initializeEvents() {
    const form = document.getElementById("scheduleForm");
    const list = document.getElementById("schedulesList");
    const startInput = document.getElementById("scheduleStartTime");
    const dateFilter = document.getElementById("scheduleDateFilter");

    if (form) {
        form.addEventListener("submit", handleFormSubmit);
    }

    if (list) {
        list.addEventListener("click", handleListClick);
    }

    if (startInput) {
        startInput.addEventListener("input", syncEndTime);
    }

    if (dateFilter) {
        dateFilter.addEventListener("change", function () {
            syncSelectedDate(this.value, {
                skipFilterSync: true,
            });
            renderSchedules();
            clearFeedback();
        });
    }
}

document.addEventListener("DOMContentLoaded", function () {
    initializeEvents();

    loadSchedulesData()
        .then(function (payload) {
            schedulesState.consultationDuration = Number(payload.doctor_profile?.consultation_duration || 30);
            schedulesState.schedules = Array.isArray(payload.schedules) ? payload.schedules : [];
            syncSelectedDate(payload.selected_date || new Date().toISOString().slice(0, 10));
            updateDurationHint();
            resetForm();
            renderSchedules();
        })
        .catch(function (error) {
            console.error("No se pudieron cargar los horarios:", error);
            schedulesState.schedules = [];
            syncSelectedDate(new Date().toISOString().slice(0, 10));
            updateDurationHint();
            resetForm({ keepFeedback: true });
            renderSchedules();
            showFeedback("No fue posible cargar los horarios del doctor.", "danger");
        });
});
