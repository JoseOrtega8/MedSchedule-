# Feature Specification: Tours guiados interactivos por rol

**Feature Branch**: `002-tours-guiados`

**Created**: 2026-09-09

**Status**: Draft

**Input**: User description: "Incorporar tours guiados interactivos con driver.js en MedSchedule, para que cada rol reciba una guía de primera vez en su panel. El administrador aprende la gestión de usuarios y especialidades; el doctor, su agenda y perfil; el paciente, cómo agendar una cita. El tour se muestra una sola vez por usuario y puede relanzarse desde un botón de ayuda. Debe ser accesible por teclado y no bloquear la aplicación si el elemento destino no existe."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Tour de primera vez para el administrador (Priority: P1)

Como administrador que ingresa por primera vez a su panel después de esta entrega, quiero recibir
una guía que me muestre dónde está la gestión de usuarios y la gestión de especialidades, para no
tener que descubrirlo por prueba y error.

**Why this priority**: El administrador gestiona a los demás usuarios (incluidos doctores nuevos)
y el catálogo de especialidades del que depende el agendado de citas; es el rol con más superficie
de panel a explicar y el que más control tiene sobre el resto del sistema.

**Independent Test**: Puede probarse de forma aislada iniciando sesión por primera vez con una
cuenta de administrador que nunca ha visto el tour y verificando que el tour arranca solo, cubre
gestión de usuarios y especialidades, y no vuelve a arrancar solo en un segundo acceso.

**Acceptance Scenarios**:

1. **Given** un administrador que nunca ha visto el tour de su rol, **When** ingresa a su panel
   principal, **Then** el tour arranca automáticamente y recorre, como mínimo, la sección de
   gestión de usuarios y la sección de gestión de especialidades.
2. **Given** un administrador que ya completó o cerró el tour de su rol, **When** vuelve a
   ingresar a su panel principal, **Then** el tour no arranca automáticamente de nuevo.

---

### User Story 2 - Tour de primera vez para el doctor (Priority: P2)

Como doctor que ingresa por primera vez a mi panel, quiero una guía que me muestre mi agenda y mi
perfil, para entender rápido dónde reviso mis citas y dónde actualizo mis datos.

**Why this priority**: La agenda y el perfil son las dos pantallas de uso diario del doctor; se
prioriza después del administrador porque no depende de configuración previa de otro rol para
tener valor.

**Independent Test**: Puede probarse de forma aislada iniciando sesión por primera vez con una
cuenta de doctor que nunca ha visto el tour y verificando que recorre agenda y perfil, sin
necesidad de que el tour de administrador exista todavía.

**Acceptance Scenarios**:

1. **Given** un doctor que nunca ha visto el tour de su rol, **When** ingresa a su panel
   principal, **Then** el tour arranca automáticamente y recorre, como mínimo, su agenda y su
   perfil.
2. **Given** un doctor que ya completó o cerró el tour de su rol, **When** vuelve a ingresar a su
   panel principal, **Then** el tour no arranca automáticamente de nuevo.

---

### User Story 3 - Tour de primera vez para el paciente (Priority: P3)

Como paciente que ingresa por primera vez a mi panel, quiero una guía que me muestre cómo agendar
una cita, para completar mi primer agendado sin confusión.

**Why this priority**: Es el rol con el flujo más orientado a una sola acción crítica (agendar);
se prioriza al final porque su tour es el más acotado en alcance de los tres.

**Independent Test**: Puede probarse de forma aislada iniciando sesión por primera vez con una
cuenta de paciente que nunca ha visto el tour y verificando que recorre el flujo de agendado de
cita.

**Acceptance Scenarios**:

1. **Given** un paciente que nunca ha visto el tour de su rol, **When** ingresa a su panel
   principal, **Then** el tour arranca automáticamente y recorre, como mínimo, los pasos para
   agendar una cita.
2. **Given** un paciente que ya completó o cerró el tour de su rol, **When** vuelve a ingresar a
   su panel principal, **Then** el tour no arranca automáticamente de nuevo.

---

### User Story 4 - Relanzar el tour desde un botón de ayuda (Priority: P4)

Como usuario de cualquier rol que ya vio (o cerró) el tour de su panel, quiero poder volver a
verlo cuando yo lo decida, para refrescar la guía sin tener que pedirle a alguien más que me
explique de nuevo.

**Why this priority**: Depende de que exista al menos un tour (US1, US2 o US3) para tener algo que
relanzar; se prioriza al final porque es un complemento a los tres tours, no un tour en sí mismo.

**Independent Test**: Puede probarse de forma aislada con cualquiera de los tres roles: cerrar o
completar el tour, hacer clic en el botón de ayuda del panel, y verificar que el tour del rol
correspondiente vuelve a mostrarse desde el inicio.

**Acceptance Scenarios**:

1. **Given** un usuario autenticado de cualquier rol que ya vio el tour de su panel, **When** hace
   clic en el botón de ayuda de ese panel, **Then** el tour de su rol vuelve a mostrarse desde el
   primer paso.
2. **Given** un usuario autenticado de cualquier rol, **When** relanza el tour desde el botón de
   ayuda y lo completa o lo cierra, **Then** el tour sigue disponible para volver a relanzarse las
   veces que el usuario lo pida.

---

### Edge Cases

- El elemento destino de un paso intermedio del tour no existe en el DOM en el momento en que le
  toca mostrarse (por ejemplo, un botón oculto por falta de datos o de permisos): el tour omite ese
  paso y continúa con el siguiente, sin mostrar un error al usuario.
- El elemento destino del primer paso de un tour no existe en el DOM: el tour completo se omite sin
  bloquear el uso normal del panel.
- Un usuario cierra el tour manualmente antes de llegar al último paso: el tour se marca como visto
  igual, y no vuelve a arrancar solo; solo el botón de ayuda lo relanza.
- Un usuario cambia de rol (por ejemplo, un doctor promovido a administrador): el tour del rol
  nuevo se trata como no visto para ese usuario y arranca automáticamente la primera vez que
  accede al panel de ese rol nuevo.
- Un usuario navega fuera del panel a la mitad del tour (por ejemplo, sigue un enlace): el tour no
  debe dejar la interfaz en un estado inconsistente (overlays visibles sin tour activo) al volver a
  entrar.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema DEBE iniciar automáticamente el tour correspondiente al rol del usuario
  (`admin`, `doctor` o `patient`) la primera vez que ese usuario ingresa a su panel principal.
- **FR-002**: El tour del rol `admin` DEBE cubrir, como mínimo, la sección de gestión de usuarios
  y la sección de gestión de especialidades.
- **FR-003**: El tour del rol `doctor` DEBE cubrir, como mínimo, su agenda y su perfil.
- **FR-004**: El tour del rol `patient` DEBE cubrir, como mínimo, los pasos para agendar una cita.
- **FR-005**: El sistema DEBE registrar, de forma persistente y asociada a la cuenta del usuario,
  que ya vio (o cerró) el tour de su rol actual, para no volver a iniciarlo automáticamente.
- **FR-006**: El sistema DEBE ofrecer un botón de ayuda visible en el panel de cada rol que permita
  relanzar el tour de ese rol en cualquier momento, sin importar si ya fue visto.
- **FR-007**: Cada paso de un tour DEBE poder recorrerse usando únicamente el teclado (avanzar,
  retroceder y cerrar el tour), sin requerir un dispositivo señalador.
- **FR-008**: Cuando el elemento destino de un paso no exista en el DOM al momento de mostrarse, el
  sistema DEBE omitir ese paso y continuar con el siguiente, sin interrumpir el tour ni mostrar un
  error visible al usuario.
- **FR-009**: Cuando el elemento destino del primer paso de un tour no exista en el DOM, el sistema
  DEBE omitir el tour completo sin bloquear el uso normal del panel.
- **FR-010**: El usuario DEBE poder cerrar un tour en cualquier paso sin que esto le impida usar el
  panel con normalidad inmediatamente después.
- **FR-011**: Si un usuario cambia de rol, el sistema DEBE tratar el tour del rol nuevo como no
  visto y mostrarlo automáticamente la primera vez que ese usuario ingrese al panel de ese rol.

### Key Entities

- **Tour**: secuencia ordenada de pasos asociada a un rol (`admin`, `doctor` o `patient`); cada
  paso identifica una sección de la interfaz a destacar y un texto explicativo asociado.
- **User** (existente): dueño del estado "tour visto" por rol; una misma cuenta puede tener más de
  un rol a lo largo del tiempo y, por tanto, más de un estado de tour (uno por rol que haya tenido).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El 100% de los usuarios que ingresan por primera vez a su panel después de esta
  entrega ven arrancar el tour de su rol sin ninguna acción adicional de su parte.
- **SC-002**: El 100% de los accesos posteriores al primero, de un usuario que ya vio o cerró el
  tour de su rol actual, no disparan el arranque automático del tour, salvo que use el botón de
  ayuda.
- **SC-003**: El botón de ayuda relanza el tour completo del panel activo en menos de 1 segundo
  desde el clic, en el 100% de los casos probados.
- **SC-004**: En el 100% de las pruebas en las que se oculta deliberadamente el elemento destino de
  un paso, el tour continúa (si el paso ausente no es el primero) u omite el tour completo (si es
  el primero), sin ningún error visible en la interfaz.
- **SC-005**: El 100% de los pasos de los tres tours son completables usando solo teclado (avanzar,
  retroceder, cerrar) en las pruebas de accesibilidad.

## Assumptions

- La biblioteca usada para implementar los tours es `driver.js@1.8.0`, versión fijada por las
  restricciones globales de esta entrega; esta especificación no depende de su API concreta y
  describe el comportamiento esperado con independencia de la biblioteca elegida.
- El estado "tour visto" se guarda asociado a la cuenta del usuario (no únicamente al navegador o
  dispositivo local), para que el comportamiento de FR-005 sea consistente si el usuario cambia de
  dispositivo; el mecanismo concreto de almacenamiento (por ejemplo, un campo nuevo vinculado al
  usuario) es una decisión del plan técnico, no de esta especificación.
- Cerrar el tour manualmente antes de terminarlo cuenta como "visto" para efectos de no repetirlo
  de forma automática; el botón de ayuda es el único mecanismo para volver a verlo por decisión
  propia.
- El contenido textual exacto de cada paso (la redacción que lee el usuario) se define durante la
  implementación con base en la interfaz vigente de cada panel; esta especificación fija qué
  secciones cubre cada tour (FR-002 a FR-004), no la redacción literal de los textos.
- Completar un tour no es obligatorio ni bloquea ninguna funcionalidad del panel: un usuario puede
  cerrarlo u omitir pasos y seguir usando el sistema con normalidad (ver FR-010).
- Los tres roles del sistema (`admin`, `doctor`, `patient`) son, y seguirán siendo mientras esta
  especificación esté vigente, los únicos con panel principal propio; un rol nuevo que se agregue
  a futuro requeriría una ampliación de esta especificación, no está cubierto aquí.
