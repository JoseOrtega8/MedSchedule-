# Feature Specification: Ampliación de cobertura de pruebas E2E por rol

**Feature Branch**: `001-pruebas-e2e`

**Created**: 2026-09-09

**Status**: Draft

**Input**: User description: "Ampliar la cobertura de pruebas E2E de MedSchedule más allá del único flujo de gestión de usuarios. Cubrir los flujos críticos por rol: autenticación y control de acceso por rol, agenda del doctor, agendado y cancelación de cita del paciente, y CRUD de especialidades del administrador. Las pruebas deben correr en el pipeline de integración continua y fallar el build cuando fallen."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Autenticación y control de acceso por rol (Priority: P1)

Como responsable de calidad de MedSchedule, necesito que una prueba automática confirme que cada
rol (`admin`, `doctor`, `patient`) puede iniciar sesión y llega únicamente a las áreas que le
corresponden, y que un usuario sin el rol requerido no puede entrar a un área ajena. Hoy esto no
se verifica de forma automática: la única prueba E2E existente (gestión de usuarios) da por hecho
que el login ya funciona.

**Why this priority**: Es la base de la que dependen los demás flujos (todos requieren login
previo) y es el área con más impacto histórico: el proyecto ya sufrió un bug de comparación de
roles en mayúsculas/minúsculas. Sin esta cobertura, un regreso a ese bug pasaría inadvertido.

**Independent Test**: Puede probarse por completo sin que existan los demás flujos nuevos:
iniciar sesión con un usuario de cada rol y verificar la redirección correcta, e iniciar sesión
con credenciales inválidas o intentar acceder a una ruta de otro rol y verificar el rechazo.

**Acceptance Scenarios**:

1. **Given** un usuario existente con rol `admin`, **When** inicia sesión con sus credenciales
   correctas, **Then** el sistema lo redirige a su panel de administrador.
2. **Given** un usuario existente con rol `doctor`, **When** inicia sesión con sus credenciales
   correctas, **Then** el sistema lo redirige a su panel de doctor.
3. **Given** un usuario existente con rol `patient`, **When** inicia sesión con sus credenciales
   correctas, **Then** el sistema lo redirige a su panel de paciente.
4. **Given** un usuario existente, **When** intenta iniciar sesión con una contraseña incorrecta,
   **Then** el sistema no lo autentica y muestra un mensaje de error, permaneciendo en la página
   de login.
5. **Given** un usuario autenticado con rol `patient`, **When** intenta acceder directamente a una
   ruta reservada al rol `admin`, **Then** el sistema le niega el acceso con un código de estado
   403 y no le muestra contenido de administrador.
6. **Given** un usuario autenticado sin ningún rol asignado, **When** intenta acceder a su panel
   principal, **Then** el sistema le niega el acceso con un código de estado 403 en vez de
   redirigirlo a un panel.

---

### User Story 2 - Agenda del doctor (Priority: P2)

Como responsable de calidad, necesito una prueba automática que confirme que un doctor autenticado
ve su propia agenda de citas y no la de otro doctor.

**Why this priority**: La agenda es la pantalla de uso diario del rol `doctor`; un error de
alcance de datos (ver citas de otro doctor) es un riesgo de privacidad de información médica.

**Independent Test**: Puede probarse iniciando sesión como doctor con citas ya agendadas y
verificando que la agenda muestra exactamente esas citas, sin depender de que exista el flujo de
agendado de citas del paciente (los datos de prueba se preparan directamente para el doctor).

**Acceptance Scenarios**:

1. **Given** un doctor autenticado con citas agendadas en su horario, **When** abre su agenda,
   **Then** ve listadas únicamente las citas que le pertenecen a él.
2. **Given** un doctor autenticado sin citas agendadas para el rango de fechas visible, **When**
   abre su agenda, **Then** el sistema muestra la agenda vacía sin error.
3. **Given** un doctor autenticado, **When** abre su agenda, **Then** no aparece ninguna cita
   perteneciente a otro doctor.

---

### User Story 3 - Agendado y cancelación de cita del paciente (Priority: P3)

Como responsable de calidad, necesito una prueba automática que confirme que un paciente puede
agendar una cita disponible con un doctor y especialidad, y cancelar una cita propia.

**Why this priority**: Es el flujo de negocio central de MedSchedule desde la perspectiva del
paciente; requiere que el flujo de autenticación (US1) ya esté cubierto y añade la primera prueba
de escritura de datos de negocio (no solo de usuarios administrativos).

**Independent Test**: Puede probarse de forma aislada con un paciente, un doctor y un horario
disponible ya sembrados: agendar la cita, verificar que aparece en el historial del paciente, y
cancelarla, verificando que su estado cambia y libera el horario.

**Acceptance Scenarios**:

1. **Given** un paciente autenticado y un horario disponible de un doctor con una especialidad
   determinada, **When** el paciente agenda una cita en ese horario, **Then** la cita aparece en
   su listado de citas con estado agendada.
2. **Given** un paciente autenticado, **When** intenta agendar una cita en un horario que ya está
   ocupado, **Then** el sistema rechaza la operación y no crea una segunda cita en ese horario.
3. **Given** un paciente autenticado con una cita propia en estado agendada, **When** la cancela,
   **Then** la cita cambia a estado cancelada y el horario vuelve a quedar disponible.
4. **Given** un paciente autenticado, **When** intenta cancelar una cita que pertenece a otro
   paciente, **Then** el sistema rechaza la operación.

---

### User Story 4 - CRUD de especialidades del administrador (Priority: P4)

Como responsable de calidad, necesito una prueba automática que confirme que un administrador
puede crear, editar y eliminar especialidades médicas, y que el sistema evita duplicados.

**Why this priority**: Es un flujo administrativo de mantenimiento de catálogo, necesario para que
los demás flujos (agendado de citas) tengan datos válidos, pero de menor frecuencia de uso que los
tres anteriores; por eso se prioriza al final.

**Independent Test**: Puede probarse de forma aislada iniciando sesión como administrador y
ejecutando el ciclo completo crear/editar/eliminar sobre una especialidad de prueba, sin depender
de que existan doctores o pacientes de prueba.

**Acceptance Scenarios**:

1. **Given** un administrador autenticado, **When** crea una especialidad con un nombre nuevo,
   **Then** la especialidad aparece en el listado de especialidades.
2. **Given** un administrador autenticado y una especialidad existente, **When** edita su nombre,
   **Then** el listado refleja el nombre actualizado.
3. **Given** un administrador autenticado y una especialidad existente sin citas asociadas,
   **When** la elimina, **Then** deja de aparecer en el listado.
4. **Given** un administrador autenticado, **When** intenta crear una especialidad con un nombre
   que ya existe, **Then** el sistema rechaza la creación y no genera un duplicado.

---

### Edge Cases

- Un usuario intenta iniciar sesión con un rol asignado en mayúscula o con variación de
  capitalización (regresión del bug histórico de comparación de roles): el sistema debe seguir
  tratando los roles de forma consistente en minúscula y no autenticar accesos indebidos por esta
  causa.
- Un paciente intenta cancelar una cita que ya está cancelada: el sistema debe rechazar la
  operación en vez de duplicar el cambio de estado.
- Un doctor sin horarios configurados abre su agenda: debe verse vacía, no producir error.
- Dos usuarios intentan agendar el mismo horario disponible casi al mismo tiempo: solo uno debe
  lograrlo; el sistema no debe dejar el horario reservado dos veces.
- Una prueba E2E falla en el pipeline de integración continua: el trabajo de CI correspondiente
  debe terminar en estado fallido, visible en la interfaz de la solicitud de cambios, sin que un
  mecanismo de la configuración del pipeline oculte el fallo.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema DEBE permitir iniciar sesión con credenciales válidas a usuarios de los
  tres roles (`admin`, `doctor`, `patient`) y redirigir a cada uno a su panel correspondiente.
- **FR-002**: El sistema DEBE rechazar el inicio de sesión con credenciales inválidas, sin
  autenticar al usuario y mostrando un mensaje de error.
- **FR-003**: El sistema DEBE denegar, con un código de estado 403, el acceso de un usuario
  autenticado a una ruta reservada a un rol distinto del suyo.
- **FR-004**: El sistema DEBE denegar, con un código de estado 403, el acceso a su panel principal
  de un usuario autenticado que no tiene ningún rol asignado.
- **FR-005**: El sistema DEBE mostrar a un doctor autenticado únicamente las citas de su propia
  agenda, sin exponer citas de otros doctores.
- **FR-006**: El sistema DEBE permitir a un paciente autenticado agendar una cita en un horario
  disponible de un doctor con una especialidad determinada.
- **FR-007**: El sistema DEBE rechazar el agendado de una cita en un horario que ya está ocupado.
- **FR-008**: El sistema DEBE permitir a un paciente autenticado cancelar una cita propia en
  estado agendada, liberando el horario correspondiente.
- **FR-009**: El sistema DEBE rechazar la cancelación de una cita que no pertenece al paciente
  autenticado.
- **FR-010**: El sistema DEBE permitir a un administrador autenticado crear, editar y eliminar
  especialidades médicas.
- **FR-011**: El sistema DEBE rechazar la creación de una especialidad con un nombre ya existente.
- **FR-012**: El pipeline de integración continua DEBE ejecutar automáticamente las pruebas E2E de
  los cuatro flujos descritos en esta especificación en cada ejecución sobre una solicitud de
  cambios o un push a las ramas protegidas del repositorio.
- **FR-013**: El pipeline de integración continua DEBE terminar en estado fallido cuando alguna
  prueba E2E falla, sin ningún mecanismo de configuración que oculte o silencie ese fallo.
- **FR-014**: Cada ejecución de las pruebas E2E en el pipeline DEBE dejar disponible, como
  artefacto descargable de esa ejecución, la evidencia visual (capturas de pantalla) generada por
  las pruebas, incluidas las que fallan.

### Key Entities

- **User**: cuenta con credenciales de acceso y un rol asignado (`admin`, `doctor` o `patient`,
  siempre en minúscula); punto de entrada de los cuatro flujos de esta especificación.
- **DoctorProfile**: datos del doctor vinculados a un `User` con rol `doctor`; determina a qué
  especialidad y horarios pertenece la agenda que se prueba en la User Story 2.
- **PatientProfile**: datos del paciente vinculados a un `User` con rol `patient`; origen de las
  citas agendadas y canceladas en la User Story 3.
- **Specialty**: catálogo de especialidades médicas administrado por el rol `admin`; probado por
  la User Story 4 y consumido como dato de entrada por la User Story 3.
- **Schedule**: horario disponible de un doctor para una especialidad; determina qué horarios
  puede seleccionar un paciente en la User Story 3.
- **Appointment**: cita agendada entre un paciente y un doctor en un horario; su ciclo de vida
  (agendada, cancelada) es el objeto probado en la User Story 3 y consultado en la User Story 2.
- **AppointmentHistory**: registro de cambios de estado de una cita; permite verificar, como
  evidencia adicional, que una cancelación quedó registrada.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: La cobertura de pruebas E2E automatizadas pasa de 1 flujo (gestión de usuarios) a 5
  flujos (el existente más los cuatro de esta especificación).
- **SC-002**: El 100% de las ejecuciones del pipeline de integración continua sobre una solicitud
  de cambios hacia las ramas protegidas ejecutan las pruebas E2E de los cuatro flujos.
- **SC-003**: El 100% de las ejecuciones del pipeline en las que al menos una prueba E2E falla
  terminan en estado fallido (0% de ejecuciones con fallo real pero resultado exitoso reportado).
- **SC-004**: El 100% de las ejecuciones del pipeline que corren las pruebas E2E dejan capturas de
  pantalla descargables como evidencia, sin importar si la ejecución fue exitosa o fallida.
- **SC-005**: Ninguna de las cinco pruebas E2E (la existente más las cuatro nuevas) depende de
  datos dejados por otra prueba E2E previa; cada una puede ejecutarse de forma aislada y obtener el
  mismo resultado.

## Assumptions

- El entorno de integración continua siembra usuarios de prueba con los tres roles (`admin`,
  `doctor`, `patient`) y credenciales conocidas antes de correr las pruebas E2E, de forma análoga a
  como ya se prepara la base de datos para las pruebas de PHPUnit existentes.
- Los datos de prueba para las User Stories 2, 3 y 4 (doctor con horarios, especialidad,
  paciente) se preparan de forma independiente entre sí y no reutilizan el usuario ni los datos
  creados por la prueba de gestión de usuarios ya existente.
- La versión de `@playwright/test` usada para las pruebas nuevas es `^1.62.1`, conforme a las
  versiones fijadas para esta entrega.
- El ajuste necesario en el pipeline de integración continua para ejecutar Playwright y dejar de
  ocultar los fallos de esa etapa es trabajo de implementación posterior a esta especificación; no
  se modifica ningún archivo de configuración de CI como parte de este documento.
- Los 15 fallos actuales del suite de PHPUnit, documentados por separado, no son objeto de esta
  especificación: esta especificación cubre pruebas E2E nuevas, no la corrección del suite
  existente.
