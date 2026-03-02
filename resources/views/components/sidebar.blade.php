@props(['active' => ''])

<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <h5>
      <i class="bi bi-calendar2-heart"></i>
      MedSchedule
    </h5>
    <small>Sistema de Gestión de Citas Médicas</small>
  </div>

  <nav class="sidebar-menu">
    <!-- Sección: Panel Principal -->
    <div class="menu-section-title">Panel Principal</div>

    <div class="menu-item">
      <a
        class="menu-link {{ $active === 'dashboard' ? 'active' : '' }}"
        href="{{ route('dashboard') }}"
        onclick="show('dashboard'); return false;"
      >
        <div class="menu-link-content">
          <i class="bi bi-speedometer2"></i>
          <span>Dashboard</span>
        </div>
      </a>
    </div>

    <!-- Sección: Gestión -->
    <div class="menu-section-title">Gestión</div>

    <div class="menu-item">
      <div class="menu-link" onclick="toggleSubmenu('usuarios-submenu', this)">
        <div class="menu-link-content">
          <i class="bi bi-people"></i>
          <span>Usuarios</span>
        </div>
      </div>
    </div>

    <div class="menu-item">
      <div class="menu-link" onclick="toggleSubmenu('citas-submenu', this)">
        <div class="menu-link-content">
          <i class="bi bi-calendar-check"></i>
          <span>Citas Médicas</span>
        </div>
      </div>
    </div>

    <div class="menu-item">
      <div class="menu-link" onclick="toggleSubmenu('doctores-submenu', this)">
        <div class="menu-link-content">
          <i class="bi bi-person-badge"></i>
          <span>Doctores</span>
        </div>
      </div>
    </div>

    <!-- Sección: Información -->
    <div class="menu-section-title">Información</div>

    <div class="menu-item">
      <a
        class="menu-link {{ $active === 'about' ? 'active' : '' }}"
        href="{{ route('about') }}"
        onclick="show('about'); return false;"
      >
        <div class="menu-link-content">
          <i class="bi bi-info-circle"></i>
          <span>Acerca de</span>
        </div>
      </a>
    </div>
  </nav>
</aside>
