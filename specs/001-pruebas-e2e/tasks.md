---

description: "Task list template for feature implementation"
---

# Tasks: Ampliación de cobertura de pruebas E2E por rol

**Input**: Design documents from `/specs/001-pruebas-e2e/`

**Prerequisites**: plan.md (listo), spec.md (listo)

**Tests**: Esta funcionalidad ES un conjunto de pruebas; no hay una fase de "implementación de
producto" separada de la "fase de pruebas": cada tarea de las User Stories produce directamente el
archivo de prueba E2E correspondiente.

**Organization**: Las tareas están agrupadas por User Story para permitir implementación y
verificación independiente de cada una.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Puede ejecutarse en paralelo (archivos distintos, sin dependencias)
- **[Story]**: A qué User Story pertenece la tarea (US1, US2, US3, US4)
- Se incluyen rutas de archivo exactas en cada descripción

## Path Conventions

- Proyecto único Laravel: `tests/playwright_<flujo>/` en la raíz del repositorio (ver
  `plan.md` → Project Structure).

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Preparar el entorno para que los cuatro flujos nuevos puedan correr.

- [ ] T001 Confirmar que el seeder de entorno de pruebas crea al menos un usuario por rol
      (`admin`, `doctor`, `patient`) con contraseña conocida, reutilizable por los cuatro flujos
      nuevos (sin crear un seeder nuevo si el existente ya lo cubre).
- [ ] T002 [P] Confirmar que existen datos base de dominio para pruebas (una especialidad, un
      doctor con horario disponible) reutilizables por `playwright_agenda_doctor` y
      `playwright_citas_paciente`, sembrados de forma independiente entre pruebas.
- [ ] T003 [P] Documentar en `playwright.config.js` (o en el `testDir`/patrón de descubrimiento
      que se decida) que las carpetas nuevas `tests/playwright_autenticacion_rbac`,
      `tests/playwright_agenda_doctor`, `tests/playwright_citas_paciente` y
      `tests/playwright_especialidades_admin` deben ejecutarse junto con
      `tests/playwright_gestion_usuarios`.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Infraestructura de CI que debe existir antes de que las pruebas nuevas aporten valor
real (de lo contrario, corren pero nadie las ve fallar).

**⚠️ CRITICAL**: Ninguna de las User Stories aporta el valor de "falla el build" (FR-012/FR-013)
hasta que esta fase esté completa.

- [ ] T004 Ajustar el paso de pruebas E2E del pipeline de integración continua para instalar los
      navegadores de Playwright (`npx playwright install --with-deps chromium`) antes de
      ejecutar las pruebas.
- [ ] T005 Ajustar el paso de pruebas E2E del pipeline para ejecutar `npx playwright test` contra
      la instancia de la aplicación levantada en el mismo job (siguiendo el patrón de captura de
      PID documentado en las restricciones de esta entrega, sin usar `kill %1`).
- [ ] T006 Eliminar el `|| true` (o equivalente) del paso de pruebas E2E del pipeline, de modo que
      un fallo de Playwright marque el job como fallido (cumple FR-013).
- [ ] T007 [P] Configurar el pipeline para publicar como artefacto descargable el reporte HTML de
      Playwright y las capturas de pantalla generadas, incluidas las de ejecuciones fallidas
      (cumple FR-014).

**Checkpoint**: A partir de aquí, cualquier User Story que se agregue puede hacer fallar el build
de verdad.

---

## Phase 3: User Story 1 - Autenticación y control de acceso por rol (Priority: P1) 🎯 MVP

**Goal**: Verificar automáticamente que cada rol inicia sesión y llega a su panel, que las
credenciales inválidas se rechazan, y que un usuario no puede entrar a un área de otro rol.

**Independent Test**: Ejecutar únicamente `tests/playwright_autenticacion_rbac/` con los usuarios
sembrados en la Fase 1; no depende de las demás User Stories.

- [ ] T008 [P] [US1] Prueba E2E: login exitoso de `admin`, `doctor` y `patient` redirige a su
      panel respectivo, en `tests/playwright_autenticacion_rbac/autenticacion-rbac.spec.js`
      (cumple FR-001).
- [ ] T009 [P] [US1] Prueba E2E: login con contraseña incorrecta no autentica y muestra error, en
      el mismo archivo (cumple FR-002).
- [ ] T010 [US1] Prueba E2E: usuario `patient` que intenta acceder a una ruta reservada a `admin`
      recibe 403, en el mismo archivo (cumple FR-003).
- [ ] T011 [US1] Prueba E2E: usuario autenticado sin rol asignado recibe 403 al acceder a su panel
      principal, en el mismo archivo (cumple FR-004).

**Checkpoint**: User Story 1 funciona de forma independiente y ya hace fallar el build ante una
regresión de autenticación o de control de acceso por rol.

---

## Phase 4: User Story 2 - Agenda del doctor (Priority: P2)

**Goal**: Verificar que un doctor ve únicamente su propia agenda.

**Independent Test**: Ejecutar únicamente `tests/playwright_agenda_doctor/` con el doctor y
horario sembrados en T002; no depende de US1 para tener datos, aunque reutiliza el mismo mecanismo
de login.

- [ ] T012 [P] [US2] Prueba E2E: la agenda del doctor muestra únicamente sus propias citas, en
      `tests/playwright_agenda_doctor/agenda-doctor.spec.js` (cumple FR-005).
- [ ] T013 [US2] Prueba E2E: la agenda del doctor se muestra vacía sin error cuando no tiene citas
      en el rango visible, en el mismo archivo (cumple FR-005, edge case de agenda vacía).

**Checkpoint**: User Stories 1 y 2 funcionan de forma independiente entre sí.

---

## Phase 5: User Story 3 - Agendado y cancelación de cita del paciente (Priority: P3)

**Goal**: Verificar que un paciente puede agendar una cita disponible y cancelar una cita propia,
y que el sistema rechaza los casos inválidos (horario ocupado, cancelación ajena).

**Independent Test**: Ejecutar únicamente `tests/playwright_citas_paciente/` con el paciente,
doctor, especialidad y horario sembrados en T001/T002.

- [ ] T014 [P] [US3] Prueba E2E: el paciente agenda una cita en un horario disponible y esta
      aparece con estado agendada, en `tests/playwright_citas_paciente/citas-paciente.spec.js`
      (cumple FR-006).
- [ ] T015 [US3] Prueba E2E: el paciente no puede agendar una cita en un horario ya ocupado, en el
      mismo archivo (cumple FR-007).
- [ ] T016 [US3] Prueba E2E: el paciente cancela una cita propia agendada y el horario vuelve a
      quedar disponible, en el mismo archivo (cumple FR-008).
- [ ] T017 [US3] Prueba E2E: el paciente no puede cancelar una cita que pertenece a otro paciente,
      en el mismo archivo (cumple FR-009).

**Checkpoint**: User Stories 1, 2 y 3 funcionan de forma independiente entre sí.

---

## Phase 6: User Story 4 - CRUD de especialidades del administrador (Priority: P4)

**Goal**: Verificar que un administrador puede crear, editar y eliminar especialidades, y que el
sistema evita nombres duplicados.

**Independent Test**: Ejecutar únicamente `tests/playwright_especialidades_admin/` con el usuario
`admin` sembrado en T001; no depende de que existan doctores o pacientes de prueba.

- [ ] T018 [P] [US4] Prueba E2E: el administrador crea una especialidad nueva y aparece en el
      listado, en `tests/playwright_especialidades_admin/especialidades-admin.spec.js` (cumple
      FR-010).
- [ ] T019 [US4] Prueba E2E: el administrador edita el nombre de una especialidad existente y el
      listado refleja el cambio, en el mismo archivo (cumple FR-010).
- [ ] T020 [US4] Prueba E2E: el administrador elimina una especialidad sin citas asociadas y deja
      de aparecer en el listado, en el mismo archivo (cumple FR-010).
- [ ] T021 [US4] Prueba E2E: el administrador no puede crear una especialidad con un nombre ya
      existente, en el mismo archivo (cumple FR-011).

**Checkpoint**: Las cuatro User Stories funcionan de forma independiente entre sí.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Consolidar la entrega de los 5 flujos E2E (el existente más los 4 nuevos) como un
conjunto coherente.

- [ ] T022 [P] Revisar que ninguna de las 4 pruebas nuevas reutilice datos (usuarios, citas,
      especialidades) creados por otra prueba E2E, incluida la ya existente de gestión de
      usuarios (cumple SC-005).
- [ ] T023 [P] Verificar en una ejecución completa del pipeline que las 5 pruebas E2E corren y que
      forzar el fallo de una de ellas efectivamente pone el job en rojo (cumple SC-002/SC-003).
- [ ] T024 Verificar que la ejecución del pipeline deja el reporte HTML y las capturas de pantalla
      como artefacto descargable, en una corrida exitosa y en una forzada a fallar (cumple SC-004).

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Fase 1)**: Sin dependencias, puede iniciar de inmediato.
- **Foundational (Fase 2)**: Depende de que el Setup exista (necesita saber qué carpetas de
  pruebas va a ejecutar). Bloquea la utilidad real de todas las User Stories: sin ella, las
  pruebas corren pero un fallo no tumba el build.
- **User Stories (Fases 3 a 6)**: Todas dependen de Setup completo; no dependen entre sí para sus
  datos, aunque comparten el patrón de login. Pueden implementarse en orden de prioridad (P1→P4)
  o en paralelo si hay más de una persona disponible.
- **Polish (Fase 7)**: Depende de que las 4 User Stories estén completas.

### User Story Dependencies

- **User Story 1 (P1)**: Sin dependencia de otras historias.
- **User Story 2 (P2)**: Sin dependencia de datos de otras historias; reutiliza el helper de login
  que también usa US1, pero puede implementarse sin que US1 exista.
- **User Story 3 (P3)**: Sin dependencia de datos de otras historias.
- **User Story 4 (P4)**: Sin dependencia de datos de otras historias.

### Parallel Opportunities

- T002 y T003 (Setup) pueden avanzar en paralelo.
- T007 (Foundational, publicar artefactos) puede avanzar en paralelo a T004-T006 una vez que se
  sabe qué reporte se va a publicar.
- Una vez completada la Fase 2, las cuatro User Stories pueden asignarse a personas distintas y
  avanzar en paralelo, porque cada una vive en su propio archivo de prueba y usa datos sembrados
  de forma independiente.
- Dentro de cada User Story, las tareas marcadas `[P]` (la primera prueba de cada archivo) pueden
  redactarse en paralelo con la preparación de datos de esa misma historia.

---

## Parallel Example: User Story 1

```bash
# Redactar en paralelo las dos primeras pruebas de autenticación:
Task: "Prueba E2E: login exitoso de admin/doctor/patient redirige a su panel"
Task: "Prueba E2E: login con contraseña incorrecta no autentica"
```

---

## Implementation Strategy

### MVP First (User Story 1 solamente)

1. Completar Fase 1: Setup.
2. Completar Fase 2: Foundational (crítico: sin esto ninguna prueba nueva hace fallar el build).
3. Completar Fase 3: User Story 1.
4. **Detenerse y validar**: forzar un fallo deliberado en una prueba de US1 y confirmar que el job
   de CI queda en rojo.

### Incremental Delivery

1. Setup + Foundational listos.
2. Agregar User Story 1 → validar de forma independiente → esta es la cobertura mínima de
   autenticación/RBAC.
3. Agregar User Story 2 → validar de forma independiente.
4. Agregar User Story 3 → validar de forma independiente.
5. Agregar User Story 4 → validar de forma independiente.
6. Cada historia agrega cobertura sin romper la de las anteriores.

---

## Notes

- `[P]` = archivos distintos o assertions independientes dentro del mismo archivo, sin
  dependencias entre sí.
- La etiqueta `[Story]` mapea cada tarea a su User Story para trazabilidad hacia `spec.md`.
- Cada User Story debe poder completarse y verificarse de forma independiente, ejecutando
  únicamente su carpeta `tests/playwright_<flujo>/`.
- Antes de dar por completa una tarea de prueba, confirmar que la prueba falla si se rompe
  deliberadamente el comportamiento que verifica (para descartar una prueba que "siempre pasa").
- Evitar: pruebas que dependan de datos dejados por otra prueba E2E, aserciones sin código de
  estado o mensaje concreto, y pasos que dupliquen datos entre corridas sin usar una marca única
  (ver el patrón `Date.now()` de `gestion-usuarios.spec.js`).
