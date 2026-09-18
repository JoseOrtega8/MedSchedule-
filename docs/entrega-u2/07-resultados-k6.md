# 7. Resultados de las pruebas de carga

## 7.1 Qué se ejecutó

| Dato | Valor |
|---|---|
| Script | `tests/carga/jri-prueba.js` |
| Autor | `ramonibr` |
| Herramienta | k6 v2.2.0 |
| Usuarios virtuales | 10 (el requisito pide más de 5) |
| Perfil | 30 s de rampa, 60 s sostenidos, 30 s de bajada |
| Endpoints | `/`, `/about`, `GET /login`, `POST /login`, `/patient/dashboard/data` |
| Métricas propias | 9, además de las de k6 |

## 7.2 Las tres corridas que hicieron falta

El plan del apartado 6.7 anticipaba tres riesgos. **Los tres se materializaron**, y cada
uno obligó a rehacer la prueba. El recorrido importa más que el número final: es la
diferencia entre medir la aplicación y medir un artefacto del método.

### Corrida 1 — la prueba medía el limitador de intentos

Resultado: `tasa_login_exitoso` = **18.51 %**, exactamente 5 autenticaciones correctas
de 27 intentos.

El número 5 no era casualidad. `routes/auth.php:24` aplica:

```php
Route::post('login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('throttle:5,1');
```

Cinco peticiones por minuto **y por IP**, con independencia del usuario. Es más
restrictivo que el limitador interno de Laravel Breeze, que cuenta por combinación de
correo e IP.

Con todos los usuarios virtuales compartiendo `patient@test.com`, la prueba estaba
midiendo la respuesta del limitador, no la de la aplicación.

**Corrección:** `database/seeders/CargaSeeder.php`, que siembra diez cuentas, una por
usuario virtual.

### Corrida 2 — seguían siendo exactamente 5

Resultado: `tasa_login_exitoso` = **8.06 %**, otra vez 5 de 62.

Diez cuentas distintas y el mismo tope. Eso descartó el limitador por correo y confirmó
que el tope es por IP: desde un solo origen, la aplicación admite cinco autenticaciones
por minuto y ni una más.

**Corrección:** autenticarse **una sola vez por usuario virtual** y navegar después con
la sesión viva, que además es lo que hace un usuario real. Más reintentos con espera
para los usuarios virtuales que encuentren el limitador durante la rampa.

### Corrida 3 — el check de JSON delataba una sesión perdida

Resultado: `panel responde 200` pasaba, pero `panel devuelve JSON` fallaba **135 de 145
veces**. Los 10 aciertos eran exactamente la primera iteración de cada usuario virtual.

La contradicción tenía una sola explicación posible: **k6 vacía el almacén de cookies al
terminar cada iteración**. La sesión de Laravel se perdía, el panel redirigía al
formulario de login, k6 seguía la redirección y devolvía un 200 con HTML. Un `check` de
código de estado nunca lo habría detectado; el de tipo de contenido sí.

**Corrección:** `noCookiesReset: true` en las opciones del script.

Vale la pena subrayarlo: sin esa opción, la prueba habría reportado métricas verdes
midiendo el formulario de login en lugar del panel. Una prueba de carga que mide lo que
no cree medir es peor que no tenerla.

## 7.3 El defecto que encontró la prueba: `/about` responde 500

Durante la preparación, la comprobación más simple posible falló:

```bash
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8010/about   # 500
```

`/about` es una ruta pública (`routes/web.php:43`, sin middleware `auth`), pero su
layout incluye `resources/views/components/topbar.blade.php`, cuya línea 9 hace:

```php
'avatarText' => strtoupper(substr(Auth::user()->name,0,1) . substr(Auth::user()->last_name,0,1)),
```

`Auth::user()` devuelve `null` sin sesión. Error registrado:

```
local.ERROR: Attempt to read property "name" on null
(View: resources/views/components/topbar.blade.php)
```

### La métrica que lo prueba sin lugar a dudas

La métrica `tasa_about_correcto` da la evidencia más limpia del diagnóstico:

| Corrida | `tasa_about_correcto` | Estado de los usuarios virtuales |
|---|---|---|
| Sin sesión persistente | **0.00 %** (0 de 62) | Anónimos en todas las iteraciones |
| Con sesión persistente | **92.30 %** (120 de 130) | Autenticados tras la primera iteración |

Las diez peticiones fallidas de la segunda corrida son exactamente las diez primeras
iteraciones, una por usuario virtual, anteriores al login. **La ruta solo falla para
visitantes anónimos**, que son justo los que llegan a una página pública.

### Impacto bajo carga

| Métrica | Con `/about` roto | Con `/about` correcto |
|---|---|---|
| `duracion_about` p95 | 1.66 s | 736 ms |
| `data_received` en 60 s | 139 MB | 12 MB |

La página de error de depuración pesa unos 945 KB frente a los pocos KB de la vista
correcta. Un error no controlado no solo falla: multiplica por doce el tráfico.

Reportado como [issue #97](https://github.com/JoseOrtega8/MedSchedule-/issues/97), sin
asignar, porque la vista es trabajo de otro integrante y no había issue que la cubriera.

## 7.4 Resultados de la corrida válida

Entorno: máquina local del autor, `php artisan serve`, SQLite. 10 usuarios virtuales,
60 s. Salida cruda en `evidencia/`.

### Umbrales

| Umbral | Objetivo | Medido | Resultado |
|---|---|---|---|
| `http_req_duration` p95 | < 5000 ms | **625.17 ms** | Cumple, 8 veces por debajo |
| `http_req_failed` | < 1 % | **0.00 %** | Cumple |
| `tasa_login_exitoso` | > 99 % | **100.00 %** | Cumple |
| `duracion_panel` p95 | < 5000 ms | **59.47 ms** | Cumple, 84 veces por debajo |

### Latencia por endpoint

| Endpoint | avg | mediana | p90 | p95 | p99 | máx |
|---|---|---|---|---|---|---|
| Portada `/` | 36.63 ms | 37.42 ms | 47.41 ms | 50.68 ms | 58.39 ms | 63.26 ms |
| `/about` | 122.12 ms | 35.50 ms | 58.17 ms | 736.22 ms | 1.80 s | 2.06 s |
| `GET /login` | 274.63 ms | 41.57 ms | 1.00 s | 1.43 s | 1.77 s | 1.86 s |
| `POST /login` | 183.51 ms | 41.26 ms | 743.03 ms | 826.73 ms | 1.01 s | 1.01 s |
| `/patient/dashboard/data` | 45.97 ms | 28.06 ms | 39.98 ms | 59.47 ms | 579.50 ms | 821.83 ms |

### Agregados

| Métrica | Valor |
|---|---|
| `http_req_duration` avg / mediana / p90 / p95 / p99 | 98.76 ms / 34.28 ms / 61.01 ms / 625.17 ms / 1.52 s |
| `http_req_failed` | 0.00 % (0 de 476) |
| `http_reqs` | 476 (5.29 por segundo) |
| `iterations` | 125 (1.39 por segundo) |
| `iteration_duration` avg / p95 | 2.43 s / 1.17 s |
| `vus_max` | 10 |
| `data_received` / `data_sent` | 12 MB / 381 kB |
| `respuestas_limitadas` | 40 |
| `tasa_about_correcto` | 92.30 % |
| `tasa_login_exitoso` | 100.00 % (10 de 10) |

## 7.5 Qué dicen estos números

1. **El cuello de botella es `bcrypt`, no la base de datos.** El panel, que ejecuta
   tres consultas por petición, responde en 59 ms de p95. Autenticar cuesta 827 ms, es
   decir, catorce veces más. `BCRYPT_ROUNDS=12` está haciendo exactamente lo que debe:
   ser caro a propósito. Conviene saberlo antes de optimizar consultas que van bien.
2. **La cola la marca el login, no el contenido.** El p99 de 1.52 s corresponde a las
   autenticaciones durante la rampa, cuando diez usuarios virtuales compiten por CPU.
3. **El limitador actuó 40 veces en 60 segundos.** Con un patrón de acceso legítimo.
   En producción, detrás de un proxy o una red institucional donde muchos usuarios
   comparten IP, `throttle:5,1` puede bloquear a gente que no ha hecho nada malo. No es
   un defecto de la prueba: es una decisión de diseño que conviene revisar.
4. **El margen sobre el acuerdo es enorme.** 625 ms contra 5 s. Por eso el apartado 4.2
   propone un umbral de vigilancia de 1.5 s: el contractual nunca saltaría a tiempo.

### Hallazgo adicional sobre las herramientas del repositorio

Al preparar el hook de pre-commit se comprobó que:

- **ESLint no tiene archivo de configuración ni figura en `devDependencies`.** `npx`
  lo descarga y falla por ausencia de configuración. El paso correspondiente de
  `ci.yml` termina con `|| true`, así que **nunca ha analizado nada**.
- **Prettier falla en 13 archivos** anteriores a esta unidad, por la misma razón.

Por eso tanto el hook como el pipeline de esta entrega exigen formato **solo sobre los
archivos que toca cada cambio**: impide añadir deuda nueva sin bloquear a nadie con la
vieja.

## 7.6 Pull request de ejecución

Los resultados de este apartado se commitean en markdown junto con la evidencia cruda,
como pide el punto 3 del enunciado. El pull request de ejecución de las pruebas queda
enlazado en el apartado 10 del índice una vez abierto.
