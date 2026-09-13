---
name: "generar-spec-modulo"
description: "Genera una especificación funcional (spec.md) en el formato de .specify/templates/spec-template.md para un módulo o requerimiento nuevo de MedSchedule, a partir de las migraciones y modelos existentes. Usar al definir un módulo o requerimiento nuevo, antes de escribir código."
argument-hint: "Describe el módulo o requerimiento nuevo (por ejemplo: 'recordatorios de citas por correo')"
compatibility: "Requiere la estructura de spec-kit instalada en .specify/"
metadata:
  author: "equipo-medschedule"
  source: "spec-driven-development"
user-invocable: true
disable-model-invocation: false
---

## Cuándo usar esta skill

Antes de escribir código para un módulo o requerimiento nuevo de MedSchedule. Si el trabajo es
una corrección pequeña sobre un módulo ya especificado, no se necesita spec nueva. Esta skill
**no reemplaza** a `/speckit-specify`: la complementa. `/speckit-specify` crea el directorio
`specs/NNN-nombre/` y copia la plantilla en blanco a partir de una descripción en lenguaje
natural; esta skill hace el trabajo previo de **investigación del dominio MedSchedule**
(migraciones, modelos, roles afectados) para que el contenido de esa spec sea concreto y
verificable, no genérico. El resultado de esta skill es el texto listo para pegar en el
`spec.md` que produce `/speckit-specify`, o para actualizar uno ya creado.

## Entradas que necesita

- Una descripción en lenguaje natural del módulo o requerimiento (el `$ARGUMENTS` del usuario).
- Lectura del código existente relevante (ver "Pasos").
- `.specify/memory/constitution.md` (autoridad de estilo y seguridad del proyecto).
- `.specify/templates/spec-template.md` (formato exacto de salida).

## Pasos

1. **Leer la constitución del proyecto**: `.specify/memory/constitution.md`. Sus principios
   (estilo de código en español/snake_case, manejo de errores explícito, gestión de secretos,
   validación de entrada, no exponer errores internos, spec-driven development) son la
   autoridad; ninguna spec generada por esta skill puede contradecirlos.

2. **Leer las migraciones y modelos relevantes** bajo `database/migrations/` y `app/Models/`
   para el área que toca el módulo descrito. Los modelos centrales de MedSchedule son:
   `User`, `DoctorProfile`, `PatientProfile`, `Specialty`, `Schedule`, `Appointment`,
   `AppointmentHistory`, `ActivityLog`. Identificar:
   - Qué tablas/columnas ya existen y cuáles tendría que agregar el módulo nuevo.
   - Qué relaciones (`hasOne`, `belongsTo`, etc.) conectan al módulo con las entidades
     existentes.
   - Si el módulo toca autorización, revisar `routes/web.php` para ver el patrón vigente
     (`Route::middleware(['auth','role:admin'])` vía Spatie) y el middleware legado
     `EnsureAdminRole`, que está en proceso de reemplazo: una spec nueva **nunca** debe asumir
     `EnsureAdminRole` como mecanismo definitivo; debe describir el requisito de autorización en
     términos de rol (admin/doctor/patient), dejando el mecanismo concreto para el plan técnico.

3. **Identificar los roles afectados**. Los tres roles del sistema son `admin`, `doctor` y
   `patient`, siempre en **minúscula** (hubo un bug histórico por usar mayúsculas en
   comparaciones de rol; la spec debe declarar explícitamente el valor esperado en minúscula
   cuando el requisito dependa del rol, para que quien implemente no repita ese bug). Para cada
   historia de usuario, anotar qué rol(es) la ejecutan y qué rol(es) quedan excluidos.

4. **Redactar la spec en el formato de `.specify/templates/spec-template.md`**, preservando el
   orden de secciones y encabezados de la plantilla:
   - `# Feature Specification: [FEATURE NAME]`, `**Feature Branch**`, `**Created**`,
     `**Status**: Draft`, `**Input**`.
   - `## User Scenarios & Testing` con historias de usuario priorizadas (P1, P2, P3...), cada
     una con título, "Why this priority", "Independent Test" y "Acceptance Scenarios" en formato
     **Given/When/Then**.
   - `### Edge Cases` con casos límite reales del dominio (por ejemplo: doble reserva del mismo
     horario, rol incorrecto intentando acceder, datos duplicados).
   - `## Requirements` con Functional Requirements numerados (`FR-001`, `FR-002`, ...), cada uno
     **verificable** (se puede convertir en un caso de prueba sin ambigüedad) y **sin detalles
     de implementación** (nada de nombres de clases, rutas de archivo, queries SQL o frameworks;
     eso pertenece al plan, no a la spec). Incluir `### Key Entities` solo si el módulo agrega o
     modifica datos.
   - `## Success Criteria` con `SC-001`, `SC-002`, ... medibles y agnósticos de tecnología.
   - `## Assumptions` documentando cualquier supuesto razonable tomado en vez de preguntar.

5. **Marcar ambigüedades reales** con `[NEEDS CLARIFICATION: pregunta específica]`, máximo 3,
   solo cuando no exista un valor por defecto razonable. No usar esta marca para detalles que
   se puedan inferir del dominio (por ejemplo, el valor de rol siempre es minúscula; eso no se
   pregunta, se declara).

6. **Verificar antes de entregar**:
   - Ningún requisito menciona una clase, ruta de archivo o tecnología concreta.
   - Todo requisito funcional es verificable (puede convertirse en un caso de prueba).
   - Los roles citados son exactamente `admin`, `doctor`, `patient` en minúscula.
   - No hay datos sensibles, claves ni credenciales de ejemplo reales (usar marcadores de
     posición, nunca valores de `.env`).
   - El texto está en español con acentuación correcta.

## Formato exacto de salida

El texto entregado sigue **al pie de la letra** la estructura de
`.specify/templates/spec-template.md` (las mismas secciones, mismo orden, mismos encabezados).
Si el usuario ya tiene un directorio `specs/NNN-nombre/` creado por `/speckit-specify`, esta
skill escribe/actualiza `specs/NNN-nombre/spec.md`; si no existe todavía, entrega el texto listo
para que el usuario lo pegue al correr `/speckit-specify` o `/speckit-plan`.

## Errores que evita

- **Specs con detalles de implementación** (nombres de controladores, rutas, SQL) que deberían
  vivir en el plan, no en la spec — viola el principio "WHAT no HOW" de spec-kit.
- **Requisitos de rol ambiguos o con mayúscula** (`Admin`, `Doctor`, `Patient`) — repite el bug
  histórico de comparación de roles.
- **Specs que asumen el middleware legado `EnsureAdminRole`** como mecanismo permanente, cuando
  está siendo reemplazado por Spatie (`role:admin`, `role:doctor`, `role:patient`).
- **Requisitos no verificables** ("el sistema debe ser rápido") en vez de criterios medibles
  ("SC-001: la lista de citas carga en menos de 2 segundos").
- **Secretos o datos reales de `.env`** filtrados como ejemplo en la spec — el repositorio es
  público.
