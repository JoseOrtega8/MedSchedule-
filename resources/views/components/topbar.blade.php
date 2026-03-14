@props([
  'title' => 'Dashboard',
  'icon' => 'bi bi-speedometer2',
  'subtitle' => null,
  'dateText' => null,
  'dynamicDate' => true,
  'showAvatarMenu' => false,
  'badgeText' => null,
  'badgeTone' => 'success',
  'avatarText' => 'A',
  'avatarColor' => null,
])

<div class="topbar">
  <div class="topbar-heading">
    <h6><i class="{{ $icon }} me-2"></i>{{ $title }}</h6>
    @if($subtitle)
      <small class="topbar-subtext">{{ $subtitle }}</small>
    @endif
  </div>
  <div class="d-flex align-items-center gap-3">
    @if(!is_null($dateText) || $dynamicDate)
      <span
        class="topbar-date"
        @if($dynamicDate) data-dynamic-date @endif
      >
        {{ $dateText ?? now()->format('d/m/Y') }}
      </span>
    @endif

    @if($badgeText)
      <span class="topbar-badge topbar-badge-{{ $badgeTone }}">
        <i class="bi bi-circle-fill"></i>
        {{ $badgeText }}
      </span>
    @endif

    @if($showAvatarMenu)
      <div class="avatar-menu" data-avatar-menu>
        <button
          class="avatar avatar-button"
          type="button"
          data-avatar-trigger
          aria-label="Abrir menú de usuario"
          aria-expanded="false"
          @if($avatarColor) style="background: {{ $avatarColor }};" @endif
        >
          {{ $avatarText }}
        </button>
        <div class="avatar-dropdown" data-avatar-dropdown>
          <button class="avatar-item" type="button" data-user-action="profile">
            <i class="bi bi-person-circle"></i>
            <span>Mi Perfil</span>
          </button>
          <button class="avatar-item" type="button" data-user-action="reset-password">
            <i class="bi bi-key"></i>
            <span>Resetear contraseña</span>
          </button>
          <button class="avatar-item logout" type="button" data-user-action="logout">
            <i class="bi bi-box-arrow-right"></i>
            <span>Cerrar Sesión</span>
          </button>
        </div>
      </div>
    @else
      <span class="avatar avatar-static" @if($avatarColor) style="background: {{ $avatarColor }};" @endif>
        {{ $avatarText }}
      </span>
    @endif
  </div>
</div>
