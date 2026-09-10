# Implementation Plan: Ampliación de cobertura de pruebas E2E por rol

**Branch**: `001-pruebas-e2e` | **Date**: 2026-09-09 | **Spec**: `specs/001-pruebas-e2e/spec.md`

**Input**: Feature specification from `/specs/001-pruebas-e2e/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command; its definition describes the execution workflow.

## Summary

Ampliar la cobertura de pruebas E2E de MedSchedule de 1 flujo (gestión de usuarios) a 5, agregando
autenticación y control de acceso por rol, agenda del doctor, agendado/cancelación de cita del
paciente y CRUD de especialidades del administrador. El enfoque técnico reutiliza Playwright, ya
instalado y configurado para el flujo existente (`tests/playwright_gestion_usuarios/`), replicando
su patrón (una carpeta por flujo, login helper por rol, evidencia por captura de pantalla) y ajusta
el paso de pruebas del pipeline de integración continua para que ejecute Playwright y deje de
ocultar fallos con `|| true`.

## Technical Context

**Language/Version**: JavaScript (Node.js 20, la misma versión ya usada en el job de CI) para las
pruebas Playwright; PHP 8.2 / Laravel 11 para la aplicación bajo prueba (sin cambios de código de
aplicación en esta funcionalidad).

**Primary Dependencies**: `@playwright/test` `^1.62.1` (versión fijada, ya presente en el flujo
existente); Spatie Laravel-permission (ya instalado) como origen de los roles `admin`/`doctor`/
`patient` contra los que se valida el control de acceso.

**Storage**: MySQL 8.0, la misma base de datos de pruebas que ya usa el job `php-tests` del
pipeline (`medschedule_test`); las pruebas E2E requieren que esa base tenga las migraciones
aplicadas y los usuarios/roles/datos de dominio (doctor, especialidad, horario) sembrados antes de
correr.

**Testing**: Playwright (`@playwright/test`) para los cinco flujos E2E; no se agregan pruebas
PHPUnit nuevas como parte de esta funcionalidad (el suite de PHPUnit existente y sus 15 fallos
documentados quedan fuera de alcance, según la especificación).

**Target Platform**: Navegador Chromium en modo headless, ejecutado dentro de un runner
`ubuntu-latest` de GitHub Actions, contra una instancia de la aplicación levantada con
`php artisan serve` en el mismo job.

**Project Type**: Aplicación web monolítica Laravel (no hay separación frontend/backend como
proyectos independientes); las pruebas E2E viven como carpetas hermanas bajo `tests/`.

**Performance Goals**: Cada prueba E2E debe completarse dentro del `timeout` de 30 segundos ya
configurado en `playwright.config.js`; el conjunto de los 5 flujos debe poder ejecutarse en un solo
job de CI sin superar el tiempo máximo típico de un job de GitHub Actions (no se fija aquí un
número exacto porque depende del runner, pero el diseño evita pasos manuales o esperas fijas
largas).

**Constraints**: `retries: 0` (ya configurado): las pruebas deben ser deterministas, sin
dependencia de temporización de red externa. Cada prueba debe ser independiente de las demás
(no reutiliza datos creados por otra prueba E2E), para permitir ejecución en paralelo a futuro y
para que un fallo aislado no oculte o cause otros fallos en cascada.

**Scale/Scope**: 4 flujos E2E nuevos (autenticación/RBAC, agenda doctor, citas paciente,
especialidades admin) más el ajuste del paso de pruebas del pipeline; no incluye corregir los 15
fallos de PHPUnit documentados ni instrumentar cobertura de código.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **Principio VI (Spec-Driven Development)**: PASA. Esta funcionalidad entra por especificación
  (`spec.md`) y plan antes que por código; ningún archivo bajo `tests/` se modifica como parte de
  este documento.
- **Principio I (Estilo de código)**: PASA con condición para la implementación futura: los
  comentarios de las pruebas Playwright y PHP deben ir en español y los nombres de funciones en
  snake_case, siguiendo el estilo ya usado en `tests/playwright_gestion_usuarios/gestion-usuarios.spec.js`.
- **Principio II (Manejo de errores)**: PASA. Las pruebas E2E deben afirmar explícitamente los
  códigos de estado esperados (por ejemplo, 403 en los casos de acceso denegado) en vez de solo
  verificar que "algo falló".
- **Principio III (Gestión de secretos)**: PASA. Las credenciales de los usuarios de prueba
  (admin/doctor/patient) provienen del seeder del entorno local o de CI, nunca se hardcodean
  valores reales ni se leen de `.env` en el código de la prueba más allá de `APP_URL`.
- **Principio IV (Validación de entrada)**: PASA. Los escenarios de rechazo (horario ocupado,
  especialidad duplicada, cancelación ajena) verifican que el sistema ya validado por la
  aplicación se comporta como se espera; esta funcionalidad no agrega validación nueva, la
  verifica.
- **Principio V (Exposición de errores al cliente)**: PASA. Los escenarios de acceso denegado
  verifican un código de estado 403 genérico, no un mensaje que exponga detalles internos.
- **Principio VII (Convenciones de control de versiones)**: PASA. El trabajo de esta
  funcionalidad se entrega en la rama `feat/unidad-docs-sdd` con commits convencionales, conforme
  a las restricciones globales de esta entrega.

No se identificaron violaciones que requieran justificación en la tabla de Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/001-pruebas-e2e/
├── plan.md              # Este archivo (/speckit-plan command output)
└── tasks.md             # Fase 2 (/speckit-tasks command)
```

No se generan `research.md`, `data-model.md`, `quickstart.md` ni `contracts/` para esta
funcionalidad: no hay incógnitas técnicas que requieran investigación previa (Playwright y su
patrón de uso ya existen en el repositorio), no se agregan entidades de datos nuevas (se reutilizan
los modelos existentes) y no expone contratos de API nuevos.

### Source Code (repository root)

MedSchedule es una aplicación Laravel de un solo proyecto (no hay separación en proyectos
`frontend/`/`backend/` ni `ios/`/`android/`), por lo que no aplica ninguna de las opciones
genéricas de estructura del template. La estructura real que usa esta funcionalidad es:

```text
tests/
├── Feature/                              # PHPUnit existente, no tocado por esta funcionalidad
├── Unit/                                 # PHPUnit existente, no tocado por esta funcionalidad
├── playwright_gestion_usuarios/          # Flujo E2E existente (referencia de estilo)
│   └── gestion-usuarios.spec.js
├── playwright_autenticacion_rbac/        # Nuevo: User Story 1
│   └── autenticacion-rbac.spec.js
├── playwright_agenda_doctor/             # Nuevo: User Story 2
│   └── agenda-doctor.spec.js
├── playwright_citas_paciente/            # Nuevo: User Story 3
│   └── citas-paciente.spec.js
└── playwright_especialidades_admin/      # Nuevo: User Story 4
    └── especialidades-admin.spec.js

playwright.config.js                      # Ya existente; testDir apunta a una carpeta por flujo,
                                           # se ajusta (en la implementación) para incluir las
                                           # carpetas nuevas o para descubrir todas las carpetas
                                           # `tests/playwright_*`.

.github/workflows/ci.yml                  # Ajuste de comportamiento (implementación futura, fuera
                                           # de alcance de esta especificación): instalar
                                           # navegadores de Playwright, ejecutar `npx playwright
                                           # test` y quitar el `|| true` del paso de pruebas para
                                           # que el build falle ante un fallo real (FR-012/FR-013).
```

**Structure Decision**: Se mantiene la estructura Laravel existente (`app/`, `resources/`,
`routes/`, `database/`, `tests/`) sin introducir una separación frontend/backend nueva. Cada flujo
E2E nuevo se agrega como una carpeta hermana de `tests/playwright_gestion_usuarios/`, siguiendo el
mismo patrón de nombre `playwright_<flujo>` y el mismo estilo de archivo (`import { test, expect }
from "@playwright/test"`, función `login_como_<rol>` reutilizable, pasos numerados en comentarios,
capturas de evidencia). El ajuste del pipeline de CI se documenta aquí como parte del diseño, pero
su ejecución real queda para la tarea de implementación correspondiente, no para esta entrega de
documentación.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

No aplica: el Constitution Check no encontró violaciones a los principios de la constitución que
requieran justificación. No se agrega ninguna fila a esta tabla.
