# 3. Niveles de servicio acordados

## 3.1 Los tres términos, sin confundirlos

| Término | Qué es | Ejemplo en MedSchedule |
|---|---|---|
| **SLI** (indicador) | La medición cruda | `http_req_duration` que reporta k6 |
| **SLO** (objetivo) | El valor que el equipo se compromete a cumplir | p95 por debajo de 5 s |
| **SLA** (acuerdo) | La consecuencia de incumplir el objetivo | El pipeline detiene la liberación |

En un producto comercial el SLA conlleva penalizaciones económicas. Aquí la
consecuencia es técnica y automática, que para un equipo de desarrollo es más útil:
**no se libera lo que no cumple**.

## 3.2 Acuerdos vigentes

| # | Indicador (SLI) | Objetivo (SLO) | Dónde se mide | Consecuencia si se incumple |
|---|---|---|---|---|
| 1 | `http_req_duration` p95 | < 5000 ms | Umbral de k6 en `tests/carga/jri-prueba.js` | k6 sale con código 99 y el job de pruebas falla |
| 2 | `http_req_failed` | < 1 % | Umbral de k6 | El job de pruebas falla |
| 3 | `duracion_panel` p95 | < 5000 ms | Umbral de k6 sobre `/patient/dashboard/data` | El job de pruebas falla |
| 4 | `tasa_login_exitoso` | > 99 % | Umbral de k6 | El job de pruebas falla |
| 5 | Disponibilidad tras desplegar | 100 % de las verificaciones de salud | `scripts/despliegue.sh` | El despliegue se marca fallido |
| 6 | Pruebas funcionales | 100 % en verde | `php artisan test` dentro de `scripts/pruebas-liberacion.sh` | El job de pruebas falla |

## 3.3 De dónde sale el objetivo de 5 segundos

El equipo fija el umbral de p95 por debajo de 5 s como compromiso de servicio. Vale la pena
señalar que es **holgado** para una aplicación de este tamaño: la medición real de
MedSchedule en el entorno de liberación es de 76 ms de p95. Un umbral holgado tiene una virtud
y un riesgo:

- **Virtud:** no produce falsos positivos. Cuando salta, algo va mal de verdad.
- **Riesgo:** una degradación del 400 % pasaría desapercibida.

Por eso el apartado 4 propone, además del umbral contractual, un umbral de vigilancia
mucho más ceñido a la medición real.

## 3.4 Por qué el percentil 95 y no la media

La media esconde exactamente lo que interesa. En la corrida de referencia:

| Estadístico de `http_req_duration` | Valor |
|---|---|
| Media | 33.36 ms |
| Mediana | 20.19 ms |
| p90 | 53.21 ms |
| p95 | 75.92 ms |
| p99 | 277.40 ms |
| Máximo | 696.91 ms |

La media dice 33 ms; una de cada cien peticiones tarda más de 277 ms, y la peor supera
los 696 ms, veinte veces la media. Un acuerdo escrito sobre la media declararía sano un
sistema en el que una parte real de los usuarios espera mucho más que lo anunciado.

## 3.5 Ventana de medición

| Parámetro | Valor | Por qué |
|---|---|---|
| Duración de la prueba | 2 minutos | 30 s de rampa, 1 min sostenido, 30 s de bajada |
| Usuarios virtuales | 10 | Suficientes para provocar concurrencia real sin saturar el entorno |
| Pausa entre iteraciones | 1 s | Modela a un usuario que lee la pantalla, no a un bucle cerrado |
| Frecuencia | En cada push y cada PR contra `main` y `develop` | Lo define `.github/workflows/release.yml` |

La rampa importa: medir desde el primer segundo incluiría el arranque en frío del
framework, que no representa el uso real.

## 3.6 Lo que estos acuerdos todavía no cubren

Honestidad sobre el alcance: los seis acuerdos de arriba se verifican **en el momento
de liberar**, no de forma continua sobre un sistema en operación. No hay todavía
recolección permanente de métricas, ni alertas, ni histórico. Ese es el objetivo de la
siguiente fase, esbozada en el apartado 4.6.
