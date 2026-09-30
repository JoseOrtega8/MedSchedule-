# 3. Entorno requerido

## 3.1 Piezas del entorno

| Pieza | Dónde se declara | Para qué |
|---|---|---|
| Entorno de liberación | `.devcontainer/` (Unidad 2, ampliado en esta unidad) | Aplicación PHP 8.2 + Node 20 + MySQL 8.0 + Docker, en GitHub Codespaces |
| Stack de análisis estático | `infra/sonarqube/docker-compose.yml` (Unidad 2) | SonarQube Community + PostgreSQL 16 |
| Stack de observabilidad | `infra/monitoreo/docker-compose.yml` (esta unidad) | Prometheus, Alertmanager, Grafana, Loki, Tempo, Alloy, exporters, Redis y Mailpit |
| Pipeline | `.github/workflows/release.yml` y `ci.yml` | Integración, compuertas, pruebas y despliegue |

El entorno de liberación es GitHub Codespaces a partir de `.devcontainer/`, igual que en la
Unidad 2. Para esta unidad el devcontainer se amplió (commit `6c99cfb`):

- **Docker dentro del contenedor** (feature `docker-in-docker`): los dos stacks de `infra/` se
  levantan con `docker compose` dentro del propio Codespace.
- **Requisito de máquina** (`hostRequirements`): 4 núcleos y 16 GB de memoria. Con menos, la
  aplicación, MySQL, SonarQube y el stack de observabilidad no caben a la vez.
- **Puertos reenviados** 8000, 3306, 3000, 9000, 9090, 9093 y 8025, todos con visibilidad
  privada: solo la cuenta dueña del Codespace puede abrirlos.

Toda la evidencia de este documento se generó en el Codespace `ideal-rotary-phone-pj7rjv7qwj6gfjpq`
(rama `feat/105-u3-sdd`): AMD EPYC 7763 con 4 núcleos, 16 GB, Debian 12, PHP 8.2.29, Node 20,
Docker 29.8 con Compose 2.40 y k6 2.3.0 (`evidencia/codespace-entorno.txt`). Cada archivo de
evidencia de terminal lleva en su encabezado el entorno donde se ejecutó.

## 3.2 Cómo se levanta dentro del Codespace

| Paso | Comando | Resultado |
|---|---|---|
| 1 | Crear el Codespace desde la rama (botón "Code → Codespaces" en GitHub o `gh codespace create`) | Contenedor con PHP, Node, MySQL y Docker; `post-create.sh` instala dependencias |
| 2 | `php artisan serve --host=0.0.0.0 --port=8000` | Aplicación escuchando para Prometheus y la sonda de disponibilidad |
| 3 | `bash scripts/monitoreo-local.sh` | Levanta `infra/monitoreo/` y espera a Prometheus, Alertmanager y Grafana |
| 4 | `sudo sysctl -w vm.max_map_count=262144` y `bash scripts/sonarqube-local.sh` | Levanta `infra/sonarqube/` (Elasticsearch, dentro de SonarQube, exige ese límite del kernel) |

`scripts/monitoreo-local.sh` comprueba que exista `infra/monitoreo/.env` y que `METRICS_TOKEN`
esté en el `.env` de la aplicación; copia ese token a un archivo local
(`infra/monitoreo/prometheus/secretos/metrics_token`, excluido en `.gitignore`) que solo monta
Prometheus, y nunca lo imprime.

Dos ajustes son propios del Codespace y no cambian el repositorio:

- `MYSQL_EXPORTER_HOST` apunta al MySQL del devcontainer en su red de Docker (puerto 3306), en
  lugar del valor por defecto pensado para un MySQL en el anfitrión.
- El directorio `infra/monitoreo/prometheus/secretos` necesita permiso de lectura para el
  usuario del contenedor de Prometheus (`chmod 755` sobre el directorio; el archivo del token
  conserva sus permisos).

## 3.3 Puertos

Todos los puertos de los stacks se publican solo en `127.0.0.1` del Codespace: ninguno queda
expuesto a la red. Desde el navegador se abren con el reenvío de puertos de Codespaces (pestaña
"Ports" o `gh codespace ports forward <puerto>:<puerto>`), que exige la sesión de GitHub de la
cuenta dueña.

| Servicio | Puerto | Uso |
|---|---|---|
| Prometheus | `127.0.0.1:9090` | Consultas, estado de reglas y alertas |
| Alertmanager | `127.0.0.1:9093` | Estado de las notificaciones |
| Grafana | `127.0.0.1:3000` | Tableros y exploración de logs y trazas |
| Mailpit | `127.0.0.1:8025` | Buzón donde llegan las alertas |
| Redis | `127.0.0.1:6380` | Almacén de métricas de la aplicación (6380 para no chocar con otro Redis) |
| Tempo (OTLP/HTTP) | `127.0.0.1:4318` | Receptor de trazas que envía la aplicación |
| SonarQube | `127.0.0.1:9000` | Análisis estático y puerta de calidad |
| blackbox-exporter, mysqld-exporter, Loki, Alloy | Sin puerto publicado | Solo se comunican dentro de la red del compose |

## 3.4 Lectura del log por Alloy

Alloy lee el log JSON de la aplicación montando `storage/logs` del proyecto
(`../../storage/logs:/logs:ro`). Dentro del Codespace el Docker interno ve el mismo sistema de
archivos que la aplicación, así que el montaje funciona sin configuración adicional.

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
| `MYSQL_EXPORTER_HOST` | MySQL a vigilar. El valor por defecto (`host.docker.internal:8889`) es para un MySQL en el anfitrión; en el Codespace apunta al MySQL del devcontainer (apartado 3.2) |

### Secretos de sesión o del repositorio

| Variable | Dónde vive | Para qué |
|---|---|---|
| `SONAR_TOKEN` | Sesión de shell o secreto de GitHub | Autenticación del escáner de SonarQube |
| `SONAR_HOST_URL` | Secreto de GitHub | Instancia de SonarQube accesible desde Actions |
| `SONAR_HABILITADO` | Variable del repositorio en GitHub | Activa los jobs de SonarQube |
| `SNYK_TOKEN` | Sesión de shell o secreto de GitHub | Autenticación del CLI de Snyk |

## 3.6 Las pruebas no necesitan el stack

`phpunit.xml` fija `METRICAS_ALMACEN=memoria` y `OTEL_ENABLED=false`, así que la suite corre sin
Redis, sin Tempo y sin Loki. Las pruebas de los tres módulos se ejecutaron en el Codespace y su salida
se guarda como evidencia (`evidencia/pruebas-*.txt`); en GitHub Actions, `release.yml` corre la
suite completa en modo informativo y `ci.yml` solo un subconjunto de cuatro clases que no
incluye las de esta unidad (apartado 11).

En ejecución normal, si Redis o Tempo no están disponibles, la aplicación responde con
normalidad y solo registra una advertencia. Loki no genera ni eso: la aplicación nunca habla con
Loki, solo escribe su archivo de log, que Alloy lee por su cuenta. El monitoreo nunca puede
tumbar la aplicación que vigila.
