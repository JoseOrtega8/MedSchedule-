# 3. Entorno requerido

## 3.1 Piezas del entorno

| Pieza | Dónde se declara | Para qué |
|---|---|---|
| Entorno de liberación | `.devcontainer/` (Unidad 2) | Aplicación PHP 8.2 + Node 20 + MySQL 8.0, reproducible en GitHub Codespaces (usado en la Unidad 2; en esta unidad la evidencia se generó en local, ver 3.1) |
| Stack de análisis estático | `infra/sonarqube/docker-compose.yml` (Unidad 2) | SonarQube Community + PostgreSQL 16 |
| Stack de observabilidad | `infra/monitoreo/docker-compose.yml` (esta unidad) | Prometheus, Alertmanager, Grafana, Loki, Tempo, Alloy, exporters, Redis y Mailpit |
| Pipeline | `.github/workflows/release.yml` y `ci.yml` | Integración, compuertas, pruebas y despliegue |

El entorno de liberación sigue siendo el de la Unidad 2: GitHub Codespaces a partir de
`.devcontainer/`. El devcontainer actual declara Node 20, `sshd` y GitHub CLI como features y
publica los puertos 8000 (Laravel) y 3306 (MySQL); no declara Docker dentro del contenedor.
Por eso los dos stacks de `infra/` se levantaron con Docker local (Docker Desktop en macOS),
contra la aplicación servida en el puerto 8000 del anfitrión, y ninguna prueba de esta unidad se
ejecutó en un Codespace. Llevar la observabilidad al Codespace requiere agregar la feature de
Docker dentro del contenedor (`docker-in-docker`) al devcontainer; queda como mejora para la
siguiente unidad.

## 3.2 Cómo se levanta

| Paso | Comando | Resultado |
|---|---|---|
| 1 | `php artisan serve --host=0.0.0.0 --port=8000` | Aplicación escuchando para Prometheus y la sonda de disponibilidad |
| 2 | `bash scripts/monitoreo-local.sh` | Levanta `infra/monitoreo/` y espera a Prometheus, Alertmanager y Grafana |
| 3 | `bash scripts/sonarqube-local.sh` | Levanta `infra/sonarqube/` (solo para el análisis estático) |

`scripts/monitoreo-local.sh` comprueba que exista `infra/monitoreo/.env` y que `METRICS_TOKEN`
esté en el `.env` de la aplicación; copia ese token a un archivo local
(`infra/monitoreo/prometheus/secretos/metrics_token`, excluido en `.gitignore`) que solo monta
Prometheus, y nunca lo imprime.

## 3.3 Puertos

Todos los puertos del stack de observabilidad se publican solo en `127.0.0.1`: es un entorno
local y ninguno queda expuesto a la red.

| Servicio | Puerto en el anfitrión | Uso |
|---|---|---|
| Prometheus | `127.0.0.1:9090` | Consultas, estado de reglas y alertas |
| Alertmanager | `127.0.0.1:9093` | Estado de las notificaciones |
| Grafana | `127.0.0.1:3000` | Tableros y exploración de logs y trazas |
| Mailpit | `127.0.0.1:8025` | Buzón donde llegan las alertas |
| Redis | `127.0.0.1:6380` | Almacén de métricas de la aplicación (6380 para no chocar con otro Redis local) |
| Tempo (OTLP/HTTP) | `127.0.0.1:4318` | Receptor de trazas que envía la aplicación |
| blackbox-exporter, mysqld-exporter, Loki, Alloy | Sin puerto publicado | Solo se comunican dentro de la red del compose |

El stack de SonarQube (`infra/sonarqube/docker-compose.yml`) también se publica solo en
`127.0.0.1:9000`.

## 3.4 Requisito de Docker Desktop en macOS

Alloy lee el log JSON de la aplicación montando `storage/logs` del proyecto
(`../../storage/logs:/logs:ro`). En macOS, Docker Desktop solo puede montar rutas incluidas en
**Settings → Resources → File sharing**. El proyecto vive en
`/Applications/MAMP/htdocs/MedSchedule-`, fuera de las rutas compartidas por defecto, así que
esa ruta debe agregarse. Sin ella, `docker compose up` no puede crear el contenedor de Alloy y
el servicio no levanta: Docker Desktop rechaza el montaje con un error "Mounts denied".

## 3.5 Variables de entorno

Se listan por nombre. Sus valores viven en `.env` o en `infra/monitoreo/.env`, ambos excluidos
del control de versiones; los archivos `.env.example` llevan los secretos vacíos.

### Aplicación (`.env`)

| Variable | Módulo | Para qué |
|---|---|---|
| `METRICS_TOKEN` | Monitoreo | Credencial Bearer que Prometheus presenta a `/metrics`. Sin ella el endpoint responde 404 |
| `METRICAS_ALMACEN` | Monitoreo | `redis` en ejecución normal, `memoria` en pruebas |
| `METRICAS_REDIS_HOST`, `METRICAS_REDIS_PORT` | Monitoreo | Redis del stack (por defecto `127.0.0.1:6380`) |
| `METRICAS_REDIS_PERSISTENTE` | Monitoreo | Conexión persistente a Redis (por defecto `true`) |
| `OTEL_ENABLED` | Trazabilidad | Enciende las trazas. Por defecto `false` |
| `OTEL_EXPORTER_OTLP_ENDPOINT` | Trazabilidad | Receptor OTLP de Tempo (`http://127.0.0.1:4318`) |
| `OTEL_TRACES_SAMPLER_ARG` | Trazabilidad | Proporción de peticiones muestreadas (por defecto `1.0`) |
| `OTEL_SERVICE_NAME` | Trazabilidad | Nombre del servicio en las trazas (`medschedule`) |
| `LOG_STACK` | Trazabilidad | Debe valer `single,json` para que exista `storage/logs/medschedule.json`, el archivo que lee Alloy |
| `AUDIT_HMAC_KEY` | Auditoría | Llave del sello HMAC. Obligatoria en producción: sin ella la aplicación no arranca |
| `GRAFANA_URL` | Auditoría | Base del enlace "Ver traza de la petición" del visor |

### Stack de observabilidad (`infra/monitoreo/.env`)

| Variable | Para qué |
|---|---|
| `GRAFANA_ADMIN_USER`, `GRAFANA_ADMIN_PASSWORD` | Administrador de Grafana. El compose se niega a arrancar si faltan |
| `MYSQL_EXPORTER_PASSWORD` | Contraseña del usuario de solo lectura `exporter` de MySQL |
| `MYSQL_EXPORTER_HOST` | MySQL a vigilar: `host.docker.internal:8889` con MAMP local (valor por defecto) |

### Secretos de sesión o del repositorio

| Variable | Dónde vive | Para qué |
|---|---|---|
| `SONAR_TOKEN` | Sesión de shell o secreto de GitHub | Autenticación del escáner de SonarQube |
| `SONAR_HOST_URL` | Secreto de GitHub | Instancia de SonarQube accesible desde Actions |
| `SONAR_HABILITADO` | Variable del repositorio en GitHub | Activa los jobs de SonarQube |
| `SNYK_TOKEN` | Sesión de shell o secreto de GitHub | Autenticación del CLI de Snyk |

## 3.6 Las pruebas no necesitan el stack

`phpunit.xml` fija `METRICAS_ALMACEN=memoria` y `OTEL_ENABLED=false`, así que la suite corre sin
Redis, sin Tempo y sin Loki. Las pruebas de los tres módulos se ejecutan localmente y su salida
se guarda como evidencia (`evidencia/pruebas-*.txt`); en GitHub Actions, `release.yml` corre la
suite completa en modo informativo y `ci.yml` solo un subconjunto de cuatro clases que no
incluye las de esta unidad (apartado 11).

En ejecución normal, si Redis o Tempo no están disponibles, la aplicación responde con
normalidad y solo registra una advertencia. Loki no genera ni eso: la aplicación nunca habla con
Loki, solo escribe su archivo de log, que Alloy lee por su cuenta. El monitoreo nunca puede
tumbar la aplicación que vigila.
