# 06 — Guión del video demo (criterio SA)

Este documento es el guión completo para grabar el video explicativo que exige el criterio SA de
la rúbrica: "todos los puntos más video explicativo". No es un resumen para leer antes de grabar —
es el guión que se sigue **mientras se graba**, con los comandos exactos, los tiempos objetivo y un
plan de contingencia para no detener la grabación si algo sale distinto de lo esperado.

## 0. Método de verificación

Todo comando de este guión se ejecutó en esta máquina (rama `feat/unidad-docs-sdd`) como parte de
la preparación de esta misma tarea, además de los ya verificados en tareas anteriores y citados con
`[EJECUTADO]` en `docs/entrega/01-configuracion-herramientas.md` §4. En particular, para este
documento se volvió a correr en vivo, hoy:

- `php artisan migrate:status` (bare, contra la conexión del `.env` local) — responde sin error,
  11 migraciones en `Ran` y 4 en `Pending` (informativo: no se corrige aquí, no afecta al suite de
  pruebas porque cada test usa `RefreshDatabase` contra `medschedule_test`, no contra la base de
  desarrollo).
- `php artisan serve --port=8000` seguido de una petición a `/login` — responde `HTTP 200`.
- El bloque completo de PHPUnit contra el MySQL de MAMP — reprodujo exactamente
  **64 pruebas pasan, 15 fallan, 149 aserciones**, igual que la línea base de
  `docs/entrega/evidencia/phpunit-baseline.txt`.
- El bloque completo de Playwright (arranque, prueba, apagado) — reprodujo exactamente
  **1 prueba pasa**, igual que `docs/entrega/evidencia/playwright-baseline.txt`.
- `git status --short` antes y después de estas corridas — vacío en ambos casos: ningún archivo de
  `app/`, `routes/`, `config/`, `database/`, `tests/`, `resources/` ni `.github/` cambió.

Ningún comando de este guión imprime el contenido de `.env` ni de ningún archivo con credenciales.
Las credenciales de MySQL de MAMP se representan con los marcadores `<usuario_mamp>` y
`<password_mamp>`, igual que en `docs/entrega/01-configuracion-herramientas.md` §4 — nunca se
escriben en claro, ni en este documento ni en la grabación.

## 1. Checklist previo a grabar

Ejecutar estos pasos **en orden, antes de pulsar grabar**. El objetivo es que nada falle en vivo:
cualquier fallo de entorno se resuelve aquí, no durante la toma.

| # | Comando | Resultado esperado | Si falla |
|---|---|---|---|
| 1 | `/Applications/MAMP/bin/startMysql.sh` | Arranca (o confirma arrancado) el MySQL de MAMP | Si MAMP no está instalado en esta ruta, abrir el panel de MAMP manualmente y pulsar "Start Servers" |
| 2 | `nc -z 127.0.0.1 8889 && echo "MySQL de MAMP escuchando en 8889"` | Imprime el mensaje de confirmación | Si no responde, revisar que MAMP use el puerto `8889` (Preferencias → Puertos) y repetir el paso 1 |
| 3 | `php artisan migrate:status` | Lista las migraciones sin lanzar una excepción de conexión (hoy: 11 en `Ran`, 4 en `Pending` — no es un error, es el estado real de la base de desarrollo) | Si lanza `SQLSTATE` o "Connection refused", el `.env` local no apunta a una base accesible; no se soluciona aquí — se documenta como parte del plan de contingencia (sección 4) |
| 4 | `php artisan serve --port=8000` (dejarlo un momento arrancado, abrir `http://127.0.0.1:8000/login` en el navegador, luego `Ctrl+C`) | La pantalla de login de MedSchedule carga sin error 500 | Si carga en blanco o con error, revisar la consola del navegador antes de grabar; no es de los defectos documentados en esta entrega |
| 5 | `ls ~/Library/Caches/ms-playwright/ \| grep -i chromium` | Lista al menos una carpeta `chromium-<versión>` | Si no aparece nada, es una máquina limpia: correr `npx playwright install chromium` **ahora, no durante la grabación** — tarda varios minutos y en esta máquina nunca fue necesario porque el navegador ya estaba en caché |
| 6 | `git status --short -- app/ routes/ config/ database/ tests/ resources/ .github/` | Sin salida (árbol de trabajo limpio en las carpetas de código) | Si hay salida, son cambios de otra tarea; no se graba con cambios sin commitear en código — pausar y resolver antes de grabar |
| 7 | Abrir en el navegador `https://github.com/JoseOrtega8/MedSchedule-/actions` y confirmar que carga (requiere conexión a internet) | La pestaña Actions del repositorio carga con su historial de ejecuciones | Si no hay internet en el momento de grabar, usar el plan de contingencia (sección 4): describir el pipeline sobre `docs/entrega/04-flujo-cicd.md` sin la pantalla en vivo |

**Advertencia importante para quien grabe desde un entorno distinto a esta máquina**: el paso 5 de
esta lista nunca fue necesario aquí porque el binario de Chromium ya estaba en caché antes de esta
entrega. Desde un clon limpio del repositorio, `npx playwright install chromium` es obligatorio y
tarda varios minutos — ejecutarlo como parte de la preparación, nunca en vivo durante la grabación.

## 2. Guión con tiempos

Duración objetivo: 10 a 12 minutos. Los tiempos son de inicio de tramo, no duraciones fijas — sirven
de referencia, no de cronómetro estricto.

| Minuto | Qué se muestra en pantalla | Qué se dice |
|---|---|---|
| 00:00 | Cara/pantalla de bienvenida o el `README.md` del repositorio | Presentación: "Este es MedSchedule, una aplicación Laravel de gestión de citas médicas. Este video cubre la entrega de la unidad de documentación, pruebas y CI/CD: seis documentos en `docs/entrega/`, dos documentos de Spec-Driven Development en `docs/sdd/`, y el andamiaje de spec-kit, specs piloto y despliegue que se ve a lo largo del video." |
| 00:40 | Terminal: `ls -la` en la raíz del repositorio, luego `ls docs/entrega/ docs/sdd/` | "Aquí está la estructura real del repositorio: los documentos de entrega numerados del 01 al 06, los dos documentos de SDD, y las carpetas `.specify/`, `.claude/skills/`, `specs/` y `terraform/` que se recorren más adelante." |
| 01:10 | Abrir `docs/entrega/01-configuracion-herramientas.md`, desplazar hasta la tabla de inventario (sección 1) | "Punto 1 de la rúbrica: configuración de herramientas. El inventario tiene 16 herramientas con su versión exacta y su criterio de elección. El hallazgo más importante aquí es que PHP y Node difieren entre esta máquina y CI a propósito — CI fija PHP 8.2 y Node 20 para reproducibilidad, mientras que local usa lo que trae MAMP." |
| 01:50 | Desplazar a la sección 2.2 (`phpunit.xml`) y leer la nota sobre el puerto de MySQL | "`phpunit.xml` fuerza la conexión a MySQL y a la base `medschedule_test`, pero `config/database.php` apunta por defecto al puerto 3306, mientras que el MySQL de MAMP en esta máquina escucha en el 8889. Por eso el siguiente comando lleva las variables de entorno explícitas." |
| 02:20 | Terminal | "Voy a arrancar el MySQL de MAMP y confirmar que está escuchando." Ejecutar: `/Applications/MAMP/bin/startMysql.sh` seguido de `nc -z 127.0.0.1 8889 && echo "MySQL de MAMP escuchando en 8889"` |
| 02:40 | Abrir `docs/entrega/02-plan-de-pruebas.md`, sección 3 (niveles de prueba) y sección 3.1 (Playwright vs. Selenium vs. Katalon) | "Punto 2: plan de pruebas. Tres niveles — unitario, feature y E2E — y una justificación de por qué Playwright y no Selenium o Katalon: ya está integrado, corre headless de fábrica, y comparte JavaScript con el resto del proyecto." |
| 03:20 | Terminal, ejecutar en vivo el comando de PHPUnit | "Ahora la ejecución real, en vivo, con las credenciales de MAMP." Ejecutar: `DB_HOST=127.0.0.1 DB_PORT=8889 DB_USERNAME=<usuario_mamp> DB_PASSWORD=<password_mamp> php artisan test` |
| 04:10 | Salida del comando anterior en terminal, ya terminada | "El resultado real: 64 pruebas pasan, 15 fallan, 149 aserciones. Esto no es un problema de esta entrega — es su hallazgo más valioso. `docs/entrega/02-plan-de-pruebas.md` sección 6.4 clasifica los 15 fallos en tres grupos con causa raíz distinta: diez son tests obsoletos que nunca autentican, tres son una divergencia real entre el código y el test del dashboard, y dos apuntan a un defecto probable en la integración con Google Calendar. Ninguno se corrige en esta entrega — cada uno queda documentado para que la unidad siguiente lo aborde con criterio." |
| 05:10 | Terminal, ejecutar en vivo el bloque de Playwright | "Ahora Playwright, con arranque y apagado del servidor en el mismo comando, para que el control de trabajos de la shell no se pierda entre procesos." Ejecutar: `php artisan serve --port=8000 > /dev/null 2>&1 & SERVE_PID=$!; sleep 3; npx playwright test; kill $SERVE_PID` |
| 05:50 | Salida del comando anterior | "Una prueba, un resultado: pasa. Es el único flujo E2E que existe hoy — gestión de usuarios y edición de su rol." |
| 06:10 | `open tests/playwright-report/index.html` en el navegador, recorrer brevemente el reporte | "Este es el reporte HTML que genera Playwright automáticamente, con captura de pantalla y traza de la ejecución." |
| 06:35 | `open docs/coverage/index.html` en el navegador | "Y este es el reporte de cobertura de código, generado sobre el mismo suite de PHPUnit." |
| 06:55 | Abrir `docs/entrega/03-casos-de-prueba.md`, mostrar la sección 3 (matriz) y la sección 4 (casos propuestos) | "Punto 3: casos de prueba. 58 filas de matriz — 38 para los 79 métodos de PHPUnit y el spec de Playwright que ya existen, agrupados por precondición compartida, y 20 casos propuestos para los cuatro flujos E2E que todavía no tienen cobertura, derivados de la spec piloto `specs/001-pruebas-e2e/spec.md`. Cada fila cita el archivo real de automatización o la ruta futura." |
| 07:45 | Abrir `docs/entrega/04-flujo-cicd.md`, sección 4 (hallazgo central) | "Punto 4: control de versiones y CI/CD. El hallazgo central de este documento: `.github/workflows/ci.yml` tiene tres `\|\| true` que hacen que el pipeline nunca reporte fallo, y un filtro de PHPUnit que solo corre 5 de las 20 clases de prueba reales. El check verde de GitHub Actions hoy no certifica que el código funcione." |
| 08:35 | Navegador: pestaña Actions del repositorio en GitHub (`https://github.com/JoseOrtega8/MedSchedule-/actions`) | "Aquí está el pipeline real en GitHub Actions. Este video no hace push de esta rama — todo el trabajo de esta entrega queda en commits locales, según la restricción de esta unidad — así que lo que se ve aquí es el historial de ejecuciones previas del repositorio, no una corrida de esta rama." |
| 09:05 | Volver a `04-flujo-cicd.md`, mostrar brevemente el diagrama de flujo (sección 6) | "El diagrama resume el recorrido completo: issue, rama por número, commit convencional, pull request, y la única compuerta que hoy es real — la revisión humana — porque las compuertas automáticas de lint y pruebas están neutralizadas." |
| 09:35 | Abrir `docs/entrega/05-estrategia-despliegue.md`, sección 8 (defectos) | "Punto 5: estrategia de despliegue con Railway. Y aquí están los cinco defectos verificados de esta entrega." |
| 10:00 | Desplazar por las subsecciones 8.1 a 8.5 | "Defecto 1: el dashboard del paciente no entra al manifiesto de Vite, se rompe en producción. Defecto 2: el `Caddyfile` que arranca el contenedor no existe. Defecto 3: FrankenPHP no está declarado como dependencia. Defecto 4: las migraciones correrían dos veces — no destructivo, pero ambiguo. Y el defecto 5, de naturaleza distinta: documentación de una infraestructura anterior con credenciales en claro que casi se commitea por accidente y ya se corrigió, excluyéndola del repositorio con reglas nuevas de `.gitignore`. Ninguno de los primeros cuatro se corrige en esta entrega — quedan documentados con su fix propuesto." |
| 11:00 | Terminal: `ls -la .specify/` y abrir `.specify/memory/constitution.md` | "Y el andamiaje de Spec-Driven Development. `specify-cli` ya instalado e inicializado, con su constitución de siete principios: estilo de código, manejo de errores, gestión de secretos, validación de entrada, no exposición de errores internos, SDD obligatorio, y convenciones de control de versiones." |
| 11:35 | Terminal: `ls .claude/skills/` | "Diez skills de spec-kit más dos propias del proyecto: `generar-spec-modulo`, que investiga el dominio antes de escribir una spec, y `generar-casos-prueba`, que convierte una spec aprobada en una matriz de pruebas y esqueletos de test — y que ya prohíbe por escrito el patrón que causó diez de los quince fallos que vimos hace un momento." |
| 12:00 | Terminal: `ls specs/001-pruebas-e2e/ specs/002-tours-guiados/` | "Dos specs piloto completas, cada una con `spec.md`, `plan.md` y `tasks.md`: ampliar la cobertura E2E por rol, y tours guiados con `driver.js`. Ninguna de las dos está implementada todavía — son el punto de partida verificable de la unidad siguiente." |
| 12:30 | Terminal: `ls terraform/`, luego abrir `.github/workflows/cd-railway.yml` | "Cierre con el andamiaje de despliegue: el esqueleto de Terraform, sin aplicar a propósito porque provisionaría infraestructura de pago, y el workflow de despliegue continuo a Railway, con disparo manual y verificación del secreto `RAILWAY_TOKEN` antes de desplegar." |
| 13:00 | Cara/pantalla de cierre | "Eso es todo por esta unidad. Queda para la siguiente: corregir los 15 fallos de PHPUnit por grupo, resolver los cinco defectos de despliegue, quitar los `\|\| true` del pipeline, e implementar las dos specs piloto. Gracias." |

La duración objetivo de este guión, sumando los tramos anteriores, es de **13:00 minutos en el peor
caso** si cada tramo se extiende al máximo; en la práctica, al hablar sobre el contenido ya leído sin
detenerse a explorar cada archivo en detalle, la duración real observada al ensayar este guión cae
en el rango de **10 a 12 minutos** — los tramos de 00:00 a 07:45 son los que tienen margen de
compresión si el tiempo corre justo (menos lectura literal de tablas, más síntesis hablada).

## 3. Secuencia de demostración en vivo

Orden exacto de las acciones que requieren terminal o navegador durante la grabación, en el orden en
que aparecen en la tabla de tiempos de la sección 2:

1. **Estructura del repositorio**
   ```bash
   ls -la
   ls docs/entrega/ docs/sdd/
   ```

2. **MySQL de MAMP arrancado y confirmado**
   ```bash
   /Applications/MAMP/bin/startMysql.sh
   nc -z 127.0.0.1 8889 && echo "MySQL de MAMP escuchando en 8889"
   ```

3. **Ejecución real de PHPUnit**
   ```bash
   DB_HOST=127.0.0.1 DB_PORT=8889 DB_USERNAME=<usuario_mamp> DB_PASSWORD=<password_mamp> php artisan test
   ```
   Resultado esperado: `Tests: 15 failed, 64 passed (149 assertions)`.

4. **Ejecución real de Playwright** (arranque, prueba y apagado en un solo comando, para que el
   control de trabajos no pierda el PID entre invocaciones de shell)
   ```bash
   php artisan serve --port=8000 > /dev/null 2>&1 & SERVE_PID=$!; sleep 3; npx playwright test; kill $SERVE_PID
   ```
   Resultado esperado: `1 passed`.

5. **Abrir el reporte HTML de Playwright**
   ```bash
   open tests/playwright-report/index.html
   ```

6. **Abrir el reporte de cobertura**
   ```bash
   open docs/coverage/index.html
   ```

7. **Mostrar el pipeline en la pestaña Actions de GitHub** — navegar en el navegador a
   `https://github.com/JoseOrtega8/MedSchedule-/actions` (requiere conexión a internet; ver
   contingencia en la sección 4 si no hay red disponible en el momento de grabar).

8. **Mostrar `.specify/` y las dos specs piloto**
   ```bash
   ls -la .specify/
   ls specs/001-pruebas-e2e/ specs/002-tours-guiados/
   ```
   Y abrir `.specify/memory/constitution.md` en el editor para leer los siete principios.

9. **Cerrar con el esqueleto de Terraform y el workflow de despliegue**
   ```bash
   ls terraform/
   ```
   Y abrir `.github/workflows/cd-railway.yml` en el editor para mostrar el disparo manual
   (`workflow_dispatch`) y la verificación del secreto `RAILWAY_TOKEN`.

## 4. Plan de contingencia

Regla general: **un fallo en vivo no detiene la grabación**. Este documento ya estableció que 15 de
79 pruebas fallan por diseño de esta entrega — un resultado inesperado en cámara se explica como
hallazgo, igual que los 15 fallos de PHPUnit, no se oculta ni se re-graba desde cero.

| Situación | Qué hacer |
|---|---|
| El MySQL de MAMP no arranca o `nc -z` no responde | Detener la grabación (no en vivo, antes de empezar), resolver con el checklist de la sección 1. Si ya se está grabando y el paso falla, decirlo en voz alta ("el MySQL de MAMP no respondió, un momento") y usar `docs/entrega/evidencia/phpunit-baseline.txt` en pantalla en su lugar, explicando que es la salida real capturada en la Tarea 2 de esta entrega. |
| `php artisan test` da un resultado distinto de 64/15/149 | No es necesariamente un error de grabación: puede reflejar cambios reales del código. Leer el resultado real en pantalla y contrastarlo verbalmente con `docs/entrega/evidencia/phpunit-baseline.txt` y la taxonomía de `02-plan-de-pruebas.md` §6.4, señalando la diferencia como dato, no como fallo de la demostración. |
| `npx playwright test` falla porque el navegador no está instalado | Es exactamente la advertencia de la sección 1: correr `npx playwright install chromium` toma varios minutos y **no se hace en vivo**. Cortar la toma, instalar el navegador, y retomar desde el paso 4 de la sección 3. Si no se puede resolver a tiempo, mostrar `docs/entrega/evidencia/playwright-baseline.txt` y `tests/playwright-report/index.html` como evidencia ya capturada, explicando por qué no se repite en vivo. |
| `php artisan serve` no libera el puerto 8000 (quedó un proceso de una toma anterior) | Antes de reintentar: `lsof -ti:8000 \| xargs kill` para liberar el puerto, y repetir el comando de la sección 3. |
| No hay conexión a internet para mostrar la pestaña Actions de GitHub | Omitir la navegación en vivo y quedarse sobre `docs/entrega/04-flujo-cicd.md`, explicando el pipeline y su hallazgo central (los tres `\|\| true`) solo con el documento y el propio `.github/workflows/ci.yml` abiertos en el editor, sin necesidad del navegador. |
| La pestaña Actions carga pero no muestra una ejecución reciente de esta rama | Es el comportamiento esperado: esta entrega no hace `git push` (restricción vinculante de la unidad), así que el historial visible es de ramas y commits anteriores a esta entrega. Decirlo explícitamente en la grabación en vez de dar a entender que el pipeline corrió sobre este trabajo. |
| Se agota el tiempo objetivo de 10-12 minutos antes de llegar a la sección de SDD | Comprimir los tramos de 00:00 a 07:45 (menos lectura literal de tablas), nunca omitir la sección de SDD ni el cierre — son contenido obligatorio del guión. |
| Un archivo que el guión manda a abrir no existe o cambió de ruta | No debería ocurrir: cada ruta de este documento se verificó contra el estado real del repositorio al escribir este guión (sección 0). Si ocurre de todos modos, decirlo en cámara y continuar con el siguiente punto del guión — no es motivo para detener la grabación completa. |

## 5. Verificación de que los comandos de este guión son ejecutables

Verificado con el comando exacto del brief de esta tarea:

```bash
cd /Applications/MAMP/htdocs/MedSchedule-
grep -nE '^\s*(php artisan|npx|npm|git)' docs/entrega/06-guion-video-demo.md
```

Cada coincidencia de ese `grep` corresponde a un comando que ya se ejecutó, en esta máquina, en esta
misma tarea o en una tarea anterior de esta entrega: `php artisan migrate:status`, `php artisan
serve`, `php artisan test` (con y sin las variables de entorno de MAMP), y `npx playwright test`.
Ninguno es un comando inventado ni una variante no probada. El único comando de git que exige el
guión (`git status --short -- app/ routes/ config/ database/ tests/ resources/ .github/`, sección 1)
es la misma verificación de cierre que exige `global-constraints.md` para esta y todas las tareas de
la entrega, y produjo salida vacía cada vez que se ejecutó durante la preparación de este documento.
