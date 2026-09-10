# Implementation Plan: Tours guiados interactivos por rol

**Branch**: `002-tours-guiados` | **Date**: 2026-09-09 | **Spec**: `specs/002-tours-guiados/spec.md`

**Input**: Feature specification from `/specs/002-tours-guiados/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command; its definition describes the execution workflow.

## Summary

Incorporar tres tours guiados (uno por rol) con `driver.js@1.8.0` sobre las vistas Blade existentes
de MedSchedule, sin alterar su lógica de negocio: el tour de administrador destaca gestión de
usuarios y especialidades, el de doctor destaca agenda y perfil, y el de paciente destaca el flujo
de agendado de cita. El tour arranca solo la primera vez por usuario y rol, se puede relanzar desde
un botón de ayuda compartido, funciona por teclado y tolera que un elemento destino falte en el
DOM sin romper la página.

## Technical Context

**Language/Version**: JavaScript (ES2020+) ejecutado en el navegador, integrado con las vistas
Blade de Laravel 12 mediante el pipeline de assets ya usado por el proyecto (Vite).

**Primary Dependencies**: `driver.js` `@1.8.0` (versión fijada por las restricciones globales de
esta entrega; su instalación real es trabajo de la Tarea 6, no de esta especificación ni de este
plan). No se requieren dependencias de backend nuevas para el comportamiento del tour en sí.

**Storage**: Se requiere persistir, por usuario y por rol, si el tour de ese rol ya fue visto
(FR-005). Se reutiliza la base de datos relacional ya existente del proyecto (MySQL vía Eloquent),
asociando el estado al modelo `User` (por ejemplo, mediante un atributo nuevo o una tabla de
preferencias por usuario); el nombre exacto del campo o tabla se decide en la fase de
implementación, ya que no forma parte del contrato de comportamiento que describe la especificación.

**Testing**: Playwright (`@playwright/test` `^1.62.1`), siguiendo el mismo patrón de infraestructura
E2E documentado en `specs/001-pruebas-e2e/` (carpeta por flujo, login helper por rol, evidencia por
captura de pantalla), para verificar arranque automático, no repetición, botón de ayuda, navegación
por teclado y tolerancia a elementos faltantes.

**Target Platform**: Navegador web (Chromium como objetivo principal de prueba, igual que el resto
del suite E2E), integrado en las vistas Blade existentes de cada panel (`admin`, `doctor`,
`patient`).

**Project Type**: Aplicación web monolítica Laravel con JavaScript de frontend integrado (no hay
separación en proyectos `frontend/`/`backend/` independientes).

**Performance Goals**: El tour debe iniciar en menos de 1 segundo tras la carga completa del panel;
el botón de ayuda debe relanzar el tour en menos de 1 segundo desde el clic (ver SC-003 de la
especificación).

**Constraints**: El tour no debe bloquear la interfaz cuando un elemento destino no exista
(FR-008/FR-009); debe ser completamente operable por teclado (FR-007); no debe introducir
dependencias de red adicionales al flujo crítico de agendado de citas (el tour se apoya en el
contenido que ya está en la página, no en llamadas nuevas al servidor durante su ejecución, salvo
la que registra "visto").

**Scale/Scope**: 3 tours (uno por rol) más 1 botón de ayuda compartido; no cubre paneles fuera de
gestión de usuarios/especialidades (admin), agenda/perfil (doctor) y agendado de cita (patient); no
cubre la corrección de accesibilidad general de MedSchedule más allá de los propios tours.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **Principio VI (Spec-Driven Development)**: PASA. Esta funcionalidad entra por especificación y
  plan antes que por código; ningún archivo bajo `resources/` o `app/` se modifica como parte de
  este documento.
- **Principio I (Estilo de código)**: PASA con condición para la implementación futura: el
  JavaScript de los tours debe llevar comentarios en español y funciones en snake_case, igual que
  el resto del código del proyecto.
- **Principio II (Manejo de errores)**: PASA con condición: la implementación debe capturar
  explícitamente la ausencia de un elemento destino (por ejemplo, comprobando su existencia antes
  de invocar a `driver.js`, o capturando la excepción que produzca) en vez de dejar que un error
  no controlado detenga la ejecución del script de la página (esto es lo que exige FR-008/FR-009).
- **Principio III (Gestión de secretos)**: PASA. El tour no maneja credenciales ni claves.
- **Principio IV (Validación de entrada)**: no aplica de forma directa: el tour no recibe datos de
  formulario: la única entrada del usuario es navegación por teclado o clic, que no requiere
  sanitización adicional a la ya provista por el navegador.
- **Principio V (Exposición de errores al cliente)**: PASA con condición: si el registro de "tour
  visto" falla (por ejemplo, error de red al guardar la preferencia), el tour debe seguir
  funcionando visualmente para el usuario en esa sesión, sin mostrarle un error técnico; el fallo
  de guardado se maneja en el mismo lugar donde ya se manejan errores internos de la aplicación.
- **Principio VII (Convenciones de control de versiones)**: PASA. El trabajo se entrega en la rama
  `feat/unidad-docs-sdd` con commits convencionales, conforme a las restricciones globales de esta
  entrega.

No se identificaron violaciones que requieran justificación en la tabla de Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/002-tours-guiados/
├── plan.md              # Este archivo (/speckit-plan command output)
└── tasks.md             # Fase 2 (/speckit-tasks command)
```

No se generan `research.md`, `data-model.md`, `quickstart.md` ni `contracts/` para esta
funcionalidad: `driver.js` es una biblioteca de UI ya elegida y con versión fijada (no requiere
investigación de alternativas), el único dato nuevo es un estado booleano/estructurado por usuario
y rol (demasiado simple para justificar un `data-model.md` propio) y no se expone ningún contrato
de API nuevo hacia fuera del propio panel.

### Source Code (repository root)

MedSchedule es una aplicación Laravel de un solo proyecto, por lo que no aplica ninguna de las
opciones genéricas de estructura del template (no hay `frontend/`/`backend/` separados ni
`ios/`/`android/`). La estructura real que usaría esta funcionalidad, cuando se implemente, es:

```text
resources/js/
└── tours/
    ├── tour-helpers.js      # Registrar "visto" por usuario/rol, botón de ayuda compartido,
    │                         # manejo de pasos con elemento destino ausente (FR-005, FR-006,
    │                         # FR-008, FR-009)
    ├── tour-admin.js         # Pasos del tour de administrador (FR-002)
    ├── tour-doctor.js        # Pasos del tour de doctor (FR-003)
    └── tour-patient.js       # Pasos del tour de paciente (FR-004)

resources/views/
├── admin/                    # Vistas existentes (rbac, especialidades) reciben atributos de
│                             # anclaje para los pasos del tour y el botón de ayuda; sin cambios
│                             # a su lógica de negocio
├── doctor/                   # Vistas existentes (agenda, perfil) reciben atributos de anclaje
└── patient/dashboard.blade.php  # Única vista del paciente (confirmada en el código: no existe
                              # una vista dedicada de agendado); recibe atributos de anclaje sobre
                              # los elementos del dashboard, incluido el bloque de agendado que
                              # vive ahí mismo y que se orquesta por AJAX desde
                              # resources/js/patient-dashboard.js contra endpoints JSON
                              # (appointments.store, appointments.cancel, patient.dashboard.data)

tests/
└── playwright_tours_guiados/
    ├── tour-admin.spec.js
    ├── tour-doctor.spec.js
    ├── tour-patient.spec.js
    └── tour-ayuda-y-resiliencia.spec.js   # Botón de ayuda, teclado, elemento destino ausente
```

**Structure Decision**: Se mantiene la estructura Laravel existente sin introducir una separación
frontend/backend nueva. El JavaScript de los tours se agrupa en `resources/js/tours/` (un archivo
por rol más un helper compartido) para que cada tour sea reemplazable de forma independiente; las
vistas Blade existentes solo reciben atributos de anclaje (sin alterar su lógica), y las pruebas
E2E se agregan como una carpeta hermana adicional bajo `tests/`, siguiendo el mismo patrón de
`specs/001-pruebas-e2e/`.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

No aplica: el Constitution Check no encontró violaciones a los principios de la constitución que
requieran justificación. No se agrega ninguna fila a esta tabla.
