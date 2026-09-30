# Feature Specification: Observabilidad y auditoría de MedSchedule

**Feature Branch**: `003-observabilidad-auditoria`

**Created**: 2026-09-23

**Status**: Draft

**Input**: User description: "Unidad 3 de Desarrollo Web Profesional. A partir de un caso de estudio que describa y justifique el pipeline de liberación y despliegue continuo (entorno requerido, niveles de servicio acordados, métricas de monitoreo y parámetros de configuración de las herramientas), y con un repositorio que contenga los scripts del pipeline, entregar usando SDD: 1) métricas para el monitoreo de la aplicación con alarmas y alertas; 2) visor de trazabilidad con registros (logs) y trazas; 3) visor de auditoría. Además, atender la observación de la unidad 2: 'no localizo los dashboard de sonarqube'."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Dashboards de análisis estático localizables (Priority: P1)

Como evaluador de la entrega, necesito encontrar los dashboards del análisis estático de
MedSchedule sin buscarlos: en el documento de entrega y en el pull request deben verse como
imágenes, al principio, no como rutas de archivo al final. Además, la puerta de calidad del
análisis debe formar parte del pipeline, de modo que una liberación que no la supera se detenga.

**Why this priority**: Es una observación pendiente de la unidad anterior que ya costó puntos; es
la de menor esfuerzo y la de efecto más directo sobre la evaluación.

**Independent Test**: Abrir el documento de entrega y el pull request general y verificar que los
dashboards se ven como imagen sin navegar a otra ubicación; forzar una corrida del pipeline que no
supera la puerta de calidad y verificar que la liberación se detiene.

**Acceptance Scenarios**:

1. **Given** el documento de entrega de la unidad 3, **When** el evaluador lo abre, **Then** el
   primer apartado muestra los dashboards del análisis estático como imágenes incrustadas.
2. **Given** el pull request general de la unidad 3, **When** el evaluador abre su descripción,
   **Then** los dashboards se ven renderizados como imagen en la propia página del pull request.
3. **Given** un cambio que no supera la puerta de calidad, **When** corre el pipeline de
   liberación, **Then** el pipeline termina en estado fallido antes de la etapa de despliegue.
4. **Given** un cambio que supera la puerta de calidad, **When** corre el pipeline de liberación,
   **Then** el pipeline continúa a las etapas siguientes.

---

### User Story 2 - Monitoreo con alertas ligadas a los niveles de servicio (Priority: P2)

Como responsable de operación de MedSchedule, necesito ver en tableros el estado de la aplicación
(tráfico, errores, tiempos de respuesta, disponibilidad, actividad de citas y estado de la base de
datos) y recibir una alerta cuando la aplicación se cae, se vuelve lenta o empieza a fallar, con
umbrales tomados de los niveles de servicio acordados en la unidad 2. Hoy, si la aplicación se cae
o se degrada, nadie se entera hasta que un usuario se queja.

**Why this priority**: Es el primer punto del enunciado y la base del visor de trazabilidad, que
se monta sobre el mismo tablero.

**Independent Test**: Levantar la aplicación y el monitoreo, generar tráfico y verificar que los
tableros muestran datos reales; detener la aplicación e inyectar latencia y verificar que la
alerta correspondiente se dispara y llega una notificación.

**Acceptance Scenarios**:

1. **Given** la aplicación recibiendo tráfico, **When** el operador abre el tablero de servicio,
   **Then** ve la tasa de peticiones, la tasa de errores y los percentiles de tiempo de respuesta
   comparados contra el objetivo acordado.
2. **Given** la aplicación detenida durante más de un minuto, **When** el sistema de monitoreo
   evalúa sus reglas, **Then** se dispara la alerta de aplicación caída y llega una notificación.
3. **Given** un tiempo de respuesta p95 por encima del objetivo acordado durante cinco minutos,
   **When** el sistema evalúa sus reglas, **Then** se dispara la alerta de latencia fuera de
   acuerdo.
4. **Given** una petición sin la credencial de acceso al endpoint de métricas, **When** llega al
   sistema, **Then** el sistema responde como si el endpoint no existiera.
5. **Given** el almacenamiento de métricas no disponible, **When** un usuario usa la aplicación,
   **Then** la aplicación responde con normalidad.

---

### User Story 3 - Visor de trazabilidad con logs y trazas enlazados (Priority: P3)

Como desarrollador que investiga un incidente, necesito buscar los registros de la aplicación por
nivel, ruta y usuario, y ver para una petición concreta en qué se fue el tiempo (consultas a la
base de datos, trabajos en cola, llamadas a servicios externos), pudiendo saltar de una línea de
registro a la traza de su petición y viceversa. Hoy solo existe un archivo de texto plano sin
forma de relacionar líneas con peticiones ni de ver dónde se pierde el tiempo.

**Why this priority**: Depende del tablero del punto de monitoreo; aporta diagnóstico después de
que la alerta avisa.

**Independent Test**: Hacer una petición al endpoint más lento medido en la unidad 2, localizar su
traza y verificar que muestra las consultas que la componen; desde una línea de registro de esa
petición llegar a su traza; provocar un error interno y localizarlo por el folio mostrado al
usuario.

**Acceptance Scenarios**:

1. **Given** una petición atendida, **When** el desarrollador abre su traza, **Then** ve un
   desglose temporal con la petición y cada consulta a la base de datos que ejecutó.
2. **Given** una línea de registro de una petición, **When** el desarrollador la selecciona,
   **Then** puede abrir directamente la traza de esa petición, y desde la traza ver sus registros.
3. **Given** una petición que termina en error interno, **When** el usuario ve la página de error,
   **Then** ve un folio de seguimiento y ningún detalle interno, y ese folio localiza la traza.
4. **Given** un registro cuyo contexto incluye contraseñas, tokens, correos o datos clínicos,
   **When** se escribe, **Then** esos valores aparecen enmascarados.
5. **Given** una consulta con parámetros, **When** se registra en la traza, **Then** aparece la
   sentencia sin los valores de los parámetros.

---

### User Story 4 - Visor de auditoría con detección de manipulación (Priority: P4)

Como administrador de MedSchedule, necesito saber quién creó, modificó o eliminó información
sensible (citas, usuarios, roles, especialidades, horarios y perfiles), qué cambió exactamente,
cuándo y desde dónde, y tener la certeza de que ese registro no fue alterado. Hoy solo se registran
tres eventos (inicio de sesión y cambios de perfil del paciente), y cualquiera con acceso a la base
de datos puede editar el registro sin que nadie lo note.

**Why this priority**: No depende del monitoreo y se puede entregar en paralelo; se ordena al
final porque reutiliza el enlace a trazas del punto anterior cuando existe.

**Independent Test**: Como administrador, modificar la fecha y el doctor de una cita y verificar
que el visor muestra el cambio con valores antes y después; alterar a mano un registro de
auditoría en la base de datos y verificar que la verificación de integridad señala ese registro.

**Acceptance Scenarios**:

1. **Given** un administrador que modifica una cita, **When** abre el visor de auditoría,
   **Then** ve quién hizo el cambio, cuándo, desde qué IP y los valores antes y después de cada
   campo modificado.
2. **Given** un cambio de contraseña o de datos clínicos, **When** se audita, **Then** el registro
   indica que el campo cambió sin guardar su valor.
3. **Given** un registro de auditoría alterado directamente en la base de datos, **When** corre la
   verificación de integridad, **Then** el sistema señala el primer registro roto y el visor
   muestra la integridad como comprometida.
4. **Given** un intento de modificar o eliminar un registro de auditoría desde la aplicación,
   **When** se ejecuta, **Then** el sistema lo rechaza.
5. **Given** un usuario sin rol de administrador, **When** intenta abrir el visor de auditoría,
   **Then** el sistema le niega el acceso con código 403.
6. **Given** un administrador que exporta la auditoría filtrada, **When** abre el archivo en una
   hoja de cálculo, **Then** ninguna celda se interpreta como fórmula y la exportación misma
   quedó auditada.

### User Story 5 - Compuerta de vulnerabilidades en dependencias (Priority: P5)

Como responsable de la liberación, necesito que el pipeline detecte dependencias PHP y JS con
vulnerabilidades conocidas antes de desplegar, en lugar de enterarme por un aviso externo después
de que el código ya está en producción. Hoy `composer.lock` y `package-lock.json` no se analizan en
ningún punto del pipeline.

**Why this priority**: No depende del monitoreo, la trazabilidad ni la auditoría; se agrega al
final como módulo adicional porque su valor es independiente de los otros tres puntos.

**Independent Test**: Introducir una dependencia con una vulnerabilidad de severidad alta o crítica
conocida y verificar que el pipeline se detiene antes de las pruebas y el despliegue; quitarla y
verificar que el pipeline continúa; quitar el token y verificar que el job se omite con un aviso
visible; aceptar un hallazgo en `.snyk` con justificación y fecha de expiración y verificar que no
bloquea hasta que esa fecha llega.

**Acceptance Scenarios**:

1. **Given** una dependencia de `composer.lock` o `package-lock.json` con una vulnerabilidad de
   severidad alta o crítica, **When** corre el pipeline de liberación, **Then** el pipeline termina
   en estado fallido antes de las etapas de pruebas y despliegue.
2. **Given** ninguna dependencia con vulnerabilidad de severidad alta o crítica, **When** corre el
   pipeline de liberación, **Then** el pipeline continúa a las etapas siguientes.
3. **Given** que no existe el secreto `SNYK_TOKEN` en el repositorio, **When** corre el pipeline de
   liberación, **Then** el job de análisis de dependencias se omite y queda un aviso visible en la
   corrida, sin detener el pipeline por esa causa.
4. **Given** un hallazgo aceptado en `.snyk` con justificación y fecha de expiración vigente,
   **When** corre el análisis, **Then** ese hallazgo no bloquea la liberación hasta que la fecha de
   expiración se cumple.

### Edge Cases

- ¿Qué pasa si el almacenamiento de métricas o el receptor de trazas no está disponible? La
  aplicación sigue respondiendo; solo se registra una advertencia.
- ¿Qué pasa con la cardinalidad de métricas cuando las URLs llevan identificadores? Las métricas
  se etiquetan por nombre de ruta, no por URL.
- ¿Qué pasa con los registros de auditoría creados antes de esta funcionalidad, que no tienen
  sello de integridad? Se sellan una sola vez al adoptarla y el documento lo declara.
- ¿Qué pasa si dos cambios se auditan al mismo tiempo? El encadenamiento se calcula de forma
  serializada para que la cadena no se bifurque.
- ¿Qué pasa si falta la llave de integridad de auditoría en producción? La aplicación no arranca.
- ¿Qué pasa si un intento de inicio de sesión fallido usa un correo real? Se audita con el correo
  enmascarado.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El documento de entrega DEBE mostrar los dashboards del análisis estático como
  imágenes en su primer apartado.
- **FR-002**: La descripción del pull request general DEBE mostrar los dashboards del análisis
  estático como imágenes renderizadas.
- **FR-003**: El pipeline de liberación DEBE detenerse antes del despliegue cuando el análisis
  estático no supera la puerta de calidad.
- **FR-004**: El sistema DEBE exponer métricas de tráfico, errores y tiempos de respuesta por
  ruta, y métricas de negocio de citas y trabajos fallidos.
- **FR-005**: El endpoint de métricas DEBE responder solo a quien presente la credencial
  configurada y comportarse como inexistente para cualquier otro.
- **FR-006**: El sistema de monitoreo DEBE ofrecer tableros de servicio, de negocio y de base de
  datos con datos reales.
- **FR-007**: El sistema de monitoreo DEBE disparar alertas por aplicación caída, latencia fuera
  de acuerdo, latencia degradada, tasa de errores alta, base de datos caída y trabajos fallidos,
  con umbrales derivados de los niveles de servicio de la unidad 2.
- **FR-008**: Toda alerta disparada DEBE producir una notificación verificable.
- **FR-009**: El sistema DEBE escribir sus registros en formato estructurado, cada línea con el
  identificador de traza, de petición, usuario y ruta.
- **FR-010**: El sistema DEBE enmascarar contraseñas, tokens, cabeceras de autorización, cookies,
  correos y datos clínicos en los registros.
- **FR-011**: El sistema DEBE generar una traza por petición con el desglose de consultas a base
  de datos, trabajos en cola y llamadas a servicios externos, sin incluir valores de parámetros.
- **FR-012**: El visor DEBE permitir pasar de una línea de registro a su traza y de una traza a
  sus registros.
- **FR-013**: Las páginas de error interno DEBEN mostrar un folio de seguimiento sin detalles
  internos. Con las trazas activas el folio localiza la traza; si están apagadas, el folio de
  respaldo es el identificador de la petición, que localiza sus registros.
- **FR-014**: El sistema DEBE auditar la creación, modificación y eliminación de citas, usuarios,
  especialidades, horarios y perfiles de doctor y paciente, con valores antes y después de los
  campos modificados.
- **FR-015**: El sistema DEBE auditar inicios y cierres de sesión, intentos fallidos,
  restablecimientos de contraseña, cambios de roles y permisos, y accesos denegados a áreas de
  administración.
- **FR-016**: El sistema NUNCA DEBE guardar en la auditoría el valor de contraseñas ni de datos
  clínicos (incluidos el motivo de consulta y las observaciones de una cita).
- **FR-017**: Los registros de auditoría DEBEN ser de solo agregado y estar encadenados con un
  sello que dependa de una llave secreta, de modo que una alteración sea detectable.
- **FR-018**: El sistema DEBE ofrecer una verificación de integridad que identifique el primer
  registro alterado, ejecutable a demanda y de forma programada diaria.
- **FR-019**: El visor de auditoría DEBE ser exclusivo del rol administrador y permitir filtrar
  por usuario, acción, entidad, rango de fechas e IP.
- **FR-020**: El visor de auditoría DEBE mostrar el detalle antes/después, la línea de tiempo de
  una entidad, el estado de integridad y, cuando exista, un enlace a la traza de la petición.
- **FR-021**: El visor de auditoría DEBE permitir exportar a CSV con escape contra inyección de
  fórmulas, y auditar la exportación.
- **FR-022**: Todo input de los visores DEBE validarse antes de procesarse.
- **FR-023**: El pipeline DEBE analizar las dependencias declaradas en `composer.lock` (PHP) y
  `package-lock.json` (JS) en busca de vulnerabilidades conocidas.
- **FR-024**: El pipeline DEBE detener la liberación antes de las pruebas y el despliegue cuando el
  análisis encuentra una vulnerabilidad de severidad alta o crítica.
- **FR-025**: Un hallazgo NUNCA DEBE quedar exento de la compuerta salvo que esté aceptado en el
  archivo de política del análisis con una justificación y una fecha de expiración; al cumplirse
  esa fecha vuelve a bloquear.
- **FR-026**: El token de autenticación del análisis de dependencias NUNCA DEBE versionarse; se lee
  únicamente de una variable de entorno en tiempo de ejecución.

### Key Entities

- **ActivityLog**: registro de auditoría existente. Se amplía con el identificador de traza y el
  sello de integridad encadenado; pasa a ser de solo agregado.
- **Appointment, User, Specialty, Schedule, DoctorProfile, PatientProfile**: entidades auditadas.
- **Métrica**: serie temporal etiquetada por ruta, método y estado; no es una tabla de la
  aplicación.
- **Traza / span**: árbol temporal de una petición; vive fuera de la base de datos de la
  aplicación.
- **Alerta**: regla con umbral derivado de un nivel de servicio y una notificación asociada.
- **Excepción de vulnerabilidad**: hallazgo del análisis de dependencias aceptado en la política del
  repositorio con justificación y fecha de expiración; deja de estar exenta al expirar.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El evaluador localiza los dashboards del análisis estático en el primer apartado del
  documento y en la descripción del pull request general sin abrir otro archivo.
- **SC-002**: El 100 % de las corridas del pipeline que no superan la puerta de calidad terminan
  en estado fallido antes del despliegue.
- **SC-003**: Ante una caída provocada de la aplicación, la notificación de alerta llega en menos
  de 3 minutos.
- **SC-004**: El 100 % de las peticiones atendidas con trazas activas tienen una traza localizable
  a partir del identificador devuelto en la respuesta.
- **SC-005**: El 0 % de las líneas de registro, spans y registros de auditoría generados en las
  pruebas contiene contraseñas, tokens o datos clínicos en claro.
- **SC-006**: El número de tipos de evento auditados pasa de 3 a al menos 18 (3 operaciones × 6
  entidades, más eventos de sesión, roles y accesos denegados).
- **SC-007**: El 100 % de las alteraciones manuales de registros de auditoría en las pruebas son
  detectadas por la verificación de integridad, que señala el registro exacto.
- **SC-008**: La suite de pruebas de la aplicación permanece en verde en CI con el monitoreo y las
  trazas desactivados.
- **SC-009**: El 100 % de las corridas del pipeline con una dependencia de severidad alta o crítica
  sin excepción vigente en la política terminan el job de análisis de dependencias en estado
  fallido antes de las etapas de pruebas y despliegue.

## Assumptions

- El entorno de liberación es el adoptado en la unidad 2 (GitHub Codespaces con devcontainer) y el
  trabajo se apila sobre la rama de la unidad 2 (`feat/100-sonarqube`, PR #104), que aún no está
  integrada en `develop`.
- Los niveles de servicio vigentes son los de `docs/entrega-u2/03-niveles-de-servicio.md`
  (p95 < 5000 ms, errores < 1 %, disponibilidad tras desplegar).
- Las notificaciones de alerta se entregan a un buzón de pruebas local; no se usa correo real,
  Slack ni Telegram.
- El monitoreo, las trazas y el análisis estático corren en contenedores locales o en el
  Codespace; su despliegue en un entorno productivo está fuera de alcance.
- La vista de logs de actividad existente (`/admin/logs`) se conserva sin cambios; el visor de
  auditoría es una vista nueva.
- Las decisiones técnicas (herramientas y versiones) se documentan en `plan.md`, no aquí.
- No se reentrega la unidad 2; la observación sobre SonarQube se atiende en esta unidad.
- El análisis de dependencias corre con la cuenta gratuita de Snyk del autor.
