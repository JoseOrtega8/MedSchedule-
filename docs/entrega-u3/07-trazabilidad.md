# 7. Módulo b: visor de trazabilidad con registros (logs) y trazas

**Pull request:** [[PR-107]]

## 7.1 Qué resuelve

Antes de esta unidad, investigar un incidente significaba abrir `storage/logs/laravel.log`: texto
plano, sin forma de saber a qué petición pertenecía cada línea ni en qué parte del código se fue
el tiempo. Este módulo agrega dos fuentes enlazadas entre sí dentro del mismo Grafana del
módulo a:

| Fuente | Qué responde | Dónde se guarda |
|---|---|---|
| Logs estructurados | Qué pasó, con qué usuario, en qué ruta | Loki, 7 días |
| Trazas | En qué se fue el tiempo de una petición | Tempo, 48 horas |

## 7.2 Logs estructurados con contexto de traza

`config/logging.php` agrega el canal `json`: una línea JSON por registro (Monolog
`JsonFormatter`) en `storage/logs/medschedule.json`. Se activa con `LOG_STACK=single,json`, que
conserva `laravel.log` y agrega el nuevo archivo.

El canal usa el driver `monolog` con `StreamHandler`. No es un detalle: el driver `single` de
Laravel 12.53 ignora la clave `processors`, y con él los logs salían sin redactar ni contexto
de traza. Por la misma razón, los canales `single` y `daily` reciben los mismos procesadores
mediante el tap `App\Logging\AplicarRedaccion`, de modo que `laravel.log` también queda
enmascarado.

Dos procesadores actúan sobre cada registro, en este orden:

| Procesador | Qué hace |
|---|---|
| `AgregarContextoTraza` | Agrega `trace_id`, `span_id`, `request_id`, `user_id`, `route` y `method`. Para el usuario usa `hasUser()`, que no dispara consultas y evita un ciclo con el log de SQL |
| `RedactarDatosSensibles` | Enmascara credenciales y PII médica en el mensaje, el contexto y los datos extra, a cualquier profundidad |

## 7.3 Enmascarado de PII

El enmascarado trabaja en cuatro frentes. Las claves y los patrones se sustituyen por
`[redactado]`; los literales que aparecen en una sentencia SQL se sustituyen por `'?'` o `?`, y
una `QueryException` se reescribe como `QueryException: <sentencia> [SQLSTATE …]`. (En la
auditoría, módulo c, el marcador es otro: `[protegido]`.)

| Frente | Regla |
|---|---|
| Claves del contexto | Una clave se enmascara si **contiene** alguna de estas subcadenas, sin distinguir mayúsculas: `token`, `password`, `secret`, `authorization`, `cookie`, `curp`, `email`, `allergies`, `chronic_conditions`, `blood_type`, `emergency_contact`, `api_key`, `apikey`, `credential`, `phone`, `telefono`, `signature`. Así `remember_token`, `access_token` o `password_confirmation` quedan cubiertas sin listarlas |
| Patrones en el texto | Dentro del mensaje y de cualquier valor de texto: correos electrónicos, credenciales `Bearer` o `Basic` (incluida la palabra del esquema) y CURP de 18 caracteres |
| Excepciones | Una excepción del contexto se reduce a clase, mensaje seguro, archivo y línea. Nunca se escribe el stacktrace, que lleva argumentos |
| Consultas | El mensaje de una `QueryException` trae los valores interpolados en el SQL. `App\Support\MensajeSeguro` lo sustituye por la sentencia parametrizada (`getSql()`) más el código SQLSTATE, y recorre **toda** la cadena de causas: una `QueryException` directa, envuelta por otra excepción o producida dentro de una vista Blade se enmascara igual, tanto en el mensaje de Laravel como en el mensaje crudo del driver |

Las subcadenas `key` y `hash` no se agregaron solas a propósito: enmascararían claves inocentes
como `cache_key`.

Límites declarados en el propio código:

- Una contraseña escrita en texto libre, sin clave propia (por ejemplo, interpolada en el
  mensaje), no tiene un patrón detectable y **no se enmascara**. La regla del equipo es pasar
  cualquier secreto en el contexto con su clave (`password`, `token`), que sí se enmascara por
  nombre.
- El enmascarado de literales en SQL (comillas y números tras `=` o dentro de `IN (...)`) es
  una red de seguridad con expresiones acotadas, no un analizador de SQL. Una sentencia bien
  parametrizada no tiene nada que enmascarar.

## 7.4 Trazas OpenTelemetry

Las trazas usan `open-telemetry/sdk` 1.15.0 y `open-telemetry/exporter-otlp` 1.4.0, en PHP puro
(sin extensión C), y se envían a Tempo por OTLP/HTTP. Están apagadas por defecto
(`OTEL_ENABLED=false`): apagadas, el proveedor es nulo y no hay costo ni conexiones.

| Elemento | Cómo se genera | Qué guarda |
|---|---|---|
| Span raíz de la petición | Middleware `IniciarTraza`, el primero de la pila | Método, nombre de ruta, **plantilla** de la ruta (`url.template`), código de respuesta y, si hay sesión, el id del usuario. Estado de error en respuestas 5xx |
| Consultas SQL | `DB::listen` | La sentencia parametrizada, **sin bindings**; el inicio se calcula a partir de la duración que reporta Laravel. `db.statement` no pasa por el enmascarado de literales: un valor escrito directamente en SQL crudo, sin parámetro, llegaría al span (límite declarado) |
| Jobs de cola | Eventos de la cola | Un span por job, abierto en `JobProcessing` y cerrado en `JobProcessed`, `JobFailed` o `JobExceptionOccurred` |
| Google Calendar | `GoogleCalendarService` | Un span por llamada (`events.insert`, `events.delete`, `events.list`) con `peer.service=google-calendar` |

Detalles que salieron de las revisiones y quedaron en el diseño:

- **Plantilla en vez de ruta real.** El span guarda `reset-password/{token}`, nunca la ruta con
  el token real. Las rutas de restablecimiento y verificación de correo llevan secretos en sus
  parámetros.
- **Reintento de jobs.** Si al job le quedan reintentos, Laravel lo devuelve a la cola sin
  disparar `JobProcessed` ni `JobFailed`; sin escuchar `JobExceptionOccurred`, el span quedaba
  abierto.
- **Excepciones sin valores.** En lugar de `recordException()`, que guarda el stacktrace con
  argumentos, los spans registran un evento `exception` con la clase y el mensaje seguro de
  `MensajeSeguro`.
- **Tempo caído no frena la aplicación.** Los spans se acumulan en un `BatchSpanProcessor` y se
  envían en `terminate()`, después de responder. El transporte tiene timeout de 1 s y ningún
  reintento: con los valores por defecto, un Tempo inalcanzable bloqueaba cerca de 40 s. Así, un
  Tempo caído acota el costo a 1 s por petición, después de responder; con `php artisan serve`,
  que atiende una petición a la vez, ese segundo sí retrasa a la siguiente. Si el envío falla,
  se pierden esos spans y queda una advertencia en el log.
- **El scrape no se traza.** `/metrics` se consulta cada 15 s y solo generaría ruido.
- **Propagación.** Se respeta la cabecera `traceparent` entrante y la respuesta incluye
  `X-Trace-Id`. El `traceparent` se acepta tal cual: un cliente podría fijar el `trace_id`. Es
  aceptable en local; en producción debe validarse en el borde.

## 7.5 Folio en la página de error

`resources/views/errors/500.blade.php` muestra un mensaje genérico y un **folio**, sin ningún
detalle interno. Con las trazas activas el folio es el `trace_id`, que localiza la traza en
Tempo; con las trazas apagadas es el `request_id`, que localiza las líneas del log de esa
petición.

## 7.6 Loki, Tempo, Alloy y la correlación

| Componente | Imagen | Configuración |
|---|---|---|
| Loki | `grafana/loki:3.7.8` | `infra/monitoreo/loki/loki.yml`: almacenamiento local, esquema `v13`, retención de 168 h |
| Tempo | `grafana/tempo:2.9.5` | `infra/monitoreo/tempo/tempo.yml`: receptor OTLP/HTTP en 4318, retención de 48 h |
| Alloy | `grafana/alloy:v1.19.2` | `infra/monitoreo/alloy/config.alloy`: lee `medschedule.json` y lo envía a Loki |

Tempo se fija en la rama 2.9: la rama 3.x cambió el formato de configuración. Alloy extrae como
etiquetas solo el nivel y la ruta (baja cardinalidad); el `trace_id` se queda dentro de la línea.

La correlación está aprovisionada en `infra/monitoreo/grafana/provisioning/datasources/trazabilidad.yml`:

| Dirección | Mecanismo |
|---|---|
| Log → traza | Campo derivado de Loki: la expresión `"trace_id":"([0-9a-f]{32})"` convierte el identificador de cada línea en un enlace a Tempo |
| Traza → logs | `tracesToLogsV2` de Tempo: desde un span, busca en Loki las líneas que contienen su `trace_id`, en una ventana de 5 minutos antes y después |

El tablero "MedSchedule - Trazabilidad" reúne los logs filtrables por nivel, las trazas de más
de 100 ms y las peticiones con error, estas dos con consultas TraceQL.

## 7.7 Pruebas

El módulo agrega 40 métodos de prueba: 22 en `tests/Feature/Trazas/` y 18 unitarios en
`tests/Unit/Logging/` y `tests/Unit/Support/`. Las pruebas de trazas usan el `InMemoryExporter`
de OpenTelemetry, sin Tempo. Entre ellas:

| Prueba | Qué verifica |
|---|---|
| `test_peticion_genera_span_raiz_y_cabecera_x_trace_id` | Hay span raíz y la respuesta devuelve su identificador |
| `test_ningun_span_guarda_la_ruta_real_con_tokens` | Ningún atributo contiene la ruta real |
| `test_consultas_de_la_peticion_son_spans_hijos_sin_bindings` | Las consultas son spans hijos y no llevan valores |
| `test_reintento_de_job_cierra_su_span_via_exception_occurred` | El span del job no queda abierto al reintentar |
| `test_query_exception_en_job_no_expone_bindings_en_su_span` | Una excepción de consulta en un job no expone valores |
| `test_scrape_de_metricas_no_produce_spans` | `/metrics` no se traza |

Resultado de la suite: [[PENDIENTE: resultado de php artisan test --filter="Trazas|RedactarDatosSensibles|MensajeSeguro" en la rama feat/107-trazabilidad, de evidencia/pruebas-trazas.txt]].

## 7.8 Costo de la instrumentación

El plan fijó como meta que la instrumentación no mueva el p95 medido en la Unidad 2 más de un
10 %. Se corrió la misma prueba de k6 de la Unidad 2 (`tests/carga/jri-prueba.js`), en la misma
máquina, sin instrumentación (métricas en memoria, trazas apagadas) y con ella (métricas en
Redis, trazas activas):

| Corrida | p95 de `http_req_duration` | Evidencia |
|---|---|---|
| Sin instrumentación | [[PENDIENTE: p95 de http_req_duration, de evidencia/k6-sin-instrumentacion.txt]] | `evidencia/k6-sin-instrumentacion.txt` |
| Con instrumentación | [[PENDIENTE: p95 de http_req_duration, de evidencia/k6-con-instrumentacion.txt]] | `evidencia/k6-con-instrumentacion.txt` |
| Diferencia | [[PENDIENTE: diferencia porcentual entre ambos p95, calculada de los dos archivos k6-*-instrumentacion.txt]] | — |

[[PENDIENTE: lectura del resultado frente a la meta del 10 %; si la supera, causa probable, a partir de los dos archivos k6-*-instrumentacion.txt]]

## 7.9 Antes y después

### Antes: un log de texto plano

Con las trazas apagadas y solo el canal `single`, se llamó al panel del paciente
(`/patient/dashboard/data`), el endpoint con más consultas por petición medido en la Unidad 2.
`laravel.log` no permite saber qué parte de la petición tarda ni relacionar sus líneas entre sí.

![Archivo laravel.log en texto plano sin identificador de petición ni desglose de tiempos](evidencia/trazas-01-antes-laravel-log.png)

### Después: la cascada de la petición

Con `OTEL_ENABLED=true` y `LOG_STACK=single,json` se repitió la llamada y se abrió su traza en
Grafana a partir del `X-Trace-Id` de la respuesta.

![Cascada de la traza de /patient/dashboard/data en Tempo con el span raíz y un span por cada consulta SQL](evidencia/trazas-02-cascada.png)

[[PENDIENTE: consulta o patrón que domina el tiempo de la petición y su duración, leídos de la traza de evidencia/trazas-02-cascada.png]]

### Después: del log a la traza y de vuelta

![Línea de log en Loki con el campo trace_id convertido en enlace a Tempo](evidencia/trazas-03-log-a-traza.png)

![Traza en Tempo con el acceso a las líneas de log de la misma petición en Loki](evidencia/trazas-04-traza-a-logs.png)

### Después: el folio de un error localiza su traza

Se provocó un error 500 controlado en local. El usuario ve el folio y nada más; ese folio
localiza la traza en Grafana.

![Página de error 500 con el folio de seguimiento y sin detalle interno](evidencia/trazas-05-folio-500.png)

![Traza localizada en Grafana a partir del folio mostrado en la página de error](evidencia/trazas-06-folio-en-grafana.png)
