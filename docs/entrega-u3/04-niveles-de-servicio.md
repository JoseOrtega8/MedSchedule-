# 4. Niveles de servicio ligados a alertas

## 4.1 De la liberación a la operación

En la Unidad 2 los niveles de servicio se verificaban **solo al liberar**: k6 medía el p95 y la
tasa de fallos durante dos minutos y el pipeline se detenía si no se cumplían. El propio
documento de la Unidad 2 lo reconocía (apartado 3.6): no había recolección continua, ni
alertas, ni histórico.

En esta unidad los mismos objetivos se vigilan de forma continua sobre la aplicación en
operación. Cada acuerdo que puede medirse en operación quedó ligado a una regla real de
`infra/monitoreo/prometheus/alertas.yml`, con su umbral copiado del acuerdo.

## 4.2 Acuerdo, alerta y umbral

| Acuerdo de la Unidad 2 (SLO) | Alerta | Expresión y umbral | Duración (`for`) | Severidad |
|---|---|---|---|---|
| `http_req_duration` p95 < 5000 ms | `LatenciaP95FueraDeAcuerdo` | `histogram_quantile(0.95, …[5m]) > 5` (segundos) | 5 min | crítica |
| `http_req_failed` < 1 % | `TasaErrores5xxAlta` | Proporción de respuestas `5..` en 5 min `> 0.01` | 1 min | crítica |
| Disponibilidad tras desplegar | `AplicacionCaida` | `probe_success{job="disponibilidad"} == 0` contra `/up` | 1 min | crítica |
| Aviso temprano, derivado del umbral de vigilancia propuesto en la Unidad 2 (300 ms) | `LatenciaP95Degradada` | `histogram_quantile(0.95, …[5m]) > 0.5` | 10 min | advertencia |
| Operación: base de datos | `BaseDeDatosCaida` | `mysql_up == 0` | 1 min | crítica |
| Operación: colas | `JobsFallidos` | `medschedule_jobs_fallidos > 0` | 10 min | advertencia |

Toda alerta se enruta por Alertmanager al receptor `correo-equipo`, que entrega en Mailpit
(`send_resolved: true`, así que también llega el aviso de resolución).

## 4.3 Acuerdos que no tienen alerta, y por qué

| Acuerdo | Por qué no tiene alerta propia |
|---|---|
| `duracion_panel` p95 < 5000 ms | Las alertas de latencia agregan todas las rutas. El tablero de servicio muestra el tráfico por ruta, pero no hay una regla específica para `/patient/dashboard/data` |
| `tasa_login_exitoso` > 99 % | Es una métrica de la prueba de carga, que conoce qué login debía funcionar. En operación, un login rechazado por contraseña incorrecta es comportamiento correcto; los intentos fallidos quedan en la auditoría (apartado 8) |
| Pruebas funcionales al 100 % | Se verifican en el pipeline, no en operación, y hoy en modo informativo mientras siga abierto el issue #86 (apartado 2.3) |

Una diferencia de medición conviene señalarla: el SLI de k6 (`http_req_failed`) cuenta
cualquier respuesta inesperada, mientras que la alerta cuenta solo respuestas 5xx. En
operación, un 4xx suele ser un error del cliente (sesión vencida, permiso denegado), no una
falla del servicio.

## 4.4 El aviso temprano de 500 ms

La Unidad 2 señaló que el acuerdo de 5 s es holgado: el p95 medido fue de 75.92 ms
(`docs/entrega-u2/03-niveles-de-servicio.md`), unas sesenta y seis veces por debajo. Con ese
margen, una degradación grave pasaría desapercibida. Por eso se agregó `LatenciaP95Degradada`:
no representa un incumplimiento del acuerdo, avisa de que algo cambió.

La Unidad 2 había propuesto 300 ms como umbral de vigilancia. El plan de esta unidad fijó la
regla en 500 ms sostenidos durante 10 minutos. Es una decisión técnica del plan con dos
propiedades: queda mucho más cerca del p95 real de 76 ms que el acuerdo de 5 s, y coincide con
uno de los límites de los buckets del histograma (`0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10`),
mientras que 300 ms caería dentro del bucket de 250 a 500 ms, donde `histogram_quantile`
interpola linealmente. Los 10 minutos de `for` exigen que la degradación se sostenga: un ruido
transitorio, por ejemplo una ráfaga breve de inicios de sesión, no produce una falsa alarma.

## 4.5 Las alertas están probadas

Las seis reglas tienen pruebas unitarias en `infra/monitoreo/prometheus/alertas.test.yml`,
ejecutadas con `promtool test rules` dentro de la imagen fijada de Prometheus. Además de
comprobar que cada alerta dispara, hay casos negativos que discriminan el umbral y la ventana:

| Caso negativo | Qué demuestra |
|---|---|
| Sonda recién caída, evaluada en el mismo minuto en que falla | `AplicacionCaida` respeta su `for` de 1 min |
| Todas las peticiones por debajo de 500 ms | `LatenciaP95Degradada` no dispara bajo su umbral |
| p95 entre 2.5 y 5 s | Dispara el aviso temprano pero **no** `LatenciaP95FueraDeAcuerdo` |
| 0.5 % de respuestas 5xx | `TasaErrores5xxAlta` no dispara por debajo del 1 % |
| MySQL recién caído, evaluado en el mismo minuto | `BaseDeDatosCaida` respeta su `for` de 1 min |
| Jobs fallidos durante 5 min | `JobsFallidos` no dispara antes de sus 10 min |

En total son 13 evaluaciones: 7 esperan la alerta y 6 esperan que no dispare. Se ejecutan con:

`docker run --rm --entrypoint promtool -v <copia-de-infra/monitoreo/prometheus>:/w prom/prometheus:v3.14.0 test rules /w/alertas.test.yml`

Resultado: [[PENDIENTE: resultado de promtool test rules, de evidencia/promtool-alertas.txt]].

## 4.6 Tiempo de notificación

La especificación fija que, ante una caída provocada, la notificación debe llegar en menos de
3 minutos. La cota teórica sale de la configuración: hasta 15 s para el scrape que detecta la
caída, hasta 15 s para la evaluación que registra la alerta como pendiente, 1 min de `for`,
hasta 15 s para la evaluación que la dispara y 30 s de `group_wait` en Alertmanager, es decir,
alrededor de 2 min 15 s en el peor caso.

| Medición | Valor |
|---|---|
| Hora de inicio de la caída | [[PENDIENTE: hora "inicio caida", de evidencia/monitoreo-alerta-tiempos.txt]] |
| Hora de llegada del correo a Mailpit | [[PENDIENTE: hora "correo recibido", de evidencia/monitoreo-alerta-tiempos.txt]] |
| Tiempo transcurrido | [[PENDIENTE: diferencia entre ambas horas, de evidencia/monitoreo-alerta-tiempos.txt]] |
