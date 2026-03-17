@props([
'title' => 'Mi Perfil Profesional',
'icon' => 'bi bi-person-square',
'subtitle' => 'Doctor / Perfil',
'dynamicDate' => true,
'showAvatarMenu' => true,
'badgeText' => 'doctor',
'badgeTone' => 'success',
'avatarText' => strtoupper(substr(Auth::user()->name,0,1) . substr(Auth::user()->last_name,0,1)),
'avatarColor' => '#28a745',
])

<div class="topbar">
	<div class="topbar-heading">
		<h6><i class="{{ $icon }} me-2"></i>{{ $title }}</h6>
		@if($subtitle)
		<small class="topbar-subtext">{{ $subtitle }}</small>
		@endif
	</div>
	<div class="d-flex align-items-center gap-3">
		@if($dynamicDate)
		<span class="topbar-date" data-dynamic-date>
			{{ now()->format('d/m/Y') }}
		</span>
		@endif

		@if($badgeText)
		<span class="topbar-badge topbar-badge-{{ $badgeTone }}">
			<i class="bi bi-circle-fill"></i>
			{{ $badgeText }}
		</span>
		@endif

		<div class="avatar-menu" data-avatar-menu>
			<button
				class="avatar avatar-button"
				type="button"
				data-avatar-trigger
				aria-label="Abrir menú de usuario"
				aria-expanded="false"
				style="background: {{ $avatarColor }};">
				{{ $avatarText }}
			</button>
			<div class="avatar-dropdown" data-avatar-dropdown>
				<a class="avatar-item" href="{{ route('doctor.profile') }}">
					<i class="bi bi-person-circle"></i>
					<span>Mi Perfil</span>
				</a>
				<a class="avatar-item" href="{{ route('doctor.profile') }}">
					<i class="bi bi-key"></i>
					<span>Cambiar contraseña</span>
				</a>
				<form method="POST" action="{{ route('logout') }}">
					@csrf
					<button class="avatar-item logout" type="submit">
						<i class="bi bi-box-arrow-right"></i>
						<span>Cerrar Sesión</span>
					</button>
				</form>
			</div>
		</div>
	</div>
</div>