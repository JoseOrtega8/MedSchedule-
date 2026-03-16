@props([
    'active' => '',
    'variant' => 'default',
])

@if ($variant === 'doctor')
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h5>
                <i class="bi bi-calendar2-heart"></i>
                MedSchedule
            </h5>
            <small>Panel Doctor</small>
        </div>

        <nav class="sidebar-menu">
            <div class="menu-section-title">Mi Practica</div>

            <div class="menu-item">
                <a class="menu-link {{ $active === 'doctor-dashboard' ? 'active' : '' }}" href="#">
                    <div class="menu-link-content">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </div>
                </a>
            </div>

            <div class="menu-item">
                <a class="menu-link {{ $active === 'doctor-agenda' ? 'active' : '' }}" href="{{ route('doctor.agenda') }}">
                    <div class="menu-link-content">
                        <i class="bi bi-calendar-week"></i>
                        <span>Mi Agenda</span>
                    </div>
                </a>
            </div>

            <div class="menu-item">
                <a class="menu-link {{ $active === 'doctor-schedules' ? 'active' : '' }}" href="{{ route('doctor.schedules') }}">
                    <div class="menu-link-content">
                        <i class="bi bi-clock"></i>
                        <span>Mis Horarios</span>
                    </div>
                </a>
            </div>

            <div class="menu-item">
                <a class="menu-link {{ $active === 'doctor-patients' ? 'active' : '' }}" href="#">
                    <div class="menu-link-content">
                        <i class="bi bi-people"></i>
                        <span>Mis Pacientes</span>
                    </div>
                </a>
            </div>

            <div class="menu-item">
                <a class="menu-link {{ $active === 'doctor-history' ? 'active' : '' }}" href="#">
                    <div class="menu-link-content">
                        <i class="bi bi-clock-history"></i>
                        <span>Historial</span>
                    </div>
                </a>
            </div>
        </nav>
    </aside>
@elseif ($variant === 'admin')
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h5>
                <i class="bi bi-calendar2-heart"></i>
                MedSchedule
            </h5>
            <small>Panel Admin</small>
        </div>

        <nav class="sidebar-menu">
            <div class="menu-section-title">General</div>

            <div class="menu-item">
                <a class="menu-link {{ $active === 'admin-dashboard' ? 'active' : '' }}" href="{{ route('dashboard') }}">
                    <div class="menu-link-content">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </div>
                </a>
            </div>

            <div class="menu-section-title">Gestion</div>

            <div class="menu-item">
                <a class="menu-link {{ $active === 'admin-users' ? 'active' : '' }}" href="#">
                    <div class="menu-link-content">
                        <i class="bi bi-people"></i>
                        <span>Usuarios</span>
                    </div>
                </a>
            </div>

            <div class="menu-item">
                <a class="menu-link {{ $active === 'admin-specialties' ? 'active' : '' }}" href="{{ route('admin.specialties') }}">
                    <div class="menu-link-content">
                        <i class="bi bi-heart-pulse"></i>
                        <span>Especialidades</span>
                    </div>
                </a>
            </div>

            <div class="menu-item">
                <a class="menu-link {{ $active === 'admin-stats' ? 'active' : '' }}" href="#">
                    <div class="menu-link-content">
                        <i class="bi bi-bar-chart"></i>
                        <span>Estadisticas</span>
                    </div>
                </a>
            </div>

            <div class="menu-section-title">Seguridad</div>

            <div class="menu-item">
                <a class="menu-link {{ $active === 'admin-rbac' ? 'active' : '' }}" href="{{ route('admin.rbac') }}">
                    <div class="menu-link-content">
                        <i class="bi bi-shield-lock"></i>
                        <span>Roles y Permisos</span>
                    </div>
                </a>
            </div>

            <div class="menu-item">
                <a class="menu-link {{ $active === 'admin-logs' ? 'active' : '' }}" href="{{ route('admin.logs') }}">
                    <div class="menu-link-content">
                        <i class="bi bi-card-list"></i>
                        <span>Logs</span>
                    </div>
                </a>
            </div>

            <div class="menu-section-title">Sistema</div>

            <div class="menu-item">
                <a class="menu-link {{ $active === 'about' ? 'active' : '' }}" href="{{ route('about') }}">
                    <div class="menu-link-content">
                        <i class="bi bi-info-circle"></i>
                        <span>About</span>
                    </div>
                </a>
            </div>
        </nav>
    </aside>
@else
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h5>
                <i class="bi bi-calendar2-heart"></i>
                MedSchedule
            </h5>
            <small>Sistema de Gestion de Citas Medicas</small>
        </div>

        <nav class="sidebar-menu">
            <div class="menu-section-title">Panel Principal</div>

            <div class="menu-item">
                <a class="menu-link {{ $active === 'dashboard' ? 'active' : '' }}" href="{{ route('dashboard') }}">
                    <div class="menu-link-content">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </div>
                </a>
            </div>

            <div class="menu-section-title">Gestion</div>

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
                        <span>Citas Medicas</span>
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

            <div class="menu-section-title">Informacion</div>

            <div class="menu-item">
                <a class="menu-link {{ $active === 'about' ? 'active' : '' }}" href="{{ route('about') }}">
                    <div class="menu-link-content">
                        <i class="bi bi-info-circle"></i>
                        <span>Acerca de</span>
                    </div>
                </a>
            </div>
        </nav>
    </aside>
@endif
