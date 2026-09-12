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

## 4. Flujo de trabajo para el control de versiones (CI/CD)

### 4.1 Flujo de ramas (ya vigente)
- main: código en producción, protegido.
- develop: integración de features antes de pasar a main.
- backend / frontend: ramas de trabajo por área.

### 4.2 Pipeline de CI (GitHub Actions) — ya implementado
El repositorio cuenta con el workflow .github/workflows/ci.yml, que se
dispara en cada push a main/develop/backend/frontend y en cada Pull
Request contra main o develop. Tiene 2 jobs:

- Job 1 - lint-format: corre ESLint sobre resources/js y valida formato
  con Prettier sobre archivos JS/CSS.
- Job 2 - php-tests: levanta un servicio de MySQL 8, instala dependencias
  de Node y Composer, compila los assets con Vite (npm run build), corre
  las migraciones, y ejecuta las pruebas automatizadas con
  php artisan test (filtradas a AuthTest, ActivityLogControllerTest,
  ExampleTest y EnsureAdminRoleTest).

Regla del equipo: el pipeline corre automáticamente en cada PR y push a
las ramas principales, sirviendo como validación continua antes de
integrar código nuevo.

## 5. Estrategia de despliegue (pipeline CI/CD)

### 5.1 Estado actual
- La aplicación está desplegada en Railway, con base de datos MySQL
  gestionada por la misma plataforma.
- El despliegue a producción sigue la configuración por defecto de
  Railway conectada al repositorio de GitHub (verificar en el panel:
  Settings > Deploy Triggers si es automático por push o manual).
- HTTPS automático, release estable etiquetado (v2.0.0).

### 5.2 Estrategia propuesta
- Usar el pipeline de CI (.github/workflows/ci.yml) como filtro antes del
  despliegue: solo el código que pasa lint, tests de PHP y build de Vite
  debe llegar a main, para que el despliegue en Railway (automático o
  manual) siempre parta de una versión validada.
- Ambiente de staging: usar la rama develop conectada a un segundo
  servicio de Railway, para probar cambios antes de que lleguen a main.
- Rollback: aprovechar los releases etiquetados en GitHub (como v2.0.0)
  para poder re-desplegar una versión anterior si algo falla en producción.

PR: https://github.com/JoseOrtega8/MedSchedule-/pull/79
