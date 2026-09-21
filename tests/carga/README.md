# Pruebas de carga con k6

Pruebas de rendimiento de MedSchedule. Cada integrante del equipo aporta su
propio script con la nomenclatura `iniciales-prueba.js`; este directorio contiene
el del autor de esta entrega.

| Script | Autor | Endpoints que recorre |
|---|---|---|
| `jri-prueba.js` | `ramonibr` | `/`, `/about`, `GET /login`, `POST /login`, `/patient/dashboard/data` |

## Requisitos previos

1. **k6 instalado.** `bash scripts/instalar-k6.sh` (Linux con apt o macOS con Homebrew).
2. **Entorno levantado.** `bash scripts/entorno-liberacion.sh`
3. **Cuentas de carga sembradas.** `php artisan db:seed --class=CargaSeeder --force`

`CargaSeeder` crea diez cuentas de paciente (`carga1@test.com` … `carga10@test.com`),
una por usuario virtual, cada una con perfil y ocho citas. Son cuentas desechables
de entornos de prueba.

## Cómo se ejecuta

La contraseña llega por variable de entorno; nunca se escribe en el repositorio.

```bash
# Prueba de humo: 1 usuario virtual, 10 segundos.
K6_PASSWORD='<contrasena de las cuentas de carga>' npm run carga:humo

# Prueba completa: rampa hasta 10 usuarios virtuales, 2 minutos.
K6_PASSWORD='<contrasena de las cuentas de carga>' npm run carga

# Prueba completa con exportación de métricas, que es como la corre el pipeline.
K6_PASSWORD='<contrasena de las cuentas de carga>' bash scripts/pruebas-liberacion.sh
```

Variables reconocidas por el script:

| Variable | Valor por defecto | Para qué sirve |
|---|---|---|
| `URL_BASE` | `http://127.0.0.1:8000` | Destino de la prueba |
| `K6_PASSWORD` | — (obligatoria) | Contraseña de las cuentas de carga |
| `K6_PREFIJO_USUARIO` | `carga` | Prefijo de los correos generados por usuario virtual |
| `K6_DOMINIO_USUARIO` | `test.com` | Dominio de esos correos |
| `K6_USUARIO` | — | Fuerza a que todos los usuarios virtuales compartan una cuenta. Sirve para reproducir a propósito el limitador de intentos |
| `K6_INTENTOS_LOGIN` | `6` | Reintentos de autenticación frente a un 429 |
| `K6_ESPERA_LIMITADOR` | `15` | Segundos de espera entre esos reintentos |

## Qué significa cada métrica

### Métricas propias de este script

| Métrica | Qué mide |
|---|---|
| `duracion_portada` | Latencia de `GET /` |
| `duracion_about` | Latencia de `GET /about` |
| `duracion_login_get` | Latencia del formulario de login, de donde se extrae el token CSRF |
| `duracion_login_post` | Latencia de la autenticación real, que incluye el coste de `bcrypt` |
| `duracion_panel` | Latencia de `GET /patient/dashboard/data`, la ruta con más consultas por petición |
| `tasa_login_exitoso` | Proporción de autenticaciones que terminaron en redirección 302 |
| `tasa_about_correcto` | Proporción de peticiones a `/about` que devolvieron 200 |
| `respuestas_limitadas` | Número de respuestas 429 del limitador de intentos de login |
| `errores_negocio` | Fallos que no son de red: token ausente, credenciales rechazadas |

### Métricas propias de k6

| Métrica | Qué mide |
|---|---|
| `http_req_duration` | Tiempo total de cada petición. **Es la métrica del nivel de servicio: su p95 debe quedar por debajo de 5 s** |
| `http_req_waiting` | Tiempo hasta el primer byte, es decir, lo que tarda el servidor en pensar |
| `http_req_connecting` | Tiempo de establecer la conexión TCP |
| `http_req_tls_handshaking` | Tiempo de negociación TLS, cero cuando se prueba sobre HTTP |
| `http_req_failed` | Proporción de peticiones con respuesta inesperada |
| `http_reqs` | Peticiones totales y su ritmo por segundo |
| `iterations` | Recorridos completos del guion de usuario |
| `iteration_duration` | Duración de cada recorrido, incluida la pausa de un segundo |
| `vus` / `vus_max` | Usuarios virtuales activos y máximo alcanzado |
| `data_received` / `data_sent` | Volumen de datos intercambiado |
| `checks` | Proporción de aserciones funcionales satisfechas |

## Umbrales configurados

```javascript
http_req_duration:  p(95) < 5000 ms   // nivel de servicio de la unidad
http_req_failed:    rate  < 1 %
tasa_login_exitoso: rate  > 99 %
duracion_panel:     p(95) < 5000 ms
```

k6 termina con código de salida 99 cuando un umbral se incumple. El pipeline usa
ese código como compuerta: si el p95 se sale del acuerdo, la liberación se detiene.

## Decisiones de diseño que conviene conocer

- **Una cuenta por usuario virtual.** `routes/auth.php` aplica `throttle:5,1` a
  `POST /login`: cinco peticiones por minuto **y por IP**. Con una sola cuenta
  compartida la prueba medía el limitador en lugar de la aplicación.
- **Autenticación una sola vez por usuario virtual.** Un usuario real inicia
  sesión y luego navega. Repetir el login en cada iteración es irreal y choca
  contra el mismo limitador.
- **`noCookiesReset: true`.** k6 vacía el almacén de cookies al terminar cada
  iteración. Sin esta opción la sesión de Laravel se perdía, el panel redirigía
  al formulario de login y la prueba medía otra cosa distinta de la que dice medir.
- **El 429 y el 500 de `/about` se declaran respuestas esperadas.** No para
  esconderlos, sino para que no distorsionen `http_req_failed`. Su frecuencia se
  sigue en `respuestas_limitadas` y `tasa_about_correcto`.
