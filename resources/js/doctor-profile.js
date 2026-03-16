/**
 * MedSchedule - Mi Perfil Profesional (Doctor Panel)
 * Uses DoctorProfileController-backed session data until the real backend exists.
 */

const doctorProfileState = {
    profile: null,
    specialties: [],
};

const DEFAULT_SPECIALTIES = [
    { id: "cardiologia", label: "Cardiologia" },
    { id: "oftalmologia", label: "Oftalmologia" },
    { id: "neurologia", label: "Neurologia" },
    { id: "pediatria", label: "Pediatria" },
];

function toggleSidebar() {
    const sidebar = document.getElementById("sidebar");
    const overlay = document.getElementById("overlay");
    if (sidebar) sidebar.classList.toggle("show");
    if (overlay) overlay.classList.toggle("show");
}

window.toggleSidebar = toggleSidebar;

function updatePhoto() {
    const photoInput = document.getElementById("profilePhotoInput");
    if (photoInput) {
        photoInput.click();
    }
}

window.updatePhoto = updatePhoto;

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

function loadDoctorProfileData() {
    const endpoint = window.profileDataUrl || "/doctor/perfil/data";
    return requestJson(endpoint, {
        method: "GET",
        headers: {
            "Content-Type": "application/json",
        },
    });
}

function mapDoctorFromApi(doctor) {
    return {
        id: doctor.id,
        name: doctor.name || "",
        lastName: doctor.last_name || "",
        email: doctor.email || "",
        phone: doctor.phone || "",
        role: doctor.role || "doctor",
        specialtyId: doctor.specialty_id || "",
        licenseNumber: doctor.license_number || "",
        consultationDuration: Number(doctor.consultation_duration || 30),
        bio: doctor.bio || "",
        photoDataUrl: doctor.photo_data_url || null,
    };
}

function computeInitials(name, lastName) {
    const first = String(name || "").trim().charAt(0);
    const last = String(lastName || "").trim().charAt(0);
    return `${first}${last}`.toUpperCase() || "DR";
}

function escapeHtml(value) {
    return String(value)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#39;");
}

function showFeedback(message, tone) {
    const feedback = document.getElementById("doctorProfileFeedback");
    if (!feedback) return;

    feedback.className = `alert doctor-profile-feedback alert-${tone}`;
    feedback.textContent = message;
}

function clearFeedback() {
    const feedback = document.getElementById("doctorProfileFeedback");
    if (!feedback) return;

    feedback.className = "alert d-none doctor-profile-feedback";
    feedback.textContent = "";
}

function showToast(message, tone) {
    const container = document.getElementById("doctorProfileToastContainer");
    if (!container) return;

    const toast = document.createElement("div");
    toast.className = `doctor-profile-toast ${tone || "success"}`;
    toast.innerHTML = `
        <div class="doctor-profile-toast-icon">
            <i class="bi ${tone === "danger" ? "bi-exclamation-circle" : "bi-check-circle"}"></i>
        </div>
        <div class="doctor-profile-toast-copy">${escapeHtml(message)}</div>
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

function setLicenseFieldValidity(message) {
    const input = document.getElementById("doctorLicenseNumber");
    const feedback = document.getElementById("doctorLicenseFeedback");
    if (!input || !feedback) return;

    const hasError = Boolean(message);
    input.classList.toggle("is-invalid", hasError);
    feedback.textContent = message || "Ya existe un perfil profesional con esa cedula.";
}

function renderSpecialtyOptions() {
    const select = document.getElementById("doctorSpecialty");
    if (!select) return;

    select.innerHTML = doctorProfileState.specialties.map(function (specialty) {
        return `<option value="${escapeHtml(specialty.id)}">${escapeHtml(specialty.label)}</option>`;
    }).join("");
}

function renderProfileSummary() {
    const profile = doctorProfileState.profile;
    if (!profile) return;

    const avatar = document.getElementById("doctorProfileAvatar");
    const fullName = document.getElementById("doctorProfileFullName");
    const lastName = document.getElementById("doctorProfileLastName");
    const role = document.getElementById("doctorProfileRole");
    const photoHelp = document.getElementById("doctorPhotoHelp");
    const initials = computeInitials(profile.name, profile.lastName);

    if (avatar) {
        avatar.textContent = initials;
        avatar.classList.toggle("has-photo", Boolean(profile.photoDataUrl));
        avatar.style.backgroundImage = profile.photoDataUrl ? `url("${profile.photoDataUrl}")` : "";
    }

    if (fullName) {
        fullName.textContent = `${profile.name} ${profile.lastName}`.trim();
    }

    if (lastName) {
        lastName.textContent = `last_name: ${profile.lastName}`;
    }

    if (role) {
        role.textContent = profile.role || "doctor";
    }

    if (photoHelp) {
        photoHelp.textContent = profile.photoDataUrl
            ? "Foto temporal cargada en el backend de sesion"
            : "Foto temporal para pruebas locales";
    }

    document.querySelectorAll("[data-avatar-trigger]").forEach(function (button) {
        button.textContent = initials;
    });
}

function populateForm() {
    const profile = doctorProfileState.profile;
    if (!profile) return;

    const mapping = {
        doctorFirstName: profile.name,
        doctorLastName: profile.lastName,
        doctorEmail: profile.email,
        doctorPhone: profile.phone,
        doctorSpecialty: profile.specialtyId,
        doctorLicenseNumber: profile.licenseNumber,
        doctorConsultationDuration: String(profile.consultationDuration),
        doctorBio: profile.bio,
    };

    Object.entries(mapping).forEach(function ([id, value]) {
        const input = document.getElementById(id);
        if (input) {
            input.value = value ?? "";
        }
    });

    setLicenseFieldValidity("");
}

function validateProfile(payload) {
    if (!payload.name) return "El nombre es obligatorio.";
    if (!payload.lastName) return "El apellido es obligatorio.";
    if (!payload.phone) return "El telefono es obligatorio.";
    if (!/^[0-9+\-\s()]{8,20}$/.test(payload.phone)) {
        return "El telefono no tiene un formato valido.";
    }
    if (!payload.specialtyId) return "La especialidad es obligatoria.";
    if (!payload.licenseNumber) return "La cedula profesional es obligatoria.";
    if (!payload.consultationDuration || Number.isNaN(payload.consultationDuration)) {
        return "La duracion de consulta es obligatoria.";
    }
    if (payload.consultationDuration < 10 || payload.consultationDuration > 120) {
        return "La duracion de consulta debe estar entre 10 y 120 minutos.";
    }
    if (!payload.bio || payload.bio.length < 20) {
        return "La bio debe contener al menos 20 caracteres.";
    }

    return null;
}

async function saveDoctorProfile(payload) {
    const endpoint = window.profileUpdateUrl || "/doctor/perfil";
    return requestJson(endpoint, {
        method: "PATCH",
        body: JSON.stringify({
            name: payload.name,
            last_name: payload.lastName,
            phone: payload.phone,
            specialty_id: payload.specialtyId,
            license_number: payload.licenseNumber,
            consultation_duration: payload.consultationDuration,
            bio: payload.bio,
        }),
    });
}

async function saveDoctorPhoto(photoDataUrl) {
    const endpoint = window.profilePhotoUrl || "/doctor/perfil/photo";
    return requestJson(endpoint, {
        method: "POST",
        body: JSON.stringify({
            photo_data_url: photoDataUrl,
        }),
    });
}

async function handleProfileSubmit(event) {
    event.preventDefault();
    clearFeedback();
    setLicenseFieldValidity("");

    const formData = new FormData(event.currentTarget);
    const payload = {
        ...doctorProfileState.profile,
        name: String(formData.get("name") || "").trim(),
        lastName: String(formData.get("lastName") || "").trim(),
        email: String(formData.get("email") || "").trim(),
        phone: String(formData.get("phone") || "").trim(),
        specialtyId: String(formData.get("specialtyId") || "").trim(),
        licenseNumber: String(formData.get("licenseNumber") || "").trim().toUpperCase(),
        consultationDuration: Number(formData.get("consultationDuration")),
        bio: String(formData.get("bio") || "").trim(),
    };

    const validationError = validateProfile(payload);
    if (validationError) {
        showFeedback(validationError, "danger");
        return;
    }

    try {
        const response = await saveDoctorProfile(payload);
        doctorProfileState.profile = mapDoctorFromApi(response.doctor);
        populateForm();
        renderProfileSummary();
        showToast(response.message || "Perfil actualizado correctamente.", "success");
    } catch (error) {
        const licenseErrors = error.payload?.errors?.license_number;
        if (Array.isArray(licenseErrors) && licenseErrors.length) {
            setLicenseFieldValidity(licenseErrors[0]);
        }
        showFeedback(error.message || "No fue posible actualizar el perfil.", "danger");
    }
}

function handleCancel() {
    populateForm();
    clearFeedback();
}

function handlePhotoSelection(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    if (!file.type.startsWith("image/")) {
        showFeedback("El archivo seleccionado no es una imagen valida.", "danger");
        event.target.value = "";
        return;
    }

    if (file.size > 2 * 1024 * 1024) {
        showFeedback("La imagen debe pesar 2 MB o menos.", "danger");
        event.target.value = "";
        return;
    }

    const reader = new FileReader();
    reader.onload = async function () {
        try {
            const response = await saveDoctorPhoto(String(reader.result || ""));
            doctorProfileState.profile = mapDoctorFromApi(response.doctor);
            renderProfileSummary();
            showToast(response.message || "Foto de perfil actualizada correctamente.", "success");
        } catch (error) {
            showFeedback(error.message || "No fue posible actualizar la foto.", "danger");
        } finally {
            event.target.value = "";
        }
    };
    reader.readAsDataURL(file);
}

function initializeEvents() {
    const form = document.getElementById("doctorProfileForm");
    const cancelButton = document.getElementById("cancelDoctorProfileBtn");
    const photoInput = document.getElementById("profilePhotoInput");
    const licenseInput = document.getElementById("doctorLicenseNumber");

    if (form) {
        form.addEventListener("submit", handleProfileSubmit);
    }

    if (cancelButton) {
        cancelButton.addEventListener("click", handleCancel);
    }

    if (photoInput) {
        photoInput.addEventListener("change", handlePhotoSelection);
    }

    if (licenseInput) {
        licenseInput.addEventListener("input", function () {
            setLicenseFieldValidity("");
        });
    }
}

document.addEventListener("DOMContentLoaded", function () {
    initializeEvents();

    loadDoctorProfileData()
        .then(function (payload) {
            doctorProfileState.specialties = payload.specialties || DEFAULT_SPECIALTIES;
            doctorProfileState.profile = mapDoctorFromApi(payload.doctor || {});

            renderSpecialtyOptions();
            populateForm();
            renderProfileSummary();
        })
        .catch(function (error) {
            console.error("No se pudo cargar el perfil del doctor:", error);
            doctorProfileState.specialties = DEFAULT_SPECIALTIES;
            showFeedback("No fue posible cargar el perfil profesional.", "danger");
        });
});
