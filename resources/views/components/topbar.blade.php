@props([
  'title' => 'Dashboard',
  'icon' => 'bi bi-speedometer2',
  'dateText' => 'Cargando fecha...',
  'dynamicDate' => true,
  'showAvatarMenu' => false,
])

<div class="topbar">
  <h6><i class="{{ $icon }} me-2"></i>{{ $title }}</h6>
  <div class="d-flex align-items-center gap-3">
    @if($dateText)
      <span
        class="topbar-date"
        @if($dynamicDate) data-dynamic-date="true" @endif
      >
        {{ $dateText }}
      </span>
    @endif

    @if($showAvatarMenu)
      <div class="avatar-menu" id="avatarMenu">
        <button class="avatar avatar-button" type="button" onclick="toggleAvatarMenu(event)" aria-label="Abrir menú de usuario">
          A
        </button>
        <div class="avatar-dropdown">
          <button class="avatar-item" type="button" onclick="show('perfil'); closeAvatarMenu();">
            <i class="bi bi-person-circle"></i>
            <span>Mi Perfil</span>
          </button>
          <button class="avatar-item" type="button" onclick="show('reset'); closeAvatarMenu();">
            <i class="bi bi-key"></i>
            <span>Resetear contraseña</span>
          </button>
          <button class="avatar-item logout" type="button" onclick="closeAvatarMenu(); alert('Cerrando sesión...');">
            <i class="bi bi-box-arrow-right"></i>
            <span>Cerrar Sesión</span>
          </button>
        </div>
      </div>
    @endif
  </div>
</div>
