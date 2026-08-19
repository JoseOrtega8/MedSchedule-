let allDoctors = [];
let specialties = [];
let proximasCitas = [];
let historial = [];

document.addEventListener("DOMContentLoaded", () => {
    loadDashboardData();
});

async function loadDashboardData() {
    try {
        const res = await fetch(window.dashboardDataUrl);
        const data = await res.json();

        allDoctors = data.doctors ?? [];
        specialties = data.specialties ?? [];
        proximasCitas = data.proximas_citas ?? [];
        historial = data.historial ?? [];

        renderStats(data.citas_por_status ?? {});
        renderSpecialties(specialties);
        filterDoctors(allDoctors, "");
        renderUpcomingAppointments(proximasCitas);
        renderHistory(historial);
    } catch (error) {
        console.error("Error cargando datos del dashboard:", error);
    }
}

async function agendarCita() {
    const payload = {
        doctor_id: document.getElementById("appointmentDoctor").value,
        specialty_id: document.getElementById("appointmentSpecialty").value,
        date: document.getElementById("appointmentDate").value,
        time: document.getElementById("appointmentTime").value,
        reason: document.getElementById("appointmentReason").value
    };

    console.log("Payload enviado:", payload); // 👈 Depuración

    try {
        const res = await fetch("/appointments", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(payload)
        });

        const data = await res.json();
        console.log("Respuesta backend:", data); // 👈 Depuración

        if (data.success) {
            alert("✅ Cita agendada correctamente");
            loadDashboardData();
        } else {
            alert("❌ No se pudo agendar la cita");
        }
    } catch (error) {
        console.error("Error al agendar cita:", error);
        alert("❌ Error al agendar cita");
    }
}


function renderStats(stats) {
    setText("statPending", stats.pending);
    setText("statConfirmed", stats.confirmed);
    setText("statCompleted", stats.completed);
    setText("statCancelled", stats.cancelled);
}

function setText(id, value) {
    const node = document.getElementById(id);
    if (node) node.textContent = value ?? "--";
}

function renderSpecialties(specialties) {
    const select = document.getElementById("specialtyFilter");
    select.innerHTML = `<option value="">Todas las especialidades</option>` +
        specialties.map(s => `<option value="${s.id}">${s.name}</option>`).join('');
    // Mostrar todos los doctores al inicio
    filterDoctors(allDoctors, "");
    select.addEventListener("change", () => filterDoctors(allDoctors, select.value));
}

function filterDoctors(doctors, specialtyId) {
    const list = document.getElementById("doctorList");
    const filtered = specialtyId ? doctors.filter(d => String(d.specialty_id) === String(specialtyId)) : doctors;
    list.innerHTML = filtered.map(d => `
        <div class="col-md-4">
            <div class="card shadow-sm mb-3">
                <img src="${d.photo ?? 'https://via.placeholder.com/150'}" class="card-img-top" alt="${d.name}">
                <div class="card-body">
                    <h6>${d.name}</h6>
                    <p class="text-muted">${d.specialty}</p>
                    <button class="btn btn-primary btn-sm" onclick="selectDoctor(${d.id}, ${d.specialty_id})">
                        Ver disponibilidad
                    </button>
                </div>
            </div>
        </div>
    `).join('');
}

async function loadAvailability(doctorId) {
    try {
        const res = await fetch(`/doctor/${doctorId}/availability`);
        const data = await res.json();

        const list = document.getElementById("availabilityList");
        if (!data.slots.length) {
            list.innerHTML = `<p class="text-muted">No hay horarios disponibles</p>`;
            return;
        }

        list.innerHTML = data.slots.map(s => `
            <div class="mb-2">
                <button class="btn btn-outline-primary btn-sm" 
                        onclick="selectSlot('${s.date}', '${s.time}')">
                    ${s.date} - ${s.time}
                </button>
            </div>
        `).join('');
    } catch (error) {
        console.error("Error cargando disponibilidad:", error);
    }
}

function selectDoctor(doctorId, specialtyId) {
    document.getElementById("appointmentDoctor").value = doctorId;
    document.getElementById("appointmentSpecialty").value = specialtyId;
    alert("Doctor seleccionado correctamente");
    loadAvailability(doctorId); 
}

function renderUpcomingAppointments(citas) {
    const tbody = document.getElementById("proximasCitasBody");
    if (!citas.length) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-4">No tienes citas próximas</td></tr>`;
        return;
    }
    tbody.innerHTML = citas.map(c => `
        <tr>
            <td>${c.appointment_date}</td>
            <td>${c.start_time} - ${c.end_time}</td>
            <td>${c.doctor_name}</td>
            <td><span class="badge bg-primary">${c.status}</span></td>
            <td>${['pending','confirmed'].includes(c.status) ? 
                `<button class="btn btn-sm btn-danger">Cancelar</button>` : ''}</td>
        </tr>
    `).join('');
}

function renderHistory(historial) {
    const tbody = document.getElementById("appointmentHistory");
    if (!historial.length) {
        tbody.innerHTML = `<tr><td colspan="3" class="text-center text-muted py-4">Sin historial</td></tr>`;
        return;
    }
    tbody.innerHTML = historial.map(h => `
        <tr>
            <td>${h.appointment_date}</td>
            <td>${h.doctor_name}</td>
            <td>${h.observaciones}</td>
        </tr>
    `).join('');
}

function selectSlot(date, time) {
    document.getElementById("appointmentDate").value = date;
    document.getElementById("appointmentTime").value = time;
}

// Exponer al scope global para que los onclick del HTML las encuentren
window.selectDoctor = selectDoctor;
window.selectSlot = selectSlot;
window.agendarCita = agendarCita;