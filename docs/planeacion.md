# Actividad 1.1 - Planeación (MedSchedule)

## 1. Parámetros de configuración de las herramientas utilizadas

### 1.1 Laravel 12 + Blade + Vite (backend y vistas)
- Uso planeado: framework único del proyecto (monolito), controladores separados
  por rol (admin/doctor/paciente), vistas Blade servidas por el propio Laravel.
- Instalación: `composer create-project laravel/laravel medschedule`;
  configuración vía `.env` (DB_*, APP_KEY, MAIL_*).
- Implementación: convención de roles en minúscula (admin, doctor, patient).

### 1.2 Autenticación y autorización — Breeze + Spatie laravel-permission
- Uso planeado: Breeze para login/registro; Spatie para roles y permisos.
- Instalación: `composer require laravel/breeze --dev && php artisan breeze:install`
- Implementación: rate limiter en login, 3 roles con middleware por ruta.

### 1.3 Base de datos — MySQL
- Uso planeado: persistencia de usuarios, citas, horarios y logs.
- Instalación: addon gestionado por Railway; en local MySQL 8 o Docker.
- Implementación: constraint único en `schedules` para evitar horarios duplicados.

### 1.4 Pruebas automáticas — PHPUnit + Playwright
- Uso planeado: PHPUnit para unitarias/feature; Playwright para e2e.
- Instalación Playwright: `npm init playwright@latest`
- Implementación: pruebas de cache del dashboard, prueba e2e de gestión de usuarios.

### 1.5 Despliegue — Railway
- Uso planeado: hosting de la app + MySQL, deploy automático al hacer push a main.
- Instalación: repo conectado desde el panel de Railway.
- Implementación: HTTPS automático, release etiquetado.