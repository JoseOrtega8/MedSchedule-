# MedSchedule — Entrega de Documentación, Pruebas y CI/CD

**Proyecto:** MedSchedule, sistema web de gestión de citas médicas (Laravel 12.53.0).
**Autor de esta entrega:** [`ramonibr`](https://github.com/ramonibr) (GitHub). **Entrega individual.**
**Repositorio:** [`github.com/JoseOrtega8/MedSchedule-`](https://github.com/JoseOrtega8/MedSchedule-) (público).
**Materia:** Desarrollo Web Profesional — Grupo TIDSM8-2, Universidad Tecnológica de Hermosillo.
**Profesor:** Iván Rogelio Chenoweth.
**Fecha de entrega:** 2026-09-10.

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
| 05 | [Estrategia de despliegue](05-estrategia-despliegue.md) | Entornos, elección de Railway, diseño del pipeline de despliegue, manejo de secretos, y cinco defectos verificados |
| 06 | [Guión del video demo](06-guion-video-demo.md) | Guión completo con tiempos, comandos en vivo y plan de contingencia (criterio SA) |
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
| **Criterio SA** — todos los puntos anteriores más video explicativo | [06-guion-video-demo.md](06-guion-video-demo.md) | Guión completo, §2 (tiempos), §3 (secuencia en vivo), §4 (contingencia) |
| **Criterio DE** — entrega en tiempo | Esta entrega se fecha el 2026-09-10 (portada de este documento), en commit local sobre la rama `feat/unidad-docs-sdd` | — |
| **Criterio AU** — módulo adicional | **No se entrega.** Decisión expresa del autor: esta unidad se enfoca en documentación, pruebas y CI/CD sobre el sistema ya construido, no en agregar un módulo funcional nuevo | — |

## 3. Conclusión

Esta entrega documenta el estado real y verificado de MedSchedule: nueve documentos (los seis
numerados de `docs/entrega/`, los dos de `docs/sdd/`, y este índice) que cubren los cinco puntos
numerados de la rúbrica, las cuatro ligas de apoyo y el criterio SA con su video. Ningún hallazgo
de código se corrigió al escribir esta entrega —árbol de trabajo limpio en `app/`, `routes/`,
`config/`, `database/`, `tests/`, `resources/` y `.github/`—; cada defecto real del sistema
(15 fallos de PHPUnit taxonomizados, cinco defectos de despliegue, un pipeline sin compuertas
reales) queda documentado con su fix propuesto para la unidad siguiente. El único módulo que esta
entrega decide no construir es el del criterio AU, declarado aquí sin disimularlo.
