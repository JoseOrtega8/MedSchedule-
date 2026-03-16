<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="csrf-token" content="{{ csrf_token() }}"/>
  <title>MedSchedule - Mi Perfil Profesional</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <script>
    window.profileDataUrl = "{{ route('doctor.profile.data') }}";
    window.profileUpdateUrl = "{{ route('doctor.profile.update') }}";
    window.profilePhotoUrl = "{{ route('doctor.profile.photo') }}";
    window.userMenuRoutes = {
      profile: "{{ route('doctor.profile') }}",
    };
  </script>
  @vite([
    'resources/css/app.css',
    'resources/css/doctor-profile.css',
    'resources/js/topbar-date.js',
    'resources/js/doctor-profile.js',
  ])
</head>
<body>

<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

<button class="mobile-toggle" onclick="toggleSidebar()">
  <i class="bi bi-list" style="font-size: 24px;"></i>
</button>

<div class="app-wrapper">
  <x-sidebar active="doctor-profile" variant="doctor" />

  <div class="content-wrapper">
    <x-topbar
      title="Mi Perfil Profesional"
      icon="bi bi-person-square"
      subtitle="Doctor / Perfil"
      :show-avatar-menu="true"
      badge-text="doctor"
      badge-tone="success"
      avatar-text="MG"
      avatar-color="#28a745"
    />

    <div class="dashboard-content p-4 doctor-profile-shell">
      <div class="row g-4 align-items-start">
        <div class="col-xl-3">
          <div class="card border-0 shadow-sm doctor-profile-summary-card" data-testid="doctor-profile-card">
            <div class="card-body p-4">
              <div class="doctor-profile-avatar" id="doctorProfileAvatar">MG</div>
              <h5 class="doctor-profile-name" id="doctorProfileFullName">Miguel Garcia</h5>
              <p class="doctor-profile-last-name" id="doctorProfileLastName">last_name: Garcia</p>
              <span class="doctor-profile-role" id="doctorProfileRole">doctor</span>

              <button class="btn btn-outline-secondary doctor-photo-btn" id="changeProfilePhotoBtn" type="button" onclick="updatePhoto()">
                <i class="bi bi-camera"></i>
                Cambiar foto
              </button>
              <input class="d-none" id="profilePhotoInput" type="file" accept="image/*" />
              <small class="doctor-photo-help" id="doctorPhotoHelp">Foto temporal para pruebas locales</small>
            </div>
          </div>
        </div>

        <div class="col-xl-9">
          <div class="card border-0 shadow-sm doctor-profile-form-panel">
            <div class="card-body p-4">
              <h6 class="doctor-profile-title mb-4">
                <i class="bi bi-pencil-square"></i>
                <span>Editar Perfil</span>
              </h6>

              <div class="alert d-none doctor-profile-feedback" id="doctorProfileFeedback" role="alert"></div>

              <form id="doctorProfileForm" data-testid="doctor-profile-form">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label doctor-profile-label" for="doctorFirstName">name</label>
                    <input class="form-control doctor-profile-input" id="doctorFirstName" name="name" type="text" required />
                  </div>
                  <div class="col-md-6">
                    <label class="form-label doctor-profile-label" for="doctorLastName">last_name</label>
                    <input class="form-control doctor-profile-input" id="doctorLastName" name="lastName" type="text" required />
                  </div>
                  <div class="col-md-6">
                    <label class="form-label doctor-profile-label" for="doctorEmail">email <span>(no editable)</span></label>
                    <input class="form-control doctor-profile-input" id="doctorEmail" name="email" type="email" readonly />
                  </div>
                  <div class="col-md-6">
                    <label class="form-label doctor-profile-label" for="doctorPhone">phone</label>
                    <input class="form-control doctor-profile-input" id="doctorPhone" name="phone" type="text" required />
                  </div>
                </div>

                <hr class="doctor-profile-divider"/>
                <p class="doctor-profile-section-label">DATOS PROFESIONALES - doctor_profiles</p>

                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label doctor-profile-label" for="doctorSpecialty">specialty_id</label>
                    <select class="form-select doctor-profile-input" id="doctorSpecialty" name="specialtyId"></select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label doctor-profile-label" for="doctorLicenseNumber">license_number <span>(unico)</span></label>
                    <input class="form-control doctor-profile-input" id="doctorLicenseNumber" name="licenseNumber" type="text" required />
                    <div class="invalid-feedback" id="doctorLicenseFeedback">Ya existe un perfil profesional con esa cedula.</div>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label doctor-profile-label" for="doctorConsultationDuration">consultation_duration <span>(min)</span></label>
                    <input class="form-control doctor-profile-input" id="doctorConsultationDuration" name="consultationDuration" type="number" min="10" max="120" step="5" required />
                  </div>
                  <div class="col-md-8">
                    <label class="form-label doctor-profile-label" for="doctorBio">bio</label>
                    <textarea class="form-control doctor-profile-input doctor-profile-textarea" id="doctorBio" name="bio" rows="3" required></textarea>
                  </div>
                </div>

                <div class="doctor-profile-actions">
                  <button class="btn btn-outline-secondary" id="cancelDoctorProfileBtn" type="button">Cancelar</button>
                  <button class="btn btn-primary" type="submit">Guardar Cambios</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="doctor-profile-toast-container" id="doctorProfileToastContainer" aria-live="polite" aria-atomic="true"></div>

</body>
</html>
