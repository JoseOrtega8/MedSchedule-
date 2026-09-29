# MedSchedule — Unidad 3: liberación continua, observabilidad y auditoría

**Proyecto:** MedSchedule, sistema web de gestión de citas médicas (Laravel 12.53.0).
**Autor de esta entrega:** [`ramonibr`](https://github.com/ramonibr) (Ibarra Fontes José Ramón). **Entrega individual.**
**Repositorio:** [`github.com/JoseOrtega8/MedSchedule-`](https://github.com/JoseOrtega8/MedSchedule-) (público).
**Materia:** Desarrollo Web Profesional — Grupo IDGS 8-2, Universidad Tecnológica de Hermosillo.
**Profesor:** Iván Rogelio Chenoweth.
**Fecha:** 2026-09.

## Observación de la Unidad 2: dashboards de SonarQube

La revisión de la Unidad 2 señaló que los dashboards de SonarQube no se localizaban en la
entrega: aparecían como rutas de archivo al final del documento. En esta unidad se muestran
aquí, al inicio y como imágenes, tomadas sobre el análisis de la rama de la Unidad 3.

![Panel general del proyecto en SonarQube para la rama de la Unidad 3](evidencia/sonar-01-panel-general.png)

**Panel general.** Resumen del último análisis: puerta de calidad, incidencias, cobertura y duplicación.

![Puerta de calidad de SonarQube en estado fallido con la condición de cobertura incumplida](evidencia/sonar-02-puerta-calidad.png)

**Puerta de calidad.** Veredicto de la puerta con la condición que la hizo fallar en la corrida de prueba descrita abajo.

![Vista de Security Hotspots de SonarQube](evidencia/sonar-03-hotspots-seguridad.png)

**Puntos sensibles de seguridad.** Fragmentos de código que requieren revisión manual desde el punto de vista de seguridad.

![Historial de análisis del proyecto en la vista Activity de SonarQube](evidencia/sonar-04-actividad.png)

**Actividad.** Historial de análisis del proyecto y evolución de sus métricas.

![Métrica de duplicación de código en la vista Measures de SonarQube](evidencia/sonar-05-duplicacion.png)

**Duplicación.** Porcentaje y bloques de código duplicado por archivo.

![Resumen completo de medidas del proyecto en SonarQube](evidencia/sonar-06-medidas.png)

**Medidas.** Resumen de todas las métricas del proyecto: tamaño, complejidad, fiabilidad, seguridad y mantenibilidad.

### La puerta de calidad ahora detiene la liberación

En la Unidad 2 el análisis estático era un reporte: el escáner terminaba en éxito sin importar
el veredicto. En esta unidad `scripts/sonarqube-escanear.sh` ejecuta el escáner con
`-Dsonar.qualitygate.wait=true` y `-Dsonar.qualitygate.timeout=300`, de modo que espera el
veredicto de la puerta y termina con código 1 si no se supera. El mismo parámetro se agregó al
job `analisis-estatico` de `ci.yml` y al job nuevo `calidad` de `release.yml` (apartado 11).

Para demostrarlo se hicieron dos corridas del mismo script sobre el mismo código:

| Corrida | Puerta de calidad | Resultado | Evidencia |
|---|---|---|---|
| Puerta por defecto de SonarQube | [[PENDIENTE: estado de la puerta, de evidencia/sonar-puerta-pasa.txt]] | [[PENDIENTE: código de salida, de evidencia/sonar-puerta-pasa.txt]] | `evidencia/sonar-puerta-pasa.txt` |
| Puerta estricta "MedSchedule estricta" (cobertura mínima de 80 %) | [[PENDIENTE: estado de la puerta, de evidencia/sonar-puerta-falla.txt]] | [[PENDIENTE: código de salida, de evidencia/sonar-puerta-falla.txt]] | `evidencia/sonar-puerta-falla.txt` |

La puerta estricta falla de forma honesta: la cobertura medida de la suite es 0 %, por la
razón explicada en el apartado 8.5 de la Unidad 2 (el informe de cobertura no se genera en el
análisis). [[PENDIENTE: métrica usada en la condición de la puerta estricta (new_coverage o coverage), de evidencia/sonar-puerta-falla.txt]]. Después de la corrida que falla, el proyecto se
regresa a la puerta por defecto.

## Método de verificación

Las cifras de este documento provienen de ejecuciones reales o de los archivos del
repositorio, no de estimaciones. Cada una se puede rastrear a su origen:

| Dato | Origen |
|---|---|
| Dashboards y puerta de calidad de SonarQube | `evidencia/sonar-*.png`, `evidencia/sonar-puerta-*.txt` |
| Umbrales de alerta | `infra/monitoreo/prometheus/alertas.yml`, derivados de `docs/entrega-u2/03-niveles-de-servicio.md` |
| Comportamiento de las alertas | `infra/monitoreo/prometheus/alertas.test.yml`, ejecutado con `promtool test rules` |
| Tiempo de notificación de una caída | `evidencia/monitoreo-alerta-tiempos.txt` |
| Tableros, trazas y visor de auditoría | Capturas `evidencia/monitoreo-*.png`, `evidencia/trazas-*.png`, `evidencia/auditoria-*.png` |
| Costo de la instrumentación | `evidencia/k6-sin-instrumentacion.txt` y `evidencia/k6-con-instrumentacion.txt` |
| Integridad de la auditoría | `evidencia/auditoria-verificar-integra.txt` y `evidencia/auditoria-verificar-rota.txt` |
| Análisis de dependencias | `evidencia/snyk-*.png` y `evidencia/snyk-puerta-*.txt` |
| Versiones de herramientas | Imagen fijada en `infra/monitoreo/docker-compose.yml`, `composer.lock` y scripts |
| Diseño y cambios de diseño | `specs/003-observabilidad-auditoria/` (`spec.md`, `plan.md`, `tasks.md`) |

Donde un dato no se midió, el documento lo dice. Los valores que dependen de una corrida se
señalan con su archivo de evidencia.

## Contenido

| # | Documento | Contenido |
|---|---|---|
| 00 | Introducción | Datos de la entrega, dashboards de SonarQube y método de verificación |
| 01 | Caso de estudio | El sistema, el equipo y el problema que resuelve esta unidad |
| 02 | Pipeline | Flujo de liberación y despliegue continuo, etapa por etapa |
| 03 | Entorno | Entorno requerido, stacks en contenedores, puertos y variables |
| 04 | Niveles de servicio | Acuerdos de la Unidad 2 ligados a alertas reales |
| 05 | Justificación de SDD | Por qué y cómo se especificó antes de codificar |
| 06 | Monitoreo | Módulo a: métricas, tableros y alertas |
| 07 | Trazabilidad | Módulo b: logs estructurados y trazas enlazados |
| 08 | Auditoría | Módulo c: visor de auditoría con detección de manipulación |
| 09 | Módulo adicional: Snyk | Compuerta de vulnerabilidades en dependencias |
| 10 | Parámetros de las herramientas | Cada parámetro, su valor, su archivo y su razón |
| 11 | Integración en CI/CD | Qué compuerta detiene qué y dónde corre |

## Pull requests de esta entrega

| Pull request | Contenido | Base |
|---|---|---|
| [[PR-105]] | Especificación, plan, tareas, puerta de calidad de SonarQube y este documento | `feat/100-sonarqube` (Unidad 2) |
| [[PR-106]] | Módulo a: monitoreo con métricas y alertas | `feat/105-u3-sdd` |
| [[PR-107]] | Módulo b: visor de trazabilidad | `feat/106-monitoreo` |
| [[PR-108]] | Módulo c: visor de auditoría | `feat/105-u3-sdd` |
| [[PR-109]] | Módulo adicional: análisis de dependencias con Snyk | `feat/105-u3-sdd` |
