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

## 2. Plan de pruebas

El plan de pruebas de MedSchedule combina dos niveles: pruebas unitarias/feature
con PHPUnit (lógica de negocio, servicios, cache) y pruebas end-to-end con
Playwright (flujos completos de usuario en el navegador).

### 2.1 Documentación de la suite
- Ejecución de PHPUnit: `php artisan test`
- Ejecución de Playwright: `npx playwright test`
- Cobertura actual: autenticación y rate limiting, unicidad de horarios,
  cache del dashboard, gestión de usuarios (prueba e2e).
- Brecha identificada: faltan pruebas automatizadas de regresión para algunos
  casos de seguridad que hoy solo se verifican manualmente.

### 2.2 PR con spec de un módulo nuevo
Ver PR #80: spec.md del módulo de "agendado de citas sin duplicados",
generado siguiendo la metodología de Spec-Driven Development (spec → plan → tasks).

## 3. Casos de prueba (Playwright, equivalente a Katalon/Selenium)

Se usa Playwright como herramienta e2e por integrarse de forma nativa al
mismo lenguaje del proyecto (JavaScript/Vite), en vez de Katalon.

| Caso de prueba | Tipo | Estado |
|---|---|---|
| Crear usuario y editar su rol | E2E - Playwright | Automatizado |
| Login con 6 intentos fallidos → bloqueo | E2E - Playwright (planeado) | Verificado manualmente |
| Agendar el mismo horario dos veces → solo 1 éxito | E2E - Playwright (planeado) | Spec creada (ver specs/appointment-scheduling) |
| Cache de estadísticas del dashboard | Unitaria - PHPUnit | Automatizado |

PR con esta evidencia: (mismo PR #79, ver historial de commits)