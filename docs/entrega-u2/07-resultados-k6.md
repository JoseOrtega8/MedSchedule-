# 7. Resultados de las pruebas de carga

## 7.1 Qué se ejecutó

| Dato | Valor |
|---|---|
| Script | `tests/carga/jri-prueba.js` |
| Autor | `ramonibr` |
| Herramienta | k6 v2.2.0 |
| Entorno oficial | Codespace de 2 núcleos, MySQL 8.0.46, Xdebug apagado |
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

## 7.4 Resultados de la corrida oficial

Entorno: **Codespace de 2 núcleos y 8 GB**, generado desde `.devcontainer/`, con MySQL
8.0.46 y Xdebug apagado. 10 usuarios virtuales, 2 minutos. Evidencia cruda en
`evidencia/k6-jri-codespace-resumen.txt` y `k6-jri-codespace-resumen.json`.

### Umbrales

| Umbral | Objetivo | Medido | Resultado |
|---|---|---|---|
| `http_req_duration` p95 | < 5000 ms | **75.92 ms** | Cumple, 66 veces por debajo |
| `http_req_failed` | < 1 % | **0.00 %** (0 de 1981) | Cumple |
| `tasa_login_exitoso` | > 99 % | **100.00 %** (10 de 10) | Cumple |
| `duracion_panel` p95 | < 5000 ms | **67.51 ms** | Cumple, 74 veces por debajo |

**Aserciones funcionales: 2586 de 2586 correctas (100 %).**

### Latencia por endpoint

| Endpoint | avg | mediana | p90 | p95 | p99 | máx |
|---|---|---|---|---|---|---|
| Portada `/` | 30.76 ms | 19.01 ms | 51.71 ms | 90.17 ms | 194.63 ms | 649.44 ms |
| `/about` | 36.32 ms | 18.39 ms | 53.53 ms | 75.54 ms | 622.76 ms | 696.91 ms |
| `GET /login` | 24.11 ms | 17.75 ms | 41.92 ms | 47.59 ms | 57.19 ms | 59.77 ms |
| `POST /login` | 136.93 ms | 49.68 ms | 289.91 ms | 290.89 ms | 331.51 ms | 343.61 ms |
| `/patient/dashboard/data` | 29.51 ms | 22.43 ms | 51.22 ms | 67.51 ms | 100.90 ms | 314.28 ms |

### Agregados

| Métrica | Valor |
|---|---|
| `http_req_duration` avg / mediana / p90 / p95 / p99 | 33.36 ms / 20.19 ms / 53.21 ms / 75.92 ms / 277.40 ms |
| `http_req_failed` | 0.00 % (0 de 1981) |
| `http_reqs` | 1981 (16.48 por segundo) |
| `iterations` | 644 (5.36 por segundo) |
| `iteration_duration` avg / p95 | 1.43 s / 1.29 s |
| `vus_max` | 10 |
| `data_received` / `data_sent` | 22 MB / 1.6 MB |
| `respuestas_limitadas` | 14 |
| `tasa_about_correcto` | 98.44 % (634 de 644) |
| `checks` | 100.00 % (2586 de 2586) |

### Comparación con la corrida local

La misma prueba, mismo script, dos entornos:

| Métrica | Local (macOS, SQLite, `artisan serve`) | Codespace (2 núcleos, MySQL 8, Xdebug apagado) |
|---|---|---|
| `http_req_duration` p95 | 625.17 ms | **75.92 ms** |
| `duracion_panel` p95 | 59.47 ms | 67.51 ms |
| `duracion_login_post` p95 | 826.73 ms | 290.89 ms |
| Peticiones totales | 476 | **1981** |
| Iteraciones | 125 | **644** |
| Rendimiento | 5.29 req/s | **16.48 req/s** |

El entorno declarado rinde tres veces más que la máquina de desarrollo. La diferencia no
es del hardware: es que `php artisan serve` en local competía con el resto del escritorio
y, sobre todo, que la rampa local se pasó esperando al limitador de intentos. El dato que
importa para la unidad es que **la medición es reproducible**: cualquier integrante que
abra un Codespace desde esta rama obtiene este mismo entorno.

### Evidencia visual

El propio k6 genera un informe HTML con la evolución de cada métrica en el tiempo,
activable con `K6_WEB_DASHBOARD=true` y exportable con `K6_WEB_DASHBOARD_EXPORT`:

```bash
K6_WEB_DASHBOARD=true K6_WEB_DASHBOARD_EXPORT=informe.html \
    K6_PASSWORD='<contrasena>' k6 run --env URL_BASE=http://127.0.0.1:8000 \
    tests/carga/jri-prueba.js
```

| Archivo | Contenido |
|---|---|
| `evidencia/k6-informe-codespace.html` | Informe interactivo con todas las gráficas |
| `evidencia/k6-01-informe-general.png` | Captura del informe completo |
| `evidencia/k6-jri-codespace-resumen.txt` | Salida de la terminal |
| `evidencia/k6-jri-codespace-resumen.json` | Resumen agregado en JSON |

Se prefiere el informe de la herramienta a una fotografía de la terminal: muestra la
evolución de la latencia durante la rampa, que un resumen agregado no puede mostrar. En
las gráficas se ve con claridad que el pico de latencia está al principio, cuando el
framework aún no tiene nada cacheado, y que a partir del segundo 20 la curva se aplana.

Las cifras del informe corresponden a una segunda corrida equivalente a la oficial: 650
iteraciones, p95 de 71 ms, 100 % de aserciones correctas, 16.53 peticiones por segundo.
La repetición confirma que la medición es estable, no un resultado afortunado.

### 7.4.1 El defecto que solo apareció en el runner

La prueba pasó en el Codespace y **falló en GitHub Actions**, con dos síntomas a la vez:

```
tasa_login_exitoso ... 65.21% (15 de 23)   ✗ umbral rate>0.99
panel devuelve JSON .. 0%     (0 de 15)
```

La causa no estaba en la prueba. `phpunit.xml` declara:

```xml
<env name="DB_DATABASE" value="medschedule_test"/>
```

**sin `force="true"`**, y PHPUnit no sobrescribe una variable que ya exista en el
entorno. El workflow exporta `DB_DATABASE=medschedule` para la aplicación, así que la
suite corría contra la base de datos de la aplicación y, con `RefreshDatabase`, se
llevaba por delante los datos que la prueba de carga necesitaba justo después.

El síntoma que lo delató es contraintuitivo: **PHPUnit pasaba más pruebas de lo normal**,
64 de 79 frente a las 7 del Codespace, porque encontraba datos reales donde debía haber
una base vacía. Un fallo que se manifiesta como «las pruebas van mejor» es de los más
difíciles de ver.

| Entorno | `DB_DATABASE` en el entorno | Base que usaba PHPUnit | PHPUnit | k6 |
|---|---|---|---|---|
| Codespace | sin definir | `medschedule_test` | 72 fallos | Correcto |
| Runner | `medschedule` | **`medschedule`** | 15 fallos | **Falla** |

Corregido en `scripts/pruebas-liberacion.sh`, pasando la base de pruebas de forma
explícita y resembrando antes de medir:

```bash
base_pruebas="${DB_DATABASE_PRUEBAS:-medschedule_test}"
DB_DATABASE="${base_pruebas}" php artisan test
```

**No se modificó `phpunit.xml`**: es un archivo del equipo y el arreglo de fondo
—añadirle `force="true"`— corresponde a quien mantenga la suite. Conviene avisarlo,
porque el problema no es solo del pipeline: cualquier integrante que tenga `DB_DATABASE`
exportada en su shell y ejecute `php artisan test` en local **borra su propia base de
desarrollo**.

### 7.4.2 Ejecución en el pipeline

La misma prueba, ejecutada por GitHub Actions sin intervención manual, sobre el servicio
MySQL del runner:

| Métrica | Valor |
|---|---|
| Aserciones correctas | **2446 de 2446 (100 %)** |
| `http_req_duration` p95 | **114.27 ms** |
| `http_req_duration` mediana | 39.99 ms |
| `http_req_failed` | 0.00 % (0 de 1876) |
| `tasa_login_exitoso` | 100.00 % (10 de 10) |

Tres entornos distintos —máquina local, Codespace y runner de GitHub— con la misma
prueba y el mismo veredicto: el sistema cumple el acuerdo de servicio con holgura.

### Estado de las dos compuertas

```
==================== RESUMEN DE LAS COMPUERTAS ====================
  Pruebas funcionales (PHPUnit) ....... FALLO (codigo 2)
  Prueba de carga (k6) ................ CORRECTO
===================================================================
```

La suite de PHPUnit falla por deuda anterior a esta unidad: 72 de 79 métodos terminan en
`Expected response status code [422] but received 401`, es decir, sin sesión autenticada.
Es el mismo problema que el issue #86 documenta y que hoy tapa el `|| true` del `ci.yml`
heredado. **No se arregla aquí**: está asignado a otro integrante y queda fuera del
alcance de esta entrega.

Lo que sí se corrigió es el diseño del script de pruebas. Con `set -e`, ese fallo abortaba
todo antes de que k6 llegara a ejecutarse, de modo que la deuda funcional dejaba a la
unidad sin medición. La salida fácil habría sido añadir `|| true` a PHPUnit —el mismo
vicio que este documento critica—. En su lugar, las dos compuertas se ejecutan siempre, se
informan por separado y el script sale con error si cualquiera falla.

## 7.5 Qué dicen estos números

1. **El cuello de botella es `bcrypt`, no la base de datos.** El panel, que ejecuta tres
   consultas por petición, responde en 67 ms de p95. Autenticar cuesta 291 ms, más de
   cuatro veces más, y es el único endpoint que se acerca a los 300 ms. `BCRYPT_ROUNDS=12`
   está haciendo exactamente lo que debe: ser caro a propósito. Conviene saberlo antes de
   optimizar consultas que van bien.
2. **La cola la marca el arranque, no el contenido.** Los máximos de 649 ms en la portada
   y 696 ms en `/about` corresponden a las primeras peticiones, cuando el framework aún no
   tiene nada cacheado. A partir de ahí la mediana se estabiliza en torno a los 19 ms.
3. **El limitador actuó 14 veces en dos minutos.** Con un patrón de acceso legítimo.
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
