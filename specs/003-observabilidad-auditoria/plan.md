# Implementation Plan: Observabilidad y auditoría de MedSchedule

**Branch**: `003-observabilidad-auditoria` | **Date**: 2026-09-23 | **Spec**: `specs/003-observabilidad-auditoria/spec.md`

**Input**: Feature specification from `/specs/003-observabilidad-auditoria/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command; its definition describes the execution workflow.

## Summary

Atender la observación de la unidad 2 sobre SonarQube (dashboards visibles y puerta de calidad en
el pipeline) y agregar tres capacidades de observabilidad: monitoreo con Prometheus + Grafana y
alertas vía Alertmanager (punto 1); visor de trazabilidad con logs en Loki y trazas OpenTelemetry
en Tempo, enlazados dentro del mismo Grafana (punto 2); y visor de auditoría propio sobre la tabla
`activity_logs` existente, con captura automática por trait y listeners y cadena HMAC para
detectar manipulación (punto 3). Los stacks corren en contenedores (`infra/monitoreo/`) junto al
de SonarQube (`infra/sonarqube/`) de la unidad 2.

## Technical Context

**Language/Version**: PHP 8.2 / Laravel 12.53.0 para la aplicación; YAML y JSON para la
configuración de los stacks; Bash para los scripts del pipeline.

**Primary Dependencies**:
- Aplicación: `promphp/prometheus_client_php` (métricas, adaptador Redis),
  `open-telemetry/sdk` y `open-telemetry/exporter-otlp` (trazas, sin extensión C), Monolog
  (incluido en Laravel), `spatie/laravel-permission` (ya instalado; se activan sus eventos).
- Stack: Prometheus, Alertmanager, Grafana, Loki, Tempo, Grafana Alloy, blackbox-exporter,
  mysqld-exporter, Redis y Mailpit, todos con versión de imagen fijada en el compose. Las
  versiones exactas se consultan al implementar y se registran en `05-parametros-herramientas.md`.
- Análisis estático: SonarQube de la unidad 2 (`infra/sonarqube/`, `sonar-project.properties`).

**Storage**: MySQL 8.0 (aplicación y `activity_logs` ampliada); Redis (almacén de métricas);
almacenamiento local de Prometheus, Loki y Tempo en volúmenes Docker.

**Testing**: PHPUnit (feature tests) contra MySQL `medschedule_test`; almacenamiento de métricas
en memoria y `InMemoryExporter` de OpenTelemetry en pruebas; `promtool check rules/config` y
`docker compose config` para la configuración de los stacks.

**Target Platform**: GitHub Codespaces (devcontainer de la unidad 2) y Docker local en macOS;
pipeline en GitHub Actions (`ci.yml`, `release.yml`).

**Project Type**: Aplicación web monolítica Laravel más infraestructura declarativa en `infra/`.

**Performance Goals**: La instrumentación no debe mover el p95 medido por k6 en la unidad 2
(75.92 ms) más de un 10 %; se vuelve a correr k6 con la instrumentación activa y se reporta.

**Constraints**:
- Si Redis, Tempo o Loki no están disponibles, la aplicación responde con normalidad.
- Ningún secreto en el repositorio (repo público); PII médica nunca en claro en logs, trazas ni
  auditoría.
- La suite de pruebas y el CI corren con `OTEL_ENABLED=false` y sin el stack de monitoreo.
- No se modifica la vista `/admin/logs` ni migraciones existentes.

**Scale/Scope**: 3 puntos del enunciado + corrección de SonarQube; 6 modelos auditados; 6 reglas
de alerta; 4 dashboards de Grafana; 4 PRs (general + uno por punto).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **Principio I (Estilo de código)**: PASA con condición: comentarios en español y `snake_case`
  en variables y funciones propias; las clases siguen PascalCase de Laravel.
- **Principio II (Manejo de errores)**: PASA. Los fallos de Redis, Tempo o Loki se capturan con
  `try/catch` explícito y se registran como advertencia; no se silencian. La escritura de
  auditoría no captura excepciones: si falla, falla la operación auditada.
- **Principio III (Gestión de secretos)**: PASA. `METRICS_TOKEN`, `AUDIT_HMAC_KEY`, credenciales
  de Grafana y del exporter de MySQL viven en `.env` (gitignored); `.env.example` con valores
  vacíos.
- **Principio IV (Validación de entrada)**: PASA. Filtros del visor de auditoría validados con
  FormRequest; token de métricas comparado con `hash_equals`.
- **Principio V (Exposición de errores al cliente)**: PASA. La página de error 500 muestra solo
  un folio (`trace_id`).
- **Principio VI (Spec-Driven Development)**: PASA. `spec.md` y este plan preceden al código;
  `tasks.md` se genera antes de implementar.
- **Principio VII (Control de versiones)**: PASA. Una rama por issue (R17–R20), commits
  convencionales sin atribución.

No se identificaron violaciones que requieran justificación en Complexity Tracking.

## Project Structure

### Documentation (this feature)

```text
specs/003-observabilidad-auditoria/
├── spec.md              # Qué y por qué
├── plan.md              # Este archivo
└── tasks.md             # Lista de tareas (siguiente paso)

docs/entrega-u3/         # Documento de entrega, evidencia/ y DOCX (gitignored)
```

### Source Code (repository root)

```text
app/
├── Console/Commands/
│   ├── VerificarAuditoria.php          # auditoria:verificar
│   └── SellarHistoricoAuditoria.php    # auditoria:sellar-historico
├── Http/
│   ├── Controllers/
│   │   ├── MetricasController.php      # GET /metrics
│   │   └── AuditoriaController.php     # /admin/auditoria
│   ├── Middleware/
│   │   ├── RegistrarMetricasHttp.php
│   │   └── IniciarTraza.php
│   └── Requests/FiltrarAuditoriaRequest.php
├── Listeners/Auditoria/                # sesión, roles, accesos denegados
├── Logging/
│   ├── AgregarContextoTraza.php
│   └── RedactarDatosSensibles.php
├── Models/Concerns/Auditable.php
├── Observability/                      # proveedor de métricas y de trazas
└── Services/SelladorAuditoria.php      # cadena HMAC
database/migrations/xxxx_add_integridad_to_activity_logs_table.php
resources/views/admin/auditoria/        # index, show, timeline
infra/monitoreo/
├── docker-compose.yml
├── prometheus/{prometheus.yml, alertas.yml}
├── alertmanager/alertmanager.yml
├── grafana/provisioning/{datasources,dashboards}/
├── grafana/dashboards/*.json
├── loki/, tempo/, alloy/
scripts/monitoreo-local.sh
tests/Feature/{Metricas,Trazas,Auditoria}/
```

## Diseño por componente

### 0. Estructura de ramas y PRs

```
feat/100-sonarqube (#104, U2)
 └─ feat/<R17>-u3-sdd               PR general: spec, plan, tasks, docs/entrega-u3, fix Sonar
     ├─ feat/<R18>-monitoreo         PR punto 1
     │   └─ feat/<R19>-trazabilidad  PR punto 2 (usa el Grafana del punto 1)
     └─ feat/<R20>-auditoria         PR punto 3 (independiente del monitoreo)
```

Los números R17–R20 se conocen al crear los issues. Cada PR lleva antes/después **de
comportamiento** e imágenes renderizadas (`raw.githubusercontent.com`), nunca solo rutas.

**Aislamiento entre PRs:** cada punto tiene su propio archivo de configuración
(`config/metricas.php`, `config/trazas.php`, `config/auditoria.php`) y su propio service
provider (`MetricasServiceProvider`, `TrazasServiceProvider`, `AuditoriaServiceProvider`), para
que las ramas paralelas solo coincidan en líneas sueltas de `bootstrap/providers.php` y
`phpunit.xml`. `/metrics` vive en `routes/observabilidad.php`, registrado con `then:` en
`bootstrap/app.php` fuera del grupo `web` (Prometheus no necesita sesión ni cookies).

**Versiones fijadas:** Prometheus v3.14.0, Alertmanager v0.34.1, Grafana 13.2.2, Loki 3.7.8,
Tempo 2.9.5 (la rama 3.x cambió el formato de configuración; se fija la 2.9 por estabilidad),
Alloy v1.19.2, blackbox-exporter v0.28.0, mysqld-exporter v0.20.0, Redis 8.8.3-alpine (puerto
6380 en el host), Mailpit v1.31.2; `promphp/prometheus_client_php` 2.15.1 con adaptador `Predis`
(no requiere la extensión phpredis), `open-telemetry/sdk` 1.15.0, `open-telemetry/exporter-otlp`
1.4.0. Todos los puertos del stack se publican solo en `127.0.0.1`; Grafana permite lectura
anónima en local para tomar capturas sin exponer credenciales.

### 1. Corrección de SonarQube

- Apartado 00 del documento: "Dashboards de SonarQube (observación U2)" con capturas al inicio.
- Capturas nuevas sobre la rama U3: panel general, Quality Gate, hotspots de seguridad,
  actividad, duplicación y medidas (`docs/entrega-u3/evidencia/sonar-*.png`).
- Quality Gate en `release.yml` con `sonar.qualitygate.wait=true`; evidencia de corrida que pasa y
  corrida que falla. Si el runner de Actions no alcanza el SonarQube local, el paso corre en el
  Codespace con `scripts/sonarqube-escanear.sh` y el documento lo explica.

### 2. Punto 1 — Monitoreo

**Stack** (`infra/monitoreo/docker-compose.yml`, arranque con `scripts/monitoreo-local.sh`):
`prometheus` (scrape 15 s, reglas), `alertmanager` (a Mailpit), `mailpit`, `grafana`
(provisioning como código), `blackbox-exporter` (sonda a `/up`), `mysqld-exporter`, `redis`.

**Instrumentación:**
- Middleware `RegistrarMetricasHttp`:
  `medschedule_http_requests_total{method,route,status}` y
  `medschedule_http_request_duration_seconds{method,route}` (buckets 0.05, 0.1, 0.25, 0.5, 1,
  2.5, 5, 10). `route` = nombre de ruta, nunca URL.
- Negocio: `medschedule_citas_agendadas_total`, `medschedule_citas_canceladas_total` (observer de
  `Appointment`); `medschedule_citas_hoy`, `medschedule_jobs_fallidos` (gauges al scrape).
- `GET /metrics` con `Authorization: Bearer <METRICS_TOKEN>`, `hash_equals`; si no, 404.
- Fallo de Redis: warning en log y la petición continúa.

**Alertas** (`infra/monitoreo/prometheus/alertas.yml`):

| Alerta | Condición | Severidad | Origen |
|---|---|---|---|
| `AplicacionCaida` | `probe_success == 0` por 1 min | crítica | Disponibilidad |
| `LatenciaP95FueraDeAcuerdo` | p95 > 5 s por 5 min | crítica | SLO p95 < 5000 ms |
| `LatenciaP95Degradada` | p95 > 500 ms por 10 min | advertencia | Riesgo del umbral holgado señalado en la U2 (p95 real 76 ms) |
| `TasaErrores5xxAlta` | 5xx > 1 % en 5 min | crítica | SLO `http_req_failed` < 1 % |
| `BaseDeDatosCaida` | `mysql_up == 0` por 1 min | crítica | Disponibilidad |
| `JobsFallidos` | `medschedule_jobs_fallidos > 0` por 10 min | advertencia | Operación de colas |

**Dashboards:** Servicio (RED + SLO + disponibilidad + alertas), Negocio (citas, jobs), Base de
datos.

**Pruebas:** contador e histograma registrados; `/metrics` 200 con token y 404 sin él; petición
normal con Redis caído; `promtool check`; `docker compose config`.

**Evidencia:** antes, app caída o lenta sin aviso; después, alerta roja en Grafana y correo en
Mailpit.

### 3. Punto 2 — Trazabilidad

**Stack** (mismo compose): `loki` (7 días), `tempo` (OTLP/HTTP 4318, 48 h), `alloy` (lee
`storage/logs/medschedule.json` y envía a Loki).

**Logs:** canal `json` (Monolog `JsonFormatter`) agregado al `stack`, conservando `laravel.log`.
Processors `AgregarContextoTraza` (`trace_id`, `span_id`, `request_id`, `user_id`, `route`,
`method`) y `RedactarDatosSensibles` (`password`, `password_confirmation`, `token`,
`authorization`, `cookie`, `curp`, `email`, campos clínicos; a cualquier profundidad).

**Trazas:** middleware `IniciarTraza` (span raíz `SERVER`, respeta `traceparent`, cabecera
`X-Trace-Id`, estado de error en 5xx). Spans hijos: SQL vía `DB::listen` (sentencia sin
bindings, inicio calculado por duración), jobs (`JobProcessing`/`JobProcessed`), HTTP saliente a
Google Calendar. `BatchSpanProcessor` con flush en `terminate()`. `.env`: `OTEL_ENABLED`
(default `false`), `OTEL_EXPORTER_OTLP_ENDPOINT`, `OTEL_TRACES_SAMPLER_ARG` (default `1.0`),
`OTEL_SERVICE_NAME=medschedule`. Fallo del exportador: warning, sin afectar la petición. Vista 500
con "Folio: `<trace_id>`".

**Correlación:** derived field de Loki (`trace_id` → Tempo) y `tracesToLogs` de Tempo. Dashboard
Trazabilidad: logs filtrables, trazas más lentas, errores recientes con enlace.

**Pruebas:** con `InMemoryExporter`, span raíz + spans de queries, `X-Trace-Id` presente, sin
bindings; `trace_id` en cada línea de log; redacción anidada; `OTEL_ENABLED=false` no exporta.

**Evidencia:** antes, endpoint más lento del k6 de la U2 investigado solo con `laravel.log`;
después, cascada de la traza con la query o N+1 culpable, salto log ↔ traza, folio de un 500
localizado.

### 4. Punto 3 — Auditoría

**Datos:** migración nueva sobre `activity_logs`: `trace_id` (string 32, nullable),
`hash_anterior` (char 64, nullable), `hash` (char 64, nullable solo para filas históricas);
índice `trace_id`. El índice `(model_type, model_id)` ya existe (`idx_model`, migración
`2026_08_15_043158`). El `trace_id` se lee del atributo de la petición que deja `IniciarTraza`,
así la auditoría no depende del código de trazas y su rama sale directo del PR general.

**Captura:** trait `Auditable` en `Appointment`, `User`, `Specialty`, `Schedule`,
`DoctorProfile`, `PatientProfile` (`creado`, `actualizado`, `eliminado`; solo campos cambiados;
excluye `remember_token` y marcas de tiempo; `password` y campos clínicos como `[protegido]`).
Listeners de `Logout`, `Failed` (correo enmascarado), `PasswordReset`; eventos de Spatie
(`events_enabled => true`); middleware `auditar.denegado` antes de `role:admin` para los 403.
El inicio de sesión ya lo registra `AuthenticatedSessionController` (`login`), así que no se
agrega listener de `Login` para no duplicarlo; ese `ActivityLog::create` sigue funcionando porque
el sello se calcula en el hook `creating`.

**Hallazgo al planear:** los dos `ActivityLog::create` de `PatientProfileController` guardan
alergias, padecimientos, tipo de sangre y CURP **en claro** en `old_values`/`new_values`, lo que
viola FR-016. Se eliminan: el trait registra esos mismos cambios con los valores protegidos.
`FullDataSeeder` inserta auditoría con `DB::table()` (sin sello); pasa a usar el modelo.

**Integridad:** `ActivityLog` lanza excepción en `update`/`delete`.
`hash = HMAC-SHA256(hash_anterior || json_canonico(fila), AUDIT_HMAC_KEY)`; `json_canonico` con
claves ordenadas, sin espacios y fechas ISO 8601 UTC; cálculo en transacción con
`lockForUpdate` sobre la última fila. Sin `AUDIT_HMAC_KEY` la app no arranca en producción.
Comandos `auditoria:verificar` (por lotes, código de salida ≠ 0 si hay ruptura, programado
diario) y `auditoria:sellar-historico` (una sola vez para filas previas).

**Visor `/admin/auditoria`:** `auth` + `role:admin` + `throttle`; filtros usuario, acción,
entidad, fechas, IP (FormRequest); paginación en servidor; diff lado a lado; línea de tiempo por
entidad; enlace a traza; indicador de integridad; exportación CSV en streaming con escape de
`= + - @`, tabulador y retorno de carro, exportación auditada.

**Pruebas:** diff correcto por modelo; sin `password` y clínicos `[protegido]`; excepción en
`update`/`delete`; alteración por SQL detectada en el id exacto; 403 a no admin; validación de
filtros; CSV escapado y exportación auditada.

**Evidencia:** antes, cambio de cita sin rastro y alteración de `activity_logs` inadvertida;
después, diff con quién/qué/cuándo/IP e indicador rojo señalando el registro alterado.

### 5. Documento de entrega (`docs/entrega-u3/`)

| # | Documento |
|---|---|
| 00 | Índice, método de verificación y **dashboards de SonarQube (observación U2)** |
| 01 | Caso de estudio y justificación del pipeline (actualiza la U2) |
| 02 | Entorno requerido |
| 03 | Niveles de servicio ligados a alertas reales |
| 04 | Métricas de monitoreo (punto 1) |
| 05 | Parámetros de configuración de cada herramienta |
| 06 | Visor de trazabilidad (punto 2) |
| 07 | Visor de auditoría (punto 3) |
| 08 | Integración en CI/CD |

Liga al PR en cada punto; cada cifra rastreable a `evidencia/`; DOCX con pandoc, reinclusión de
`/docs/entrega-u3/` y exclusión de `/docs/entrega-u3/*.docx` en `.gitignore`.

### 6. Fuera de alcance

Despliegue productivo del stack de monitoreo; node-exporter y cAdvisor; notificaciones reales
(Slack, Telegram, correo); reentrega de la U2; issues de otros integrantes (#97, EQ2 #65).

## Complexity Tracking

Sin violaciones de la constitución que justificar.
