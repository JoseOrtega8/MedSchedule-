# MedSchedule — Unidad 2: liberación continua, pruebas de carga y análisis estático

**Proyecto:** MedSchedule, sistema web de gestión de citas médicas (Laravel 12.53.0).
**Autor de esta entrega:** [`ramonibr`](https://github.com/ramonibr). **Entrega individual.**
**Repositorio:** [`github.com/JoseOrtega8/MedSchedule-`](https://github.com/JoseOrtega8/MedSchedule-) (público).
**Materia:** Desarrollo Web Profesional — Grupo TIDSM8-2, Universidad Tecnológica de Hermosillo.
**Profesor:** Iván Rogelio Chenoweth.
**Fecha:** 2026-09-17.

## 0. Método de verificación

Todas las cifras de esta entrega provienen de ejecuciones reales realizadas durante su
elaboración, no de estimaciones. Cada una se puede rastrear a un archivo de
`evidencia/`:

| Dato | Origen |
|---|---|
| Métricas de carga | Corridas de k6 documentadas en el apartado 7, con su salida cruda |
| Métricas de código | Análisis de SonarQube 26.9.0 sobre la rama del PR #95 |
| Defectos reportados | Reproducidos en ejecución antes de documentarlos |
| Versiones de herramientas | Consultadas con el propio binario (`k6 version`, `php -v`, API de SonarQube) |

Donde algo no se midió, se dice que no se midió. El apartado 8.5 explica por qué la
cobertura sale en 0 % en lugar de dejar el número sin contexto.

## 1. Índice de documentos

| # | Documento | Contenido |
|---|---|---|
| 00 | [Índice y mapa de rúbrica](00-indice.md) | Este documento |
| 01 | [Caso de estudio](01-caso-de-estudio.md) | El sistema, el equipo, sus restricciones y el problema que resuelve la unidad |
| 02 | [Entorno de liberación](02-entorno-liberacion.md) | Entorno requerido, adopción de Codespaces y justificación del descarte de IONOS y Railway |
| 03 | [Niveles de servicio](03-niveles-de-servicio.md) | SLI, SLO y SLA acordados, con su ventana de medición |
| 04 | [Métricas de monitoreo](04-metricas-monitoreo.md) | Qué se mide, por qué, con qué umbral, y qué queda pendiente |
| 05 | [Parámetros de configuración](05-parametros-herramientas.md) | Cada parámetro de cada herramienta, su archivo y su razón |
| 06 | [Plan de implementación de k6](06-plan-implementacion-k6.md) | Elección de herramienta, instalación, endpoints y diseño de la prueba |
| 07 | [Resultados de las pruebas de carga](07-resultados-k6.md) | Métricas reales, los tres defectos encontrados y su resolución |
| 08 | [Análisis estático con SonarQube](08-sonarqube.md) | Instalación del stack local, limitaciones encontradas y resultados del PR #95 |
| 09 | [Integración en CI/CD](09-integracion-cicd.md) | Cuándo usar las pruebas, cómo se integran y evidencia con y sin la compuerta |

## 2. Mapa de rúbrica

| Requisito del profesor | Cubierto en | Sección |
|---|---|---|
| Documento a partir de un caso de estudio | [01](01-caso-de-estudio.md) | §1.1 a §1.5 |
| Justificación del flujo de trabajo (pipeline) para la liberación y el despliegue continuo | [01](01-caso-de-estudio.md), [09](09-integracion-cicd.md) | §1.3, §1.4, §9.2 |
| Entorno requerido para la liberación y el despliegue | [02](02-entorno-liberacion.md) | §2.1 a §2.6 |
| Niveles de servicio acordados | [03](03-niveles-de-servicio.md) | §3.2 a §3.5 |
| Métricas para el monitoreo de la aplicación | [04](04-metricas-monitoreo.md) | §4.1 a §4.5 |
| Parámetros de configuración de las herramientas | [05](05-parametros-herramientas.md) | §5.1 a §5.7 |
| Configurar y vincular una herramienta de liberación continua con el entorno de despliegue | [02](02-entorno-liberacion.md), [09](09-integracion-cicd.md) | §2.2, §9.2 |
| Repositorio con los scripts del pipeline | [`scripts/`](../../scripts/) | `verificar-formato.sh`, y los tres siguientes |
| Scripts para generar el entorno de liberación | [`scripts/entorno-liberacion.sh`](../../scripts/entorno-liberacion.sh), [`.devcontainer/`](../../.devcontainer/) | §2.2 |
| Scripts para ejecutar pruebas en ese entorno | [`scripts/pruebas-liberacion.sh`](../../scripts/pruebas-liberacion.sh) | §6.4 |
| Scripts para generar el despliegue | [`scripts/despliegue.sh`](../../scripts/despliegue.sh) | §9.2 |
| **k6 punto 1.** PR de implementación con plan en markdown, comandos de instalación y endpoints | [06](06-plan-implementacion-k6.md) | §6.2, §6.3 |
| **k6 punto 2.** Ejecución con script propio `iniciales-prueba.js`, más de 5 VUs, máximo de métricas | [`tests/carga/jri-prueba.js`](../../tests/carga/jri-prueba.js), [07](07-resultados-k6.md) | §7.1, §7.4 |
| **k6 punto 3.** Resultados commiteados en markdown y PR de ejecución | [07](07-resultados-k6.md) | §7.4, §7.6 |
| **k6 punto 4.** Cuándo usarla y cómo se incluye en el CI/CD, con evidencia con y sin ella | [09](09-integracion-cicd.md) | §9.1, §9.3, §9.4 |
| **SonarQube punto 1.** Instalación en stack local, pasos en markdown, en un PR | [08](08-sonarqube.md) | §8.1 |
| **SonarQube punto 2.** Evidencia de resultados, escaneo del PR de la unidad anterior | [08](08-sonarqube.md) | §8.3 |
| Objetivo de carga: p95 < 5 s | [03](03-niveles-de-servicio.md), [07](07-resultados-k6.md) | §3.2, §7.4 |

## 3. Qué distingue esta entrega

Las pruebas de carga y el análisis estático **encontraron cinco defectos reales** que
la suite funcional existente no detectaba. Uno de ellos, el error 500 de `/about` para
cualquier visitante anónimo, estaba en producción y visible para cualquiera.

| # | Defecto | Dónde se documenta |
|---|---|---|
| 1 | `/about` responde 500 a visitantes no autenticados | §7.3, issue #97 |
| 2 | `POST /login` limitado a 5 peticiones por minuto y por IP | §7.2 |
| 3 | Los archivos con credenciales de IONOS no estaban excluidos en `develop` | §2.3 |
| 4 | ESLint no tiene configuración ni figura en las dependencias | §7.5 |
| 5 | El publicador SCM de SonarQube se cuelga en este repositorio | §8.2 |

Ninguno se buscó a propósito. Aparecieron al intentar medir, que es exactamente el
argumento a favor de medir.
