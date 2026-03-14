/**
 * MedSchedule - Gestion de Especialidades (Admin Panel)
 * Uses SpecialtyController-backed session data for temporary CRUD operations.
 */

const specialtiesState = {
    specialties: [],
    editingId: null,
};

function escapeHtml(value) {
    return String(value)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#39;");
}

function normalizeText(value) {
    return String(value || "")
        .trim()
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "");
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

function loadSpecialtiesData() {
    const endpoint = window.specialtiesDataUrl || "/admin/especialidades/data";
    return requestJson(endpoint, {
        method: "GET",
        headers: {
            "Content-Type": "application/json",
        },
    });
}

function showFeedback(message, tone) {
    const feedback = document.getElementById("specialtyFeedback");
    if (!feedback) return;

    feedback.className = `alert specialty-feedback alert-${tone}`;
    feedback.textContent = message;
}

function clearFeedback() {
    const feedback = document.getElementById("specialtyFeedback");
    if (!feedback) return;

    feedback.className = "alert d-none specialty-feedback";
    feedback.textContent = "";
}

function updateStatusLabel() {
    const toggle = document.getElementById("specialtyStatusToggle");
    const label = document.getElementById("specialtyStatusLabel");
    if (!toggle || !label) return;

    label.textContent = toggle.checked ? "1 - activa" : "0 - inactiva";
}

function setNameFieldValidity(message) {
    const nameInput = document.getElementById("specialtyName");
    const feedback = document.getElementById("specialtyNameFeedback");
    if (!nameInput || !feedback) return;

    const hasError = Boolean(message);
    nameInput.classList.toggle("is-invalid", hasError);
    feedback.textContent = message || "Ya existe una especialidad con ese nombre.";
}

function validateNameUniqueness() {
    const nameInput = document.getElementById("specialtyName");
    if (!nameInput) return false;

    const value = String(nameInput.value || "").trim();
    if (!value) {
        setNameFieldValidity("");
        return false;
    }

    const duplicate = specialtiesState.specialties.find(function (specialty) {
        return normalizeText(specialty.name) === normalizeText(value)
            && specialty.id !== specialtiesState.editingId;
    });

    if (duplicate) {
        setNameFieldValidity("Ya existe una especialidad con ese nombre.");
        return true;
    }

    setNameFieldValidity("");
    return false;
}

function updateFormMode() {
    const heading = document.getElementById("specialtyFormHeading");
    const saveButton = document.getElementById("saveSpecialtyBtn");
    const cancelEditBtn = document.getElementById("cancelEditBtn");

    if (heading) {
        heading.innerHTML = specialtiesState.editingId
            ? '<i class="bi bi-pencil-square"></i><span>Editar Especialidad</span>'
            : '<i class="bi bi-plus-circle"></i><span>Nueva Especialidad</span>';
    }

    if (saveButton) {
        saveButton.textContent = specialtiesState.editingId
            ? "Actualizar Especialidad"
            : "Guardar Especialidad";
    }

    if (cancelEditBtn) {
        cancelEditBtn.classList.toggle("d-none", !specialtiesState.editingId);
    }
}

function resetForm(options) {
    const config = options || {};
    const form = document.getElementById("specialtyForm");
    const statusToggle = document.getElementById("specialtyStatusToggle");
    if (!form) return;

    form.reset();
    specialtiesState.editingId = null;

    if (statusToggle) {
        statusToggle.checked = true;
    }

    updateStatusLabel();
    updateFormMode();
    setNameFieldValidity("");

    if (!config.keepFeedback) {
        clearFeedback();
    }
}

function fillForm(specialty) {
    const nameInput = document.getElementById("specialtyName");
    const descriptionInput = document.getElementById("specialtyDescription");
    const statusToggle = document.getElementById("specialtyStatusToggle");

    if (nameInput) nameInput.value = specialty.name || "";
    if (descriptionInput) descriptionInput.value = specialty.description || "";
    if (statusToggle) {
        statusToggle.checked = Number(specialty.status) === 1;
    }

    specialtiesState.editingId = specialty.id;
    updateStatusLabel();
    updateFormMode();
    setNameFieldValidity("");
    clearFeedback();
}

function renderSpecialties() {
    const list = document.getElementById("specialtiesList");
    if (!list) return;

    if (!specialtiesState.specialties.length) {
        list.innerHTML = `
            <div class="specialty-empty-state">
                <i class="bi bi-inboxes"></i>
                <p class="mb-0">No hay especialidades registradas en este mock temporal.</p>
            </div>
        `;
        return;
    }

    const ordered = specialtiesState.specialties.slice().sort(function (a, b) {
        if (Number(b.status) !== Number(a.status)) {
            return Number(b.status) - Number(a.status);
        }

        return a.name.localeCompare(b.name, "es");
    });

    list.innerHTML = ordered.map(function (specialty) {
        const isEditing = specialty.id === specialtiesState.editingId;
        const isInactive = Number(specialty.status) !== 1;
        const doctorsLabel = specialty.doctorsCount === 1 ? "doctor" : "doctores";

        return `
            <article class="specialty-card${isEditing ? " is-selected" : ""}${isInactive ? " is-inactive" : ""}">
                <div class="specialty-icon ${escapeHtml(specialty.tone || "primary")}">
                    <i class="${escapeHtml(specialty.icon || "bi bi-hospital")}"></i>
                </div>

                <div class="specialty-main">
                    <p class="specialty-name">${escapeHtml(specialty.name)}</p>
                    <p class="specialty-meta">
                        description: ${escapeHtml(specialty.description)} · status: ${escapeHtml(specialty.status)}
                    </p>
                </div>

                <div class="specialty-side">
                    <span class="specialty-count">${escapeHtml(specialty.doctorsCount)} ${doctorsLabel}</span>

                    <div class="specialty-actions">
                        <button
                            class="specialty-action-btn edit"
                            type="button"
                            data-action="edit"
                            data-id="${escapeHtml(specialty.id)}"
                            aria-label="Editar ${escapeHtml(specialty.name)}"
                        >
                            <i class="bi bi-pencil"></i>
                        </button>

                        <button
                            class="specialty-action-btn delete"
                            type="button"
                            data-action="delete"
                            data-id="${escapeHtml(specialty.id)}"
                            aria-label="Eliminar ${escapeHtml(specialty.name)}"
                            ${specialty.doctorsCount > 0 ? "disabled" : ""}
                        >
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </article>
        `;
    }).join("");
}

async function saveSpecialty(payload) {
    const baseUrl = window.specialtiesBaseUrl || "/admin/especialidades";
    const isEditing = Boolean(specialtiesState.editingId);
    const url = isEditing ? `${baseUrl}/${specialtiesState.editingId}` : baseUrl;
    const method = isEditing ? "PATCH" : "POST";

    return requestJson(url, {
        method,
        body: JSON.stringify(payload),
    });
}

async function deleteSpecialty(id) {
    const specialty = specialtiesState.specialties.find(function (item) {
        return item.id === id;
    });
    if (!specialty) return;

    if (Number(specialty.doctorsCount) > 0) {
        showFeedback("No puedes eliminar una especialidad con doctores asignados.", "warning");
        return;
    }

    const confirmed = window.confirm(`Eliminar la especialidad "${specialty.name}"?`);
    if (!confirmed) return;

    try {
        await requestJson(`${window.specialtiesBaseUrl || "/admin/especialidades"}/${id}`, {
            method: "DELETE",
        });

        specialtiesState.specialties = specialtiesState.specialties.filter(function (item) {
            return item.id !== id;
        });

        if (specialtiesState.editingId === id) {
            resetForm({ keepFeedback: true });
        }

        renderSpecialties();
        showFeedback("Especialidad eliminada correctamente.", "success");
    } catch (error) {
        showFeedback(error.message || "No fue posible eliminar la especialidad.", "danger");
    }
}

async function handleFormSubmit(event) {
    event.preventDefault();

    const formData = new FormData(event.currentTarget);
    const duplicate = validateNameUniqueness();
    if (duplicate) {
        showFeedback("Corrige el nombre unico antes de continuar.", "danger");
        return;
    }

    const payload = {
        name: String(formData.get("name") || "").trim(),
        description: String(formData.get("description") || "").trim(),
        status: document.getElementById("specialtyStatusToggle")?.checked ? 1 : 0,
    };

    if (!payload.name) {
        showFeedback("El nombre es obligatorio.", "danger");
        return;
    }

    if (!payload.description) {
        showFeedback("La descripcion es obligatoria.", "danger");
        return;
    }

    try {
        const response = await saveSpecialty(payload);
        const specialty = response.specialty;

        if (specialtiesState.editingId) {
            specialtiesState.specialties = specialtiesState.specialties.map(function (item) {
                return item.id === specialty.id ? specialty : item;
            });
            showFeedback("Especialidad actualizada correctamente.", "success");
        } else {
            specialtiesState.specialties.unshift(specialty);
            showFeedback("Especialidad creada correctamente.", "success");
        }

        resetForm({ keepFeedback: true });
        renderSpecialties();
    } catch (error) {
        const nameErrors = error.payload?.errors?.name;
        if (Array.isArray(nameErrors) && nameErrors.length) {
            setNameFieldValidity(nameErrors[0]);
        }
        showFeedback(error.message || "No fue posible guardar la especialidad.", "danger");
    }
}

function handleListClick(event) {
    const trigger = event.target.closest("[data-action]");
    if (!trigger) return;

    const id = Number(trigger.getAttribute("data-id"));
    if (!id) return;

    const specialty = specialtiesState.specialties.find(function (item) {
        return item.id === id;
    });
    if (!specialty) return;

    const action = trigger.getAttribute("data-action");
    if (action === "edit") {
        fillForm(specialty);
        renderSpecialties();
        return;
    }

    if (action === "delete") {
        deleteSpecialty(id);
    }
}

function initializeEvents() {
    const form = document.getElementById("specialtyForm");
    const list = document.getElementById("specialtiesList");
    const newButton = document.getElementById("btnNewSpecialty");
    const cancelEditBtn = document.getElementById("cancelEditBtn");
    const nameInput = document.getElementById("specialtyName");
    const statusToggle = document.getElementById("specialtyStatusToggle");

    form?.addEventListener("submit", handleFormSubmit);
    list?.addEventListener("click", handleListClick);

    newButton?.addEventListener("click", function () {
        resetForm();
        renderSpecialties();
    });

    cancelEditBtn?.addEventListener("click", function () {
        resetForm();
        renderSpecialties();
    });

    nameInput?.addEventListener("input", validateNameUniqueness);
    statusToggle?.addEventListener("change", updateStatusLabel);
}

document.addEventListener("DOMContentLoaded", function () {
    initializeEvents();
    resetForm();

    loadSpecialtiesData()
        .then(function (payload) {
            specialtiesState.specialties = payload.specialties || [];
            renderSpecialties();
        })
        .catch(function (error) {
            console.error("No se pudieron cargar las especialidades:", error);
            specialtiesState.specialties = [];
            renderSpecialties();
            showFeedback("No fue posible cargar las especialidades temporales.", "danger");
        });
});
