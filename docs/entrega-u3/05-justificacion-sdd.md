# 5. Justificación del uso de Spec-Driven Development

## 5.1 Por qué especificar antes de codificar

Los tres módulos de esta unidad tocan la parte más delicada de MedSchedule: los datos que la
aplicación **emite hacia afuera** de su base de datos. Una métrica, una línea de log, un span
de traza o un registro de auditoría son copias de información que terminan en otros sistemas
(Prometheus, Loki, Tempo, un CSV exportado). En un sistema con PII médica y un repositorio
público, un error de diseño ahí no es un defecto menor: es una filtración.

Spec-Driven Development obliga a responder primero **qué** debe hacer cada módulo y **qué no
debe hacer nunca**, antes de decidir cómo. Tres ejemplos de esta unidad muestran por qué importa:

| Requisito escrito antes del código | Decisión de diseño que forzó |
|---|---|
| FR-016: la auditoría nunca guarda el valor de contraseñas ni de datos clínicos | Revisar las escrituras existentes en `activity_logs` llevó al hallazgo de PII en claro en `PatientProfileController` |
| FR-011: las trazas no incluyen valores de parámetros | Los spans SQL guardan la sentencia parametrizada, nunca los bindings |
| FR-005: el endpoint de métricas se comporta como inexistente sin credencial | `/metrics` responde 404 y no 401, para no revelar que existe |

Además, la especificación define criterios medibles (SC-001 a SC-009) que después se
comprueban con evidencia: por ejemplo, que la notificación de una caída llegue en menos de
3 minutos o que el 100 % de las alteraciones manuales de auditoría se detecten.

## 5.2 Herramienta y constitución

Se usó Spec Kit, cuya configuración vive en `.specify/`. La constitución del proyecto
(`.specify/memory/constitution.md`, versión 1.0.0) fija siete principios que cada plan debe
superar antes de implementarse:

| Principio | Cómo lo cumple esta unidad |
|---|---|
| I. Estilo de código | Comentarios en español y `snake_case` en funciones y variables propias |
| II. Manejo de errores | Fallos de Redis, Tempo o Loki capturados con `try/catch` explícito y registrados como advertencia; la escritura de auditoría no captura excepciones: si falla, falla la operación auditada |
| III. Gestión de secretos | `METRICS_TOKEN`, `AUDIT_HMAC_KEY`, credenciales de Grafana y del exporter solo en `.env` |
| IV. Validación de entrada | Filtros del visor validados con `FiltrarAuditoriaRequest`; token de métricas comparado con `hash_equals` |
| V. Exposición de errores al cliente | La página de error 500 muestra solo un folio |
| VI. Spec-Driven Development | `spec.md` y `plan.md` preceden al código; `tasks.md` antes de implementar |
| VII. Control de versiones | Una rama por issue, commits convencionales |

## 5.3 Cómo se organizó

Los tres artefactos viven en `specs/003-observabilidad-auditoria/`:

| Archivo | Responde | Contenido |
|---|---|---|
| `spec.md` | Qué y por qué | Cinco historias de usuario (US1 dashboards de SonarQube, US2 monitoreo, US3 trazabilidad, US4 auditoría, US5 dependencias con Snyk), cada una con prueba independiente y escenarios de aceptación; requisitos FR-001 a FR-026; criterios de éxito SC-001 a SC-009; casos límite y supuestos |
| `plan.md` | Cómo | Contexto técnico, verificación de la constitución, estructura de archivos, versiones fijadas y diseño por componente, incluidas las tablas de alertas y de códigos de salida |
| `tasks.md` | En qué orden | Tareas T001 a T026 agrupadas por historia y por rama, con TDD: cada tarea de código escribe primero la prueba que falla, la ejecuta, implementa y la vuelve a ejecutar |

La tabla "Cobertura del spec" al final de `tasks.md` liga cada requisito con las tareas que lo
implementan, de modo que ningún requisito queda sin tarea.

Cada módulo vive en su propia rama y su propio pull request, con su propio archivo de
configuración (`config/metricas.php`, `config/trazas.php`, `config/auditoria.php`) y su propio
service provider. Así las ramas paralelas coinciden en pocas líneas: en la práctica hubo tres
conflictos, todos de líneas sueltas en `.env.example`, `bootstrap/providers.php` y
`phpunit.xml`.

El pull request general de planeación, [[PR-105]], contiene la especificación, el plan, las
tareas, la corrección de la puerta de calidad de SonarQube y este documento. Los módulos se
revisan en sus propios pull requests.

## 5.4 Cómo absorbió el SDD los cambios detectados al implementar

Una especificación no es un contrato inamovible: la implementación y las revisiones revelan
cosas que el plan no previó. La regla fue que **ningún cambio de diseño se queda solo en el
código**: se registra en la sección "Cambios respecto al plan original" de `tasks.md` y, cuando
afecta al diseño, en `plan.md`. Los principales:

| Tarea | Cambio | Motivo |
|---|---|---|
| T007 | Las pruebas de alertas incluyen casos negativos | Un caso solo positivo no demuestra que el umbral discrimine |
| T010 | El span raíz guarda `url.template` en lugar de la ruta real | La ruta real podía llevar tokens (`reset-password/{token}`, `verify-email/{id}/{hash}`) |
| T010 | Exportador OTLP con timeout de 1 s y sin reintentos | Con los valores por defecto, un Tempo inalcanzable bloqueaba cerca de 40 s cada petición |
| T010 | `/metrics` no se traza | El scrape cada 15 s solo generaba ruido |
| T011 | El span del job también se cierra en `JobExceptionOccurred` | Si al job le quedan reintentos, Laravel no dispara `JobProcessed` ni `JobFailed` y el span quedaba abierto |
| T012 | El canal `json` usa driver `monolog` con `StreamHandler` | El driver `single` ignora `processors` en Laravel 12.53: los logs salían sin redactar ni contexto de traza |
| T012 | Redacción ampliada a patrones en el texto, excepciones del contexto y claves por subcadena | Correos, credenciales Bearer y CURP aparecían dentro de los mensajes |
| T012 | `MensajeSeguro` recorre toda la cadena de excepciones | Una `QueryException` directa, envuelta o dentro de una vista exponía los valores de la consulta en el mensaje del log y en los eventos de los spans |
| T012 | Tap `AplicarRedaccion` en los canales `single` y `daily` | `laravel.log` se escribía sin enmascarar porque esos drivers no aplican `processors` |
| T013 | Folio de respaldo con el `request_id` | Con las trazas apagadas el folio decía "no disponible" |
| T016 | La migración sella las filas existentes; toda fila sin sello cuenta como rota | Una fila sin sello "perdonada" sería un hueco para insertar registros falsos |
| T016 | Se quitó la llave foránea `activity_logs.user_id` | Su `ON DELETE SET NULL` alteraba filas ya selladas al borrar un usuario |
| T016 | Transacción con 3 reintentos ante interbloqueos y JSON canónico con claves ordenadas | Concurrencia sobre el `lockForUpdate` y MySQL reordena las claves de las columnas JSON |
| T017 | `reason` y `observaciones` de la cita se guardan como `[protegido]` | Son texto clínico libre |
| T018 | El cierre de sesión lo registra solo el listener | El controlador también lo registraba y duplicaba la fila |
| T018/T020 | Orden `auth`, `throttle:60,1`, `auditar.denegado`, `role:admin` | El límite de tasa corta antes de escribir auditoría de accesos denegados |
| T020 | `fputcsv` sin escape invertido, `try/catch` en la exportación, id de la línea de tiempo limitado a 18 dígitos | Evitar celdas desplazadas hacia fórmulas y un desbordamiento que respondía 500 |
| T022 | El documento incluye File sharing de Docker Desktop, `LOG_STACK=single,json`, los límites de la auditoría y las condiciones reales del pipeline | Son requisitos que no eran evidentes en el plan |
| US5 | Se agregó la historia de Snyk como módulo adicional, en su propia rama | Las dependencias no se analizaban en ningún punto del pipeline |

Este registro es también la respuesta a la pregunta "¿el código hace lo que dice la
especificación?": la especificación se actualizó para decir lo que el código hace, con el
motivo de cada diferencia.
