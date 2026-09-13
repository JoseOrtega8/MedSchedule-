---

description: "Task list template for feature implementation"
---

# Tasks: Tours guiados interactivos por rol

**Input**: Design documents from `/specs/002-tours-guiados/`

**Prerequisites**: plan.md (listo), spec.md (listo)

**Tests**: Se incluyen tareas de prueba porque la especificación fija criterios de aceptación
verificables (arranque único, botón de ayuda, teclado, resiliencia ante elementos ausentes) que
deben convertirse en pruebas E2E antes de dar por completa cada historia.

**Organization**: Las tareas están agrupadas por User Story para permitir implementación y
verificación independiente de cada una.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Puede ejecutarse en paralelo (archivos distintos, sin dependencias)
- **[Story]**: A qué User Story pertenece la tarea (US1, US2, US3, US4)
- Se incluyen rutas de archivo exactas en cada descripción

## Path Conventions

- Proyecto único Laravel: JavaScript de los tours en `resources/js/tours/`, vistas en
  `resources/views/<rol>/`, pruebas E2E en `tests/playwright_tours_guiados/` (ver `plan.md` →
  Project Structure).

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Dejar disponible la dependencia y la carpeta de trabajo antes de escribir cualquier
tour.

- [ ] T001 Agregar `driver.js` en su versión fijada `1.8.0` como dependencia del proyecto
      (instalación real a cargo de la implementación futura; esta tarea deja registrada la versión
      exacta a usar).
- [ ] T002 [P] Crear la carpeta `resources/js/tours/` como ubicación de los archivos de esta
      funcionalidad.
- [ ] T003 [P] Crear la carpeta `tests/playwright_tours_guiados/` como ubicación de las pruebas
      E2E de esta funcionalidad.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Construir el mecanismo compartido por los tres tours antes de escribir cualquiera de
ellos.

**⚠️ CRITICAL**: Ninguna User Story puede darse por completa sin este mecanismo compartido.

- [ ] T004 Implementar en `resources/js/tours/tour-helpers.js` el registro persistente de "tour
      visto" asociado al usuario y a su rol actual (cumple FR-005 y FR-011: un cambio de rol
      implica un estado de tour distinto).
- [ ] T005 Implementar en el mismo archivo la comprobación de existencia del elemento destino
      antes de mostrar cada paso, con la lógica de omitir el paso (si no es el primero) u omitir
      el tour completo (si es el primero) cuando el elemento no exista (cumple FR-008 y FR-009).
- [ ] T006 [P] Agregar un botón de ayuda reutilizable al layout compartido de cada panel
      (`resources/views/admin/`, `resources/views/doctor/`, `resources/views/patient/`) que
      invoque el relanzamiento del tour del rol activo (cumple FR-006).
- [ ] T007 Configurar la inicialización de `driver.js` para que todos los tours acepten navegación
      completa por teclado (avanzar, retroceder, cerrar) sin necesidad de dispositivo señalador
      (cumple FR-007).

**Checkpoint**: El mecanismo compartido (persistencia, resiliencia, botón de ayuda, teclado) está
listo; cada tour de rol puede construirse sobre él sin repetir esta lógica.

---

## Phase 3: User Story 1 - Tour de primera vez para el administrador (Priority: P1) 🎯 MVP

**Goal**: El administrador recibe, en su primer ingreso, un tour que cubre gestión de usuarios y
gestión de especialidades.

**Independent Test**: Iniciar sesión por primera vez con una cuenta `admin` sin tour visto y
verificar el arranque automático, la cobertura de ambas secciones y la no repetición en un segundo
ingreso.

- [ ] T008 [US1] Definir los pasos del tour de administrador en
      `resources/js/tours/tour-admin.js`, cubriendo como mínimo gestión de usuarios y gestión de
      especialidades (cumple FR-002).
- [ ] T009 [US1] Agregar los atributos de anclaje necesarios a las vistas de gestión de usuarios
      (`resources/views/admin/rbac.blade.php`) y de especialidades
      (`resources/views/admin/especialidades.blade.php`), sin alterar su lógica de negocio.
- [ ] T010 [P] [US1] Prueba E2E: el tour de administrador arranca solo en el primer ingreso y
      recorre gestión de usuarios y especialidades, en
      `tests/playwright_tours_guiados/tour-admin.spec.js` (cumple FR-001, FR-002).
- [ ] T011 [US1] Prueba E2E: el tour de administrador no vuelve a arrancar solo en un segundo
      ingreso tras haberse visto, en el mismo archivo (cumple FR-005).

**Checkpoint**: User Story 1 funciona de forma independiente.

---

## Phase 4: User Story 2 - Tour de primera vez para el doctor (Priority: P2)

**Goal**: El doctor recibe, en su primer ingreso, un tour que cubre su agenda y su perfil.

**Independent Test**: Iniciar sesión por primera vez con una cuenta `doctor` sin tour visto y
verificar el arranque automático, la cobertura de ambas secciones y la no repetición en un segundo
ingreso; no depende de que el tour de administrador exista.

- [ ] T012 [US2] Definir los pasos del tour de doctor en `resources/js/tours/tour-doctor.js`,
      cubriendo como mínimo agenda y perfil (cumple FR-003).
- [ ] T013 [US2] Agregar los atributos de anclaje necesarios a las vistas de agenda
      (`resources/views/doctor/agenda.blade.php`) y de perfil
      (`resources/views/doctor/perfil.blade.php`), sin alterar su lógica de negocio.
- [ ] T014 [P] [US2] Prueba E2E: el tour de doctor arranca solo en el primer ingreso y recorre
      agenda y perfil, en `tests/playwright_tours_guiados/tour-doctor.spec.js` (cumple FR-001,
      FR-003).
- [ ] T015 [US2] Prueba E2E: el tour de doctor no vuelve a arrancar solo en un segundo ingreso tras
      haberse visto, en el mismo archivo (cumple FR-005).

**Checkpoint**: User Stories 1 y 2 funcionan de forma independiente entre sí.

---

## Phase 5: User Story 3 - Tour de primera vez para el paciente (Priority: P3)

**Goal**: El paciente recibe, en su primer ingreso, un tour que cubre cómo agendar una cita. El
paciente no tiene una vista dedicada de agendado: todo el flujo (listar doctores, elegir horario,
agendar, cancelar) vive dentro de `resources/views/patient/dashboard.blade.php`, orquestado por
AJAX desde `resources/js/patient-dashboard.js` contra endpoints que devuelven JSON
(`appointments.store`, `appointments.cancel`, `patient.dashboard.data`), no por navegación entre
páginas HTML. El tour ancla sus pasos sobre los elementos de ese dashboard.

**Independent Test**: Iniciar sesión por primera vez con una cuenta `patient` sin tour visto y
verificar el arranque automático, la cobertura del bloque de agendado dentro del dashboard y la no
repetición en un segundo ingreso.

- [ ] T016 [US3] Definir los pasos del tour de paciente en `resources/js/tours/tour-patient.js`,
      cubriendo como mínimo los elementos del dashboard que permiten agendar una cita (selección
      de doctor/especialidad, horario y botón de confirmación) (cumple FR-004).
- [ ] T017 [US3] Agregar los atributos de anclaje necesarios dentro de
      `resources/views/patient/dashboard.blade.php`, sobre el bloque de agendado que ya renderiza
      esa vista (sin crear una vista nueva ni alterar la lógica de negocio ni los endpoints JSON
      de `AppointmentController`).
- [ ] T018 [P] [US3] Prueba E2E: el tour de paciente arranca solo en el primer ingreso y recorre
      el flujo de agendado, en `tests/playwright_tours_guiados/tour-patient.spec.js` (cumple
      FR-001, FR-004).
- [ ] T019 [US3] Prueba E2E: el tour de paciente no vuelve a arrancar solo en un segundo ingreso
      tras haberse visto, en el mismo archivo (cumple FR-005).

**Checkpoint**: Las tres User Stories de tour por rol funcionan de forma independiente entre sí.

---

## Phase 6: User Story 4 - Relanzar el tour desde un botón de ayuda (Priority: P4)

**Goal**: Cualquier usuario puede volver a ver el tour de su panel desde un botón de ayuda, y el
tour sigue siendo resiliente y operable por teclado sin importar cómo se relanzó.

**Independent Test**: Con cualquiera de los tres tours ya implementado, cerrar el tour, hacer clic
en el botón de ayuda y verificar que se relanza desde el primer paso; ocultar deliberadamente un
elemento destino y verificar que el tour lo omite; y recorrer un tour completo usando solo teclado.

- [ ] T020 [P] [US4] Prueba E2E: el botón de ayuda relanza el tour del panel activo desde el
      primer paso, para al menos uno de los tres roles, en
      `tests/playwright_tours_guiados/tour-ayuda-y-resiliencia.spec.js` (cumple FR-006).
- [ ] T021 [P] [US4] Prueba E2E: el tour continúa al siguiente paso cuando el elemento destino de
      un paso intermedio no existe, en el mismo archivo (cumple FR-008).
- [ ] T022 [P] [US4] Prueba E2E: el tour se omite completo cuando el elemento destino del primer
      paso no existe, en el mismo archivo (cumple FR-009).
- [ ] T023 [US4] Prueba E2E: un tour completo puede recorrerse usando solo teclado (avanzar,
      retroceder, cerrar), en el mismo archivo (cumple FR-007).

**Checkpoint**: Las cuatro User Stories funcionan de forma independiente entre sí.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Consolidar los tres tours y el botón de ayuda como una funcionalidad coherente.

- [ ] T024 [P] Revisar que los textos de los tres tours estén en español con acentuación correcta
      y con un tono consistente entre roles.
- [ ] T025 Verificar que cerrar cualquier tour en cualquier paso deja el panel usable de inmediato,
      sin overlays residuales (cumple FR-010).
- [ ] T026 Verificar el escenario de cambio de rol de un mismo usuario: el tour del rol nuevo
      arranca solo la primera vez que ese usuario entra al panel de ese rol (cumple FR-011).

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Fase 1)**: Sin dependencias, puede iniciar de inmediato.
- **Foundational (Fase 2)**: Depende de Setup. Bloquea a las cuatro User Stories: sin el
  mecanismo compartido (persistencia, resiliencia, botón de ayuda, teclado) ningún tour de rol
  cumple sus criterios de aceptación.
- **User Stories (Fases 3 a 6)**: Dependen de Foundational. US1, US2 y US3 son independientes
  entre sí. US4 requiere que al menos un tour de rol (US1, US2 o US3) exista para tener algo que
  relanzar u omitir, pero no requiere que los tres existan.
- **Polish (Fase 7)**: Depende de que las cuatro User Stories estén completas.

### User Story Dependencies

- **User Story 1 (P1)**: Depende solo de Foundational.
- **User Story 2 (P2)**: Depende solo de Foundational; no depende de US1.
- **User Story 3 (P3)**: Depende solo de Foundational; no depende de US1 ni US2.
- **User Story 4 (P4)**: Depende de Foundational y de que exista al menos un tour de rol
  (US1, US2 o US3) para probar el relanzamiento y la resiliencia sobre él.

### Parallel Opportunities

- T002 y T003 (Setup) pueden avanzar en paralelo.
- T006 (botón de ayuda) puede avanzar en paralelo a T004/T005 una vez acordada la forma en que el
  helper expone la función de relanzar.
- Una vez completada la Fase 2, US1, US2 y US3 pueden asignarse a personas distintas y avanzar en
  paralelo, porque cada una vive en su propio archivo JS, sus propias vistas y su propio archivo
  de prueba.
- Las pruebas E2E marcadas `[P]` dentro de cada historia pueden redactarse en paralelo a la
  integración de los atributos de anclaje de esa misma historia.

---

## Parallel Example: User Story 1

```bash
# Redactar en paralelo la definicion de pasos y la primera prueba E2E del tour de administrador:
Task: "Definir los pasos del tour de administrador en resources/js/tours/tour-admin.js"
Task: "Prueba E2E: el tour de administrador arranca solo en el primer ingreso"
```

---

## Implementation Strategy

### MVP First (User Story 1 solamente)

1. Completar Fase 1: Setup.
2. Completar Fase 2: Foundational (crítico: persistencia, resiliencia, botón de ayuda y teclado
   deben existir antes de que cualquier tour de rol sea verificable).
3. Completar Fase 3: User Story 1.
4. **Detenerse y validar**: confirmar que el tour de administrador arranca una sola vez y cubre
   gestión de usuarios y especialidades.

### Incremental Delivery

1. Setup + Foundational listos.
2. Agregar User Story 1 → validar de forma independiente (tour de administrador funcional).
3. Agregar User Story 2 → validar de forma independiente (tour de doctor funcional).
4. Agregar User Story 3 → validar de forma independiente (tour de paciente funcional).
5. Agregar User Story 4 → validar de forma independiente (botón de ayuda, teclado y resiliencia
   sobre los tours ya existentes).
6. Cada historia agrega valor sin romper la de las anteriores.

---

## Notes

- `[P]` = archivos distintos o pruebas independientes dentro del mismo archivo, sin dependencias
  entre sí.
- La etiqueta `[Story]` mapea cada tarea a su User Story para trazabilidad hacia `spec.md`.
- Cada User Story debe poder completarse y verificarse de forma independiente, salvo la
  dependencia mínima de US4 sobre la existencia de al menos un tour de rol.
- Antes de dar por completa una tarea de prueba, confirmar que la prueba falla si se rompe
  deliberadamente el comportamiento que verifica (por ejemplo, forzando que el tour arranque dos
  veces, para confirmar que la prueba de "no se repite" realmente lo detecta).
- Evitar: tours que dependan de datos dejados por otra prueba E2E, pasos sin verificación de
  existencia del elemento destino, y aserciones de accesibilidad que solo prueben el clic con
  mouse sin cubrir también el teclado.
