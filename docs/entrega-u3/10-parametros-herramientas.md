# 10. Parámetros de configuración de las herramientas

Cada parámetro está copiado del archivo donde vive. Se indica el valor, el archivo y la razón.
Las versiones de las imágenes están fijadas en `infra/monitoreo/docker-compose.yml`; las de las
bibliotecas PHP, en `composer.lock`.

## 10.1 Prometheus `v3.14.0`

| Parámetro | Valor | Archivo | Por qué |
|---|---|---|---|
| `scrape_interval` | `15s` | `infra/monitoreo/prometheus/prometheus.yml` | Resolución suficiente para alertas de 1 minuto sin cargar la aplicación |
| `evaluation_interval` | `15s` | El mismo | Las reglas se evalúan al ritmo de la recolección |
| `--storage.tsdb.retention.time` | `15d` | `infra/monitoreo/docker-compose.yml` | Histórico de dos semanas para comparar comportamientos |
| `--web.enable-lifecycle` | activado | El mismo | Recargar la configuración sin reiniciar el contenedor |
| `rule_files` | `/etc/prometheus/alertas.yml` | `prometheus.yml` | Las seis reglas del apartado 4 |
| Job `medschedule` | `/metrics` en `host.docker.internal:8000`, `authorization.credentials_file` | `prometheus.yml` | El token se lee de un archivo local, nunca del YAML versionado |
| Job `disponibilidad` | Módulo `http_2xx` contra `http://host.docker.internal:8000/up` | `prometheus.yml` | Disponibilidad vista desde afuera, contra la ruta de salud de Laravel |
| Puerto | `127.0.0.1:9090` | `docker-compose.yml` | Solo local |

## 10.2 Alertmanager `v0.34.1`

| Parámetro | Valor | Archivo | Por qué |
|---|---|---|---|
| `smtp_smarthost` | `mailpit:1025` | `infra/monitoreo/alertmanager/alertmanager.yml` | Entrega al buzón de pruebas, sin correo real |
| `smtp_require_tls` | `false` | El mismo | Mailpit local no usa TLS |
| `group_by` | `[alertname]` | El mismo | Un correo por tipo de alerta, no por instancia |
| `group_wait` | `30s` | El mismo | Agrupa alertas simultáneas sin retrasar demasiado el aviso |
| `group_interval` | `1m` | El mismo | Frecuencia de actualización de un grupo ya notificado |
| `repeat_interval` | `1h` | El mismo | Recuerda una alerta que sigue activa sin saturar el buzón |
| `send_resolved` | `true` | El mismo | También se notifica cuando el problema se resuelve |

## 10.3 Grafana `13.2.2`

| Parámetro | Valor | Archivo | Por qué |
|---|---|---|---|
| `GF_SECURITY_ADMIN_USER`, `GF_SECURITY_ADMIN_PASSWORD` | De `GRAFANA_ADMIN_USER` y `GRAFANA_ADMIN_PASSWORD` | `docker-compose.yml` e `infra/monitoreo/.env` | Obligatorias: el compose no arranca sin ellas |
| `GF_USERS_ALLOW_SIGN_UP` | `false` | `docker-compose.yml` | Nadie crea cuentas desde la interfaz |
| `GF_AUTH_ANONYMOUS_ENABLED` / `GF_AUTH_ANONYMOUS_ORG_ROLE` | `true` / `Viewer` | El mismo | Lectura sin credenciales, solo porque escucha en `127.0.0.1` |
| Proveedor de tableros | Carpeta `MedSchedule`, `allowUiUpdates: false` | `grafana/provisioning/dashboards/medschedule.yml` | El repositorio es la fuente de verdad de los tableros |
| Fuentes de datos | Prometheus (por defecto), Alertmanager, Loki, Tempo | `grafana/provisioning/datasources/*.yml` | Aprovisionadas como código |
| Campo derivado de Loki | `"trace_id":"([0-9a-f]{32})"` → Tempo | `datasources/trazabilidad.yml` | Enlace de log a traza |
| `tracesToLogsV2` | Loki, ventana de −5 min a +5 min | El mismo | Enlace de traza a logs |

## 10.4 Loki `3.7.8`

| Parámetro | Valor | Archivo | Por qué |
|---|---|---|---|
| `auth_enabled` | `false` | `infra/monitoreo/loki/loki.yml` | Un solo inquilino local |
| `schema_config` | `tsdb`, esquema `v13`, índice de 24 h | El mismo | Índice TSDB, el formato de índice de Loki 3 |
| `retention_period` | `168h` | El mismo | Siete días de logs |
| `compactor.retention_enabled` | `true` | El mismo | Sin compactador la retención no se aplica |
| `allow_structured_metadata` | `true` | El mismo | Permite metadatos estructurados, disponibles con el esquema `v13` |
| `user` | `root` | `docker-compose.yml` | Solo local: el volumen nace con dueño root y Loki corre con otro usuario |

## 10.5 Tempo `2.9.5`

| Parámetro | Valor | Archivo | Por qué |
|---|---|---|---|
| Receptor OTLP | HTTP en `0.0.0.0:4318` | `infra/monitoreo/tempo/tempo.yml` | La aplicación envía por OTLP/HTTP; publicado en el anfitrión solo en `127.0.0.1:4318` |
| `max_block_duration` | `5m` | El mismo | Las trazas se vuelven consultables en bloque pronto |
| `block_retention` | `48h` | El mismo | Las trazas sirven para diagnóstico reciente |
| Almacenamiento | `local` | El mismo | Entorno local |
| Versión | Rama 2.9 | `docker-compose.yml` | La rama 3.x cambió el formato de configuración |

## 10.6 Grafana Alloy `v1.19.2`

| Parámetro | Valor | Archivo | Por qué |
|---|---|---|---|
| Archivo leído | `/logs/medschedule.json` | `infra/monitoreo/alloy/config.alloy` | El canal `json` de la aplicación |
| Montaje | `../../storage/logs:/logs:ro` | `docker-compose.yml` | Solo lectura; requiere File sharing en Docker Desktop para macOS |
| Etiquetas | `level` (de `level_name`) y `route` (de `extra.route`) | `config.alloy` | Baja cardinalidad; el `trace_id` queda dentro de la línea |
| Destino | `http://loki:3100/loki/api/v1/push` | El mismo | Loki dentro de la red del compose |

## 10.7 blackbox-exporter `v0.28.0`, mysqld-exporter `v0.20.0`, Redis `8.8.3-alpine` y Mailpit `v1.31.2`

| Herramienta | Parámetro | Valor | Archivo | Por qué |
|---|---|---|---|---|
| blackbox-exporter | Módulo | `http_2xx` | `prometheus.yml` | La sonda espera una respuesta 2xx de `/up` |
| mysqld-exporter | `--mysqld.address` | `MYSQL_EXPORTER_HOST` (por defecto `host.docker.internal:8889`) | `docker-compose.yml` | MySQL de MAMP local, alcanzado desde el contenedor |
| mysqld-exporter | `--mysqld.username` | `exporter` | El mismo | Usuario de solo lectura; su contraseña en `MYSQL_EXPORTER_PASSWORD` |
| Redis | Puerto | `127.0.0.1:6380` | El mismo | No choca con otro Redis local en 6379 |
| Mailpit | Puerto web | `127.0.0.1:8025` | El mismo | Buzón visible donde se comprueba la llegada de la alerta |

## 10.8 Bibliotecas de la aplicación

| Biblioteca | Versión | Parámetro | Valor | Archivo | Por qué |
|---|---|---|---|---|---|
| `promphp/prometheus_client_php` | 2.15.1 | Almacén | `redis` (`memoria` en pruebas) | `config/metricas.php` | Los contadores deben sobrevivir entre peticiones |
| | | Timeouts de Redis | `0.2` s de conexión y de lectura | El mismo | Un Redis caído no frena la petición |
| | | Conexión persistente | `METRICAS_REDIS_PERSISTENTE`, por defecto `true` (opción `persistent` de Predis) | `config/metricas.php`, `MetricasServiceProvider` | Reutiliza el socket entre peticiones del mismo proceso; ahorra de 0.2 a 0.5 ms por petición (`evidencia/k6-atribucion.txt`) |
| | | Métrica `php_info` | desactivada | `MetricasServiceProvider` | Serie sin valor para el monitoreo |
| `predis/predis` | 3.6.0 | Adaptador | `Predis` | El mismo | No requiere la extensión `phpredis` |
| `open-telemetry/sdk` | 1.15.0 | `habilitadas` | `OTEL_ENABLED`, por defecto `false` | `config/trazas.php` | Pruebas y CI sin Tempo |
| | | Muestreo | `ParentBased(TraceIdRatioBased(OTEL_TRACES_SAMPLER_ARG))`, por defecto `1.0` | `TrazasServiceProvider` | Respeta la decisión del llamador; en local se muestrea todo |
| | | Procesador | `BatchSpanProcessor` con envío en `terminate()` | El mismo | El envío ocurre después de responder |
| `open-telemetry/exporter-otlp` | 1.4.0 | Transporte | OTLP/HTTP con `application/json`, `timeout: 1.0`, `maxRetries: 0` | El mismo | Con los valores por defecto (10 s de timeout y 3 reintentos) un Tempo inalcanzable bloqueaba cerca de 40 s; así el costo queda acotado a 1 s |
| Exportador propio | — | Exportador de spans | `App\Observability\Trazas\ExportadorOtlpJson` con `CodificadorOtlpJson`, en lugar de `OpenTelemetry\Contrib\Otlp\SpanExporter` | `TrazasServiceProvider` | El oficial serializa con `google/protobuf` en PHP puro (sin `ext-protobuf`). El propio produce el mismo JSON OTLP con arreglos y `json_encode`, verificado contra el oficial en `CodificadorOtlpJsonTest`: `forceFlush()` bajó de 3.2 a 0.96 ms (p50) |
| | | Log interno de OpenTelemetry | `Psr3LogWriter` sobre el log de Laravel | `TrazasServiceProvider` | Los avisos del SDK (envío fallido, éxito parcial) quedan en el log de la aplicación y pasan por el enmascarado |
| Monolog | 3.10.0 | Canal `json` | Driver `monolog`, `StreamHandler`, `JsonFormatter` | `config/logging.php` | El driver `single` ignora `processors` |
| | | Procesadores | `AgregarContextoTraza`, `RedactarDatosSensibles` | El mismo | Contexto de traza y PII enmascarada |
| | | `LOG_STACK` | `single,json` | `.env.example` | Conserva `laravel.log` y agrega el archivo para Loki |

## 10.9 Programador de tareas de Laravel

| Parámetro | Valor | Archivo | Por qué |
|---|---|---|---|
| Tarea | `auditoria:verificar` | `routes/console.php` | Verificación de integridad de la auditoría |
| Frecuencia | `dailyAt('03:00')` | El mismo | Una vez al día, en horario de baja actividad |
| Requisito | `php artisan schedule:run` cada minuto (cron del sistema) | No versionado | Sin ese cron la tarea no se ejecuta; debe configurarse en el servidor |

## 10.10 SonarQube

Los parámetros de `sonar-project.properties` e `infra/sonarqube/docker-compose.yml` son los de
la Unidad 2 (`docs/entrega-u2/05-parametros-herramientas.md`, apartado 5.2). Esta unidad agrega:

| Parámetro | Valor | Archivo | Por qué |
|---|---|---|---|
| `sonar.qualitygate.wait` | `true` | `scripts/sonarqube-escanear.sh`, `ci.yml`, `release.yml` | El escáner espera el veredicto y falla si la puerta no se supera |
| `sonar.qualitygate.timeout` | `300` | Los mismos | Cinco minutos como máximo de espera del veredicto |
| `SONAR_HABILITADO` | Variable del repositorio | `ci.yml`, `release.yml` | Los jobs solo corren cuando existe una instancia accesible desde Actions |
| Puerto publicado | `127.0.0.1:9000` | `infra/sonarqube/docker-compose.yml` | Solo local, igual que el stack de observabilidad; en la Unidad 2 se publicaba en todas las interfaces |

## 10.11 Snyk CLI `1.1307.4`

| Parámetro | Valor | Archivo | Por qué |
|---|---|---|---|
| Invocación | `npx --yes snyk@1.1307.4` | `scripts/snyk-escanear.sh` | Versión fijada, sin instalación global |
| `--all-projects` | activado | El mismo | PHP y JavaScript en una sola corrida |
| `--severity-threshold` | `high` | El mismo | Solo altas y críticas detienen la liberación |
| `--json-file-output` | `SNYK_SALIDA` o `docs/entrega-u3/evidencia/snyk-resultado.json` | El mismo | Detalle consultable y publicado como artefacto |
| Política | `version: v1.25.0`, `ignore: {}` | `.snyk` | Excepciones solo con `reason` y `expires` |

## 10.12 k6

Los parámetros de `tests/carga/jri-prueba.js` son los de la Unidad 2 (apartado 5.1 de ese
documento). `scripts/instalar-k6.sh` declara k6 2.2.0 como versión de referencia: si ya hay un
k6 instalado lo usa tal cual, y si no, lo instala (repositorio oficial en Linux, con el binario publicado como reserva; Homebrew en macOS). En esta unidad se usa la misma
prueba, sin cambios, para generar tráfico en los tableros y para medir el costo de la
instrumentación (apartado 7.8).
