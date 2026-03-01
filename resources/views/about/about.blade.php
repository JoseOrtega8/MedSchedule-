<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>MedSchedule</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <script>
    window.medScheduleRoutes = {
      dashboard: "{{ route('dashboard') }}",
      about: "{{ route('about') }}"
    };
  </script>
  @vite(['resources/css/app.css', 'resources/js/about.js', 'resources/js/topbar-date.js'])
</head>
<body>

<!-- Overlay para móvil -->
<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

<!-- Botón hamburguesa móvil -->
<button class="mobile-toggle" onclick="toggleSidebar()">
  <i class="bi bi-list" style="font-size: 24px;"></i>
</button>

<div class="app-wrapper">
  <!-- SIDEBAR -->
  <x-sidebar active="about" />

  <!-- CONTENT WRAPPER -->
  <div class="content-wrapper">
    <x-topbar
      title="Acerca de"
      icon="bi bi-info-circle"
      date-text="Miércoles, 12 Feb 2026"
      :show-avatar-menu="true"
    />

  <div class="container py-5" style="max-width:800px">

    <section class="about-hero reveal-section mb-4">
      <div class="about-hero-content">
        <h1 class="about-hero-title">
          <i class="bi bi-calendar2-heart"></i>
          <span>MedSchedule</span>
        </h1>
        <p class="about-hero-subtitle mb-0">Sistema de Gestión de Citas Médicas</p>
      </div>
    </section>

    <div class="card border-0 shadow-sm mb-4 reveal-section">
      <div class="card-body p-4">
        <h2 class="fw-bold mb-3"><i class="bi bi-info-circle me-2 text-primary"></i>Descripción general del proyecto</h2>
        <p class="txt-proyecto"><strong>MedSchedule</strong> es una aplicación web profesional orientada a la administración integral de citas médicas entre pacientes, doctores y administradores. El sistema permite registrar usuarios, gestionar agendas médicas, programar citas, llevar historiales y generar registros de auditoría.</p>
        <p class="txt-proyecto">La plataforma busca optimizar la organización de servicios médicos, reducir tiempos de espera y mejorar la experiencia del usuario mediante una interfaz intuitiva, segura y escalable.</p>
      </div>
    </div>

    <h5 class="fw-bold mb-3 reveal-section"><i class="bi bi-people me-2 text-primary"></i>Equipo de desarrollo</h5>
    <div class="row g-3 mb-4 reveal-section">
      <div class="col-md-6">
        <div class="member-card">
          <div class="member-avatar bg-member-avatar-1">JC</div>
          <h6 class="fw-bold mb-1">Jose Carlos Calles Ortega</h6>
          <small class="text-muted d-block mb-2">Desarrollador Backend</small>
          <a href="javascript:void(0)" onclick="abrirLink('https://github.com/JoseOrtega8')" class="github-link"><i class="bi bi-github me-1"></i>GitHub</a>
        </div>
      </div>
      <div class="col-md-6">
        <div class="member-card">
          <div class="member-avatar bg-member-avatar-2">UC</div>
          <h6 class="fw-bold mb-1">Ulises Castro Domínguez</h6>
          <small class="text-muted d-block mb-2">Desarrollador Frontend</small>
          <a href="javascript:void(0)" onclick="abrirLink('https://github.com/usuario')" class="github-link"><i class="bi bi-github me-1"></i>GitHub</a>
        </div>
      </div>
      <div class="col-md-6">
        <div class="member-card">
          <div class="member-avatar bg-member-avatar-4">LA</div>
          <h6 class="fw-bold mb-1">Luis Angel Andrade Silva</h6>
          <small class="text-muted d-block mb-2">Desarrollador Backend</small>
          <a href="javascript:void(0)" onclick="abrirLink('')" class="github-link"><i class="bi bi-github me-1"></i>GitHub</a>
        </div>
      </div>
      <div class="col-md-6">
        <div class="member-card">
          <div class="member-avatar bg-member-avatar-3">JI</div>
          <h6 class="fw-bold mb-1">Jose Ramon Ibarra Fontes</h6>
          <small class="text-muted d-block mb-2">Desarrollador Frontend</small>
          <a href="javascript:void(0)" onclick="abrirLink('https://github.com/ramonibr')" class="github-link"><i class="bi bi-github me-1"></i>GitHub</a>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm mb-4 reveal-section">
      <div class="card-body p-4">
        <h5 class="fw-bold mb-3"><i class="bi bi-stack me-2 text-primary"></i>Stack tecnológico</h5>
        <div class="tech-badges">
          <span class="tech-badge"><i class="bi bi-server me-1"></i>Laravel 12</span>
          <span class="tech-badge"><i class="bi bi-database me-1"></i>MySQL 8</span>
          <span class="tech-badge"><i class="bi bi-bootstrap me-1"></i>Bootstrap 5.3</span>
          <span class="tech-badge"><i class="bi bi-filetype-html me-1"></i>Blade Templates</span>
          <span class="tech-badge"><i class="bi bi-diagram-3 me-1"></i>Eloquent ORM</span>
          <span class="tech-badge"><i class="bi bi-calendar-event me-1"></i>Google Calendar API</span>
          <span class="tech-badge"><i class="bi bi-git me-1"></i>Git + GitHub</span>
          <span class="tech-badge"><i class="bi bi-shield-check me-1"></i>MIT License</span>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm reveal-section">
      <div class="card-body p-4">
        <h5 class="fw-bold mb-2"><i class="bi bi-file-earmark-text me-2 text-primary"></i><strong>MIT License</strong></h5>
        <p class="txt-licencia mb-2">Copyright (c) 2026 Jose Carlos Calles Ortega, Ulises Castro Domínguez, Luis Angel Andrade Silva, Jose Ramon Ibarra Fontes.</p>
        <p class="txt-licencia">Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:</p>
        <p class="txt-licencia">The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.</p>
        <p class="txt-licencia">THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.</p>
      </div>
    </div>

    <!--p!-- class="text-center text-muted mt-4" style="font-size:12px">
      Universidad Tecnológica de Hermosillo · Ingeniería en Desarrollo y Gestión de Software · TIDSM8-2 · Prof. Iván Rogelio Chenoweth
    </!--p!-->
  </div>
</div>

</body>
</html>
