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
- Análisis de dependencias: CLI `snyk@1.1307.4`, fijado por versión y ejecutado vía `npx --yes`
  (sin instalación global ni en `package.json`).

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
  un folio (`trace_id`, o `request_id` si las trazas están apagadas).
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
│   └── VerificarAuditoria.php          # auditoria:verificar (diario)
├── Exceptions/RegistroAuditoriaInmutable.php
├── Http/
│   ├── Controllers/
│   │   ├── MetricasController.php      # GET /metrics
│   │   └── AuditoriaController.php     # /admin/auditoria
│   ├── Middleware/
│   │   ├── RegistrarMetricasHttp.php
│   │   ├── IniciarTraza.php
│   │   └── AuditarAccesoDenegado.php   # alias auditar.denegado
│   └── Requests/FiltrarAuditoriaRequest.php
├── Listeners/Auditoria/                # sesión, roles y permisos
├── Logging/
│   ├── AgregarContextoTraza.php
│   └── RedactarDatosSensibles.php
├── Models/Concerns/Auditable.php
├── Observability/                      # proveedor de métricas y de trazas
├── Observers/ContadorCitasObserver.php # contadores de citas agendadas/canceladas
├── Services/Auditoria/
│   ├── SelladorAuditoria.php           # cadena HMAC
│   └── RegistradorAuditoria.php        # punto único de escritura de auditoría
└── Support/
    ├── CsvSeguro.php                   # escape de fórmulas en la exportación
    └── Enmascarar.php                  # correo enmascarado en login fallido
database/migrations/
├── 2026_09_23_000000_add_integridad_to_activity_logs_table.php
└── 2026_09_23_000001_drop_user_fk_from_activity_logs_table.php
resources/views/admin/auditoria/        # index, show, timeline
infra/monitoreo/
├── docker-compose.yml
├── prometheus/{prometheus.yml, alertas.yml}
├── alertmanager/alertmanager.yml
├── grafana/provisioning/{datasources,dashboards}/
├── grafana/dashboards/*.json
├── loki/, tempo/, alloy/
scripts/monitoreo-local.sh
scripts/snyk-escanear.sh                  # modulo adicional: compuerta de Snyk
.snyk                                     # politica de excepciones (raiz del repo)
tests/Feature/{Metricas,Trazas,Auditoria}/
tests/scripts/snyk-escanear.test.sh
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
que las ramas paralelas coincidan en pocas líneas. En la práctica hubo tres conflictos reales,
todos de líneas sueltas: `.env.example`, `bootstrap/providers.php` y `phpunit.xml`. `/metrics` vive en `routes/observabilidad.php`, registrado con `then:` en
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

`alertas.test.yml` prueba cada regla en positivo y también casos negativos (bajo el umbral o por
menos tiempo del `for`, la alerta no dispara).

**Dashboards:** Servicio (RED + SLO + disponibilidad + alertas), Negocio (citas, jobs), Base de
datos.

**Pruebas:** contador e histograma registrados; `/metrics` 200 con token y 404 sin él; petición
normal con Redis caído; `promtool check`; `docker compose config`.

**Evidencia:** antes, app caída o lenta sin aviso; después, alerta roja en Grafana y correo en
Mailpit.

### 3. Punto 2 — Trazabilidad

**Stack** (mismo compose): `loki` (7 días), `tempo` (OTLP/HTTP 4318, 48 h), `alloy` (lee
`storage/logs/medschedule.json` y envía a Loki).

**Logs:** canal `json` (Monolog `JsonFormatter`) agregado al `stack` (`LOG_STACK=single,json`),
conservando `laravel.log`. Driver `monolog` con `StreamHandler` (`handler_with.stream`): el driver
`single` ignora `processors` en Laravel 12.53. Processors `AgregarContextoTraza` (`trace_id`,
`span_id`, `request_id`, `user_id`, `route`, `method`) y `RedactarDatosSensibles`, que enmascara a
cualquier profundidad: claves por subcadena sin distinguir mayúsculas (`token`, `password`,
`secret`, `authorization`, `cookie`, `curp`, `email`, campos clínicos, `emergency_contact`);
patrones dentro del mensaje y de valores de texto (correo, `Bearer`/`Basic`, CURP); y excepciones
del contexto, reducidas a clase, mensaje redactado (de `QueryException`, la sentencia sin
bindings), archivo y línea, sin trace. Límite documentado: una contraseña escrita en texto libre
sin clave propia no se detecta; la regla es pasar secretos en `$context` con su clave.

**Trazas:** middleware `IniciarTraza` (span raíz `SERVER`, respeta `traceparent`, cabecera
`X-Trace-Id`, estado de error en 5xx). El span guarda `url.template` (plantilla de la ruta, p. ej.
`reset-password/{token}`), nunca la ruta real, que puede llevar tokens. `/metrics` no se traza (el
scrape de Prometheus cada 15 s). El `traceparent` entrante se acepta tal cual (un cliente puede
fijar el `trace_id` o marcarlo no muestreado): aceptable en local; en producción debe validarse en
el borde. Spans hijos: SQL vía `DB::listen` (sentencia sin bindings, inicio calculado por
duración), jobs (abre en `JobProcessing`; cierra en `JobProcessed`, `JobFailed` o
`JobExceptionOccurred`, este último cuando al job le quedan reintentos), HTTP saliente a Google
Calendar. `BatchSpanProcessor` con flush en `terminate()`; transporte OTLP con timeout de 1 s y
sin reintentos (con los valores por defecto un Tempo inalcanzable bloqueaba ~40 s). `.env`:
`OTEL_ENABLED` (default `false`), `OTEL_EXPORTER_OTLP_ENDPOINT`, `OTEL_TRACES_SAMPLER_ARG`
(default `1.0`), `OTEL_SERVICE_NAME=medschedule`. Fallo del exportador: warning, sin afectar la
petición. Vista 500 con "Folio: `<trace_id>`"; con las trazas apagadas, el folio de respaldo es
el `request_id`.

**Correlación:** derived field de Loki (`trace_id` → Tempo) y `tracesToLogs` de Tempo. Dashboard
Trazabilidad: logs filtrables, trazas más lentas, errores recientes con enlace.

**Pruebas:** con `InMemoryExporter`, span raíz + spans de queries, `X-Trace-Id` presente, sin
bindings; `trace_id` en cada línea de log; redacción anidada; `OTEL_ENABLED=false` no exporta.

**Evidencia:** antes, endpoint más lento del k6 de la U2 investigado solo con `laravel.log`;
después, cascada de la traza con la query o N+1 culpable, salto log ↔ traza, folio de un 500
localizado.

### 4. Punto 3 — Auditoría

**Datos:** migración nueva sobre `activity_logs`: `trace_id` (string 32, nullable),
`hash_anterior` (char 64, nullable), `hash` (char 64, nullable solo para poder agregar la columna:
la misma migración sella todas las filas existentes en orden de id, así que después de migrar
toda fila con `hash` NULL cuenta como rota); índice `trace_id`. Segunda migración
(`2026_09_23_000001`): se elimina la FK de `activity_logs.user_id` (su `ON DELETE SET NULL`
alteraba filas selladas al borrar un usuario) y se conserva su índice; restaurarla en el
rollback falla si ya hay `user_id` huérfanos. El índice `(model_type, model_id)` ya existe (`idx_model`, migración
`2026_08_15_043158`). El `trace_id` se lee del atributo de la petición que deja `IniciarTraza`,
así la auditoría no depende del código de trazas y su rama sale directo del PR general.

**Captura:** trait `Auditable` en `Appointment`, `User`, `Specialty`, `Schedule`,
`DoctorProfile`, `PatientProfile` (`creado`, `actualizado`, `eliminado`; solo campos cambiados;
excluye `remember_token` y marcas de tiempo; `password` y campos clínicos como `[protegido]`,
incluidos `reason` y `observaciones` de `Appointment`, texto clínico libre).
Listeners de `Logout`, `Failed` (correo enmascarado), `PasswordReset`; eventos de Spatie
(`events_enabled => true`); middleware `auditar.denegado` antes de `role:admin` para los 403.
El `logout` lo registra solo el listener: se quitó el `ActivityLog::create` de
`AuthenticatedSessionController@destroy`, que duplicaba la fila. El inicio de sesión ya lo
registra `AuthenticatedSessionController` (`login`), así que no se agrega listener de `Login` para
no duplicarlo; ese `ActivityLog::create` sigue funcionando porque el sello se calcula en el hook
`creating`.

**Hallazgo al planear:** los dos `ActivityLog::create` de `PatientProfileController` guardan
alergias, padecimientos, tipo de sangre y CURP **en claro** en `old_values`/`new_values`, lo que
viola FR-016. Se eliminan: el trait registra esos mismos cambios con los valores protegidos.
`FullDataSeeder` inserta auditoría con `DB::table()` (sin sello); pasa a usar el modelo.

**Integridad:** `ActivityLog` lanza excepción en `update`/`delete`.
`hash = HMAC-SHA256(hash_anterior || json_canonico(fila), AUDIT_HMAC_KEY)`; `json_canonico` con
claves ordenadas (`ksort(..., SORT_STRING)`), sin espacios y fechas ISO 8601 UTC; cálculo en
`DB::transaction(..., 3)` (reintenta ante deadlock) con `lockForUpdate` sobre la última fila. Sin
`AUDIT_HMAC_KEY` la app no arranca en producción. Comando `auditoria:verificar` (por lotes, código
de salida ≠ 0 si hay ruptura, programado diario; requiere el cron `schedule:run`). No hay comando
de sellado histórico: lo hace la migración.

Límites no detectables (documentados en `SelladorAuditoria` y en el documento 07): borrar las
últimas filas de la tabla (borrado de cola); quien posee `AUDIT_HMAC_KEY` puede recalcular toda
la cadena; `id` y `updated_at` no forman parte del contenido canónico; rotar la llave obliga a
resellar el histórico; la migración que sella debe correr en modo mantenimiento para que nadie
inserte auditoría a mitad del sellado.

**Visor `/admin/auditoria`:** middleware del grupo admin en este orden: `auth`,
`throttle:60,1`, `auditar.denegado`, `role:admin` (el límite de tasa corta antes de escribir
auditoría de accesos denegados); filtros usuario, acción, entidad, fechas, IP (FormRequest);
paginación en servidor; diff lado a lado; línea de tiempo por entidad (`id` restringido a
`[0-9]{1,18}` para evitar el desbordamiento a 500); enlace a traza; indicador de integridad;
exportación CSV en streaming con escape de `= + - @`, tabulador y retorno de carro, `fputcsv` con
escape `''` (sin barra invertida), `try/catch` dentro del stream y exportación auditada.

**Pruebas:** diff correcto por modelo; sin `password` y clínicos `[protegido]` (incluido el motivo
de la cita); excepción en `update`/`delete`; alteración por SQL detectada en el id exacto; fila sin
sello y anulado masivo de hashes detectados como rotos; 403 a no admin y 404 para ids inválidos;
validación de filtros; CSV escapado y exportación auditada.

**Evidencia:** antes, cambio de cita sin rastro y alteración de `activity_logs` inadvertida;
después, diff con quién/qué/cuándo/IP e indicador rojo señalando el registro alterado.

### 5. Módulo adicional — Snyk

**Piezas:**
- `scripts/snyk-escanear.sh`: corre `npx --yes snyk@1.1307.4 test --all-projects --severity-threshold=high --json-file-output=<salida>` (CLI fijado por versión, sin instalación global). `<salida>` es
  `${SNYK_SALIDA:-docs/entrega-u3/evidencia/snyk-resultado.json}`. Con `--monitor` corre además
  `npx --yes snyk@1.1307.4 monitor --all-projects` (sube una instantánea al dashboard de Snyk), solo
  si el `test` terminó en 0 o 1; su fallo no cambia el código de salida del análisis.
- `.snyk` (raíz del repo): política de excepciones, versión `v1.25.0`, `ignore: {}` por defecto;
  cada excepción que se agregue lleva `reason` y `expires` obligatorios (FR-025).
- Job `seguridad` en `.github/workflows/release.yml` (T025).

**Códigos de salida del script** (traducidos desde el CLI de Snyk):

| Código de Snyk | Significado | Traducción del script |
|---|---|---|
| 0 | Sin vulnerabilidades que superen el umbral | exit 0 |
| 1 | Vulnerabilidades de severidad alta o crítica encontradas | exit 1 |
| 2 | Error de ejecución o autenticación | exit 2 |
| 3 | No se encontraron proyectos soportados | exit 2 |
| otro | No documentado por Snyk | exit 2 |
| sin `SNYK_TOKEN` | No se intenta ejecutar el CLI | exit 1 |

**Integración en `release.yml`:** job `seguridad` (`needs: integracion`) corre siempre; un paso
previo detecta si `secrets.SNYK_TOKEN` existe y expone `hay=true|false`; el análisis solo se ejecuta
si `hay == 'true'`, y sin token el job termina en éxito con un `::notice::` visible en la corrida
(FR-024, FR-026). El job de pruebas agrega `seguridad` a su `needs` y a su condición: exige que
`seguridad` termine en éxito (que incluye el caso "omitido con aviso"), igual que ya hace con
`calidad`.

**Límites:** sin `SNYK_TOKEN` el análisis no corre (queda documentado como advertencia, no como
compuerta fallida); la cuenta gratuita de Snyk del autor es la que autentica el análisis (no hay
cuenta de equipo); `--monitor` es opcional y depende del dashboard de Snyk, fuera del alcance de
esta unidad más allá de subir la instantánea.

### 6. Documento de entrega (`docs/entrega-u3/`)

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

### 7. Fuera de alcance

Despliegue productivo del stack de monitoreo; node-exporter y cAdvisor; notificaciones reales
(Slack, Telegram, correo); reentrega de la U2; issues de otros integrantes (#97, EQ2 #65); crear
cuentas o equipos en Snyk; correr el análisis real de Snyk fuera de la sesión del autor con su
token.

## Complexity Tracking

Sin violaciones de la constitución que justificar.
