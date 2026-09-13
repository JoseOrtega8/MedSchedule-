# MedSchedule — Entrega de Documentación, Pruebas y CI/CD

**Proyecto:** MedSchedule, sistema web de gestión de citas médicas (Laravel 12.53.0).
**Autor de esta entrega:** [`ramonibr`](https://github.com/ramonibr) (GitHub). **Entrega individual.**
**Repositorio:** [`github.com/JoseOrtega8/MedSchedule-`](https://github.com/JoseOrtega8/MedSchedule-) (público).
**Materia:** Desarrollo Web Profesional — Grupo TIDSM8-2, Universidad Tecnológica de Hermosillo.
**Profesor:** Iván Rogelio Chenoweth.
**Fecha de entrega:** 2026-09-12.

## 0. Método de verificación

Los datos de portada (materia, grupo, universidad y profesor) se verificaron abriendo
`README.md` del repositorio (líneas 20-22 y 258). El mapa de rúbrica de la sección 2 se construyó
leyendo íntegros los ocho documentos que enlaza, más `.specify/`, `specs/001-pruebas-e2e/`,
`specs/002-tours-guiados/`, `resources/js/tours/tour-ejemplo.js` y `terraform/`. Como parte de esta
misma tarea se revisó la coherencia de cifras entre los ocho documentos (versiones, conteos de
pruebas, líneas de código citadas) y se corrigió un defecto aritmético encontrado en
`docs/entrega/03-casos-de-prueba.md` §6 (la suma de métodos declaraba "78 + stub contado como 0 =
79", una operación que no cuadra; el total de 79 era correcto, solo la frase que lo explicaba
estaba mal redactada). El detalle completo de esa revisión de coherencia, con el resultado de los
barridos de marcadores y secretos, vive en el reporte de esta tarea
(`.superpowers/sdd/2026-09-08-entrega-sdd-cicd/tarea-17-report.md`), fuera del entregable académico.

Este índice no enlaza ni menciona ningún documento fuera de `docs/entrega/` y `docs/sdd/` que
contenga credenciales; el repositorio es público y ninguna clave, token ni contraseña aparece en
esta entrega.

## 1. Índice de documentos

| # | Documento | Contenido |
|---|---|---|
| 00 | [Índice y mapa de rúbrica](00-indice.md) | Este documento |
| 01 | [Configuración de herramientas](01-configuracion-herramientas.md) | Inventario de 16 herramientas, parámetros de configuración, planeación de uso, instalación e implementación |
| 02 | [Plan de pruebas](02-plan-de-pruebas.md) | Plan formal, ejecución real con evidencia, taxonomía de los 15 fallos de PHPUnit, y descripción del PR mínimo (skills + specs) |
| 03 | [Casos de prueba](03-casos-de-prueba.md) | Matriz de 58 casos: 38 existentes (79 métodos de PHPUnit + 1 spec de Playwright) y 20 propuestos |
| 04 | [Flujo de trabajo CI/CD](04-flujo-cicd.md) | Modelo de ramas, convenciones de commit/issue/PR, y análisis línea por línea de `ci.yml` y `cd-railway.yml` |
| 05 | [Estrategia de despliegue](05-estrategia-despliegue.md) | Entornos, elección de Railway, diseño del pipeline de despliegue, manejo de secretos, y cuatro defectos de despliegue verificados más un incidente de seguridad ya resuelto |
| — | [Propuesta de SDD](../sdd/sdd-proposal.md) | Diagnóstico del sistema por ingeniería inversa y propuesta de adopción de Spec-Driven Development |
| — | [Guía de implementación de SDD](../sdd/sdd-implementation.md) | Qué es SDD, instalación de spec-kit ya realizada, flujo de trabajo, y las dos specs piloto |

## 2. Mapa de rúbrica

Tabla que enlaza cada requisito del profesor con el archivo y la sección exacta que lo cubre.

| Requisito del profesor | Cubierto en | Sección |
|---|---|---|
| **1.** Parámetros de configuración de las herramientas, planeación de su uso, instalación e implementación | [01-configuracion-herramientas.md](01-configuracion-herramientas.md) | §1 (inventario), §2 (parámetros), §3 (planeación), §4 (instalación), §5 (implementación) |
| **2.** Plan de pruebas, documentación y ejecución del test suite, más el PR mínimo con skills y specs para nuevos módulos | [02-plan-de-pruebas.md](02-plan-de-pruebas.md) | §1-§8 (plan, ejecución real con evidencia, taxonomía de fallos), §9 (el PR mínimo: `.claude/skills/generar-spec-modulo/`, `.claude/skills/generar-casos-prueba/`, `specs/001-pruebas-e2e/`, `specs/002-tours-guiados/`) |
| **3.** Casos de prueba | [03-casos-de-prueba.md](03-casos-de-prueba.md) | §3 (matriz de 38 filas existentes), §4 (20 casos propuestos), §5 (trazabilidad a requisito/spec) |
| **4.** Flujo de trabajo para el control de versiones, CI/CD | [04-flujo-cicd.md](04-flujo-cicd.md) | §1 (modelo de ramas), §2 (convenciones de commit/issue/PR), §3-§4 (pipeline `ci.yml` y su hallazgo central), §5 (`cd-railway.yml`) |
| **5.** Estrategia de despliegue mediante pipelines CI/CD | [05-estrategia-despliegue.md](05-estrategia-despliegue.md) | §1 (entornos), §2 (por qué Railway), §3 (diseño del pipeline), §4-§6 (secretos, migraciones, reversión), §8 (defectos verificados) |
| **Liga a)** Pruebas automáticas — Playwright | [02-plan-de-pruebas.md](02-plan-de-pruebas.md) §3.1 (justificación) y §6.3 (ejecución real); spec piloto [`specs/001-pruebas-e2e/`](../../specs/001-pruebas-e2e/) | — |
| **Liga b)** Tours guiados — driver.js | [01-configuracion-herramientas.md](01-configuracion-herramientas.md) §1 (fila 12, versión `1.8.0` exacta); [`resources/js/tours/tour-ejemplo.js`](../../resources/js/tours/tour-ejemplo.js); spec piloto [`specs/002-tours-guiados/`](../../specs/002-tours-guiados/) | — |
| **Liga c)** Software como infraestructura | [`terraform/`](../../terraform/) — esqueleto declarativo sin aplicar (`init`/`plan`/`apply` no ejecutados). **Sin Codespaces**, porque el autor no dispone de él | [01-configuracion-herramientas.md](01-configuracion-herramientas.md) §2.11, §4; [05-estrategia-despliegue.md](05-estrategia-despliegue.md) §7 |
| **Liga d)** Spec Driven Development con spec-kit | [sdd-proposal.md](../sdd/sdd-proposal.md) y [sdd-implementation.md](../sdd/sdd-implementation.md); [`.specify/`](../../.specify/); specs piloto [`specs/001-pruebas-e2e/`](../../specs/001-pruebas-e2e/) y [`specs/002-tours-guiados/`](../../specs/002-tours-guiados/) | — |
| **Criterio SA** — todos los puntos anteriores más video explicativo | Video explicativo de cinco minutos, entregado por separado | El guión de trabajo con el que se grabó no se versiona: es material de preparación del autor, no un entregable |
| **Criterio DE** — entrega en tiempo | Esta entrega se fecha el 2026-09-12 (portada de este documento), en commit local sobre la rama `feat/unidad-docs-sdd` | — |
| **Criterio AU** — módulo adicional | **No se entrega.** Decisión expresa del autor: esta unidad se enfoca en documentación, pruebas y CI/CD sobre el sistema ya construido, no en agregar un módulo funcional nuevo | — |

## 3. Pull request de esta entrega

Todo el contenido de este documento se integra mediante un solo pull request, que es el
"PR mínimo" que solicita el punto 2 de la rúbrica: incluye las dos skills de inteligencia
artificial y las dos specs piloto con las que se aborda un módulo o requerimiento nuevo.

**Pull request:** https://github.com/JoseOrtega8/MedSchedule-/pull/95

El pull request no modifica `.github/workflows/ci.yml` ni ningún archivo de código de la
aplicación. Los defectos encontrados durante el trabajo se documentan con su corrección
propuesta, pero no se corrigen aquí: su implementación corresponde a la unidad siguiente y
está planificada en las tarjetas de la sección 4.

## 4. Planificación de la unidad siguiente en el tablero de kanban

El trabajo de la unidad siguiente ya está planificado en el tablero del equipo,
[MedSchedule Project Board](https://github.com/users/JoseOrtega8/projects/2), derivado de las dos
specs piloto (`specs/001-pruebas-e2e/` y `specs/002-tours-guiados/`); las quince tarjetas listadas a
continuación están en la columna `To do`, listas para moverse conforme avance la implementación.

### 4.1 Tarjetas asignadas al autor

Derivadas de la spec `specs/001-pruebas-e2e/` más dos hallazgos de esta misma entrega, asignadas al
autor (`ramonibr`):

| Issue | Título | Origen |
|---|---|---|
| [#80](https://github.com/JoseOrtega8/MedSchedule-/issues/80) | R5 test: preparar la infraestructura de pruebas E2E por rol | Spec 001, fase 1 (Setup) |
| [#81](https://github.com/JoseOrtega8/MedSchedule-/issues/81) | R6 test: sembrar datos y utilidades base para las pruebas E2E | Spec 001, fase 2 (Foundational) |
| [#82](https://github.com/JoseOrtega8/MedSchedule-/issues/82) | R7 test: prueba E2E de autenticación y control de acceso por rol | Spec 001, fase 3, prioridad P1 |
| [#83](https://github.com/JoseOrtega8/MedSchedule-/issues/83) | R8 test: prueba E2E de la agenda del doctor | Spec 001, fase 4, prioridad P2 |
| [#84](https://github.com/JoseOrtega8/MedSchedule-/issues/84) | R9 test: prueba E2E del CRUD de especialidades del administrador | Spec 001, fase 6, prioridad P4 |
| [#85](https://github.com/JoseOrtega8/MedSchedule-/issues/85) | R10 test: pulido y transversales de la suite E2E | Spec 001, fase 7 (Polish y transversales) |
| [#86](https://github.com/JoseOrtega8/MedSchedule-/issues/86) | R11 ci: hacer que el pipeline falle cuando fallan las pruebas | Hallazgo de [04-flujo-cicd.md](04-flujo-cicd.md) |
| [#87](https://github.com/JoseOrtega8/MedSchedule-/issues/87) | R12 fix: corregir los cuatro defectos que impiden el despliegue | Hallazgos de [05-estrategia-despliegue.md](05-estrategia-despliegue.md) |

### 4.2 Tarjetas de tours guiados

Derivadas de la spec `specs/002-tours-guiados/`, una por fase, sin asignar todavía:

| Issue | Título |
|---|---|
| [#88](https://github.com/JoseOrtega8/MedSchedule-/issues/88) | feat: preparar la infraestructura de tours guiados con driver.js |
| [#89](https://github.com/JoseOrtega8/MedSchedule-/issues/89) | feat: base compartida de tours y persistencia de tour visto |
| [#90](https://github.com/JoseOrtega8/MedSchedule-/issues/90) | feat: tour de primera vez para el administrador |
| [#91](https://github.com/JoseOrtega8/MedSchedule-/issues/91) | feat: tour de primera vez para el doctor |
| [#92](https://github.com/JoseOrtega8/MedSchedule-/issues/92) | feat: tour de primera vez para el paciente |
| [#93](https://github.com/JoseOrtega8/MedSchedule-/issues/93) | feat: relanzar el tour desde un botón de ayuda |
| [#94](https://github.com/JoseOrtega8/MedSchedule-/issues/94) | feat: pulido y accesibilidad de los tours guiados |

### 4.3 Notas sobre dos tarjetas que no siguen el patrón

- **Fase 5 de `specs/001-pruebas-e2e/` sin tarjeta propia, a propósito.** Esa fase corresponde al
  agendado y cancelación de cita del paciente, y ya existe el issue
  [#63](https://github.com/JoseOrtega8/MedSchedule-/issues/63), "E4 test: prueba automática del
  flujo de agendar cita", asignado a otro integrante del equipo. No se duplicó la tarjeta para no
  pisar trabajo ajeno; la spec sigue especificando ese flujo, solo que su ejecución corresponde a
  esa tarjeta preexistente.
- **Issue #61 cerrado y movido a `Done`, como ejemplo real del flujo aplicado.** El issue
  [#61](https://github.com/JoseOrtega8/MedSchedule-/issues/61), "R4 test: prueba automática del
  flujo de gestión de usuarios", quedó cerrado porque su trabajo ya estaba mergeado: es la prueba
  `tests/playwright_gestion_usuarios/gestion-usuarios.spec.js` que aparece en la línea base con 1
  prueba aprobada. Es evidencia de que el flujo de kanban documentado en
  [04-flujo-cicd.md](04-flujo-cicd.md) no es solo teoría, sino que ya se aplicó sobre trabajo real.

## 5. Conclusión

Esta entrega documenta el estado real y verificado de MedSchedule: ocho documentos (los cinco
numerados de `docs/entrega/`, los dos de `docs/sdd/`, y este índice) que cubren los cinco puntos
numerados de la rúbrica, las cuatro ligas de apoyo y el criterio SA con su video. Ningún hallazgo
de código existente se corrigió al escribir esta entrega —ningún archivo preexistente de `app/`,
`routes/`, `config/`, `database/`, `tests/`, `resources/` ni `.github/` se modificó; esta entrega
solo agrega archivos nuevos en dos de esas carpetas (`resources/js/tours/tour-ejemplo.js` y su
`README.md`, y `.github/workflows/cd-railway.yml`)—; cada defecto real del sistema queda
documentado: 15 fallos de PHPUnit taxonomizados y un pipeline sin compuertas reales, junto con
cuatro defectos de despliegue, quedan con su fix propuesto para la unidad siguiente; el incidente
de seguridad detectado ya se resolvió en esta misma entrega. El único módulo que esta entrega
decide no construir es el del criterio AU, declarado aquí sin disimularlo.
