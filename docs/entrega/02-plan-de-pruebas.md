# 02 — Plan de pruebas

Punto 2 de la rúbrica: "plan de pruebas, documentación y ejecución del test suite de una
herramienta de pruebas automáticas, más un PR mínimo que arroje los skills y specs para nuevos
módulos". Este documento cubre las tres partes: el plan formal, la ejecución real con su
evidencia, y la descripción del PR mínimo.

## 0. Método de verificación

Todo dato de este documento se obtuvo abriendo el archivo citado en esta máquina (rama
`feat/unidad-docs-sdd`), ejecutando el comando de verificación indicado, o leyendo directamente
la evidencia cruda capturada al ejecutar las pruebas: `docs/entrega/evidencia/phpunit-baseline.txt`,
`docs/entrega/evidencia/playwright-baseline.txt` y `docs/entrega/evidencia/entorno.txt`. Ningún
resultado de prueba se inventa: cada cifra de las secciones 5 y 6 se cita textualmente desde su
archivo de origen. Ningún defecto encontrado se corrige en esta entrega — se documenta con su fix
propuesto para la unidad siguiente, siguiendo la misma regla que ya aplicaron `01-configuracion-
herramientas.md`, `docs/sdd/sdd-proposal.md` y `docs/sdd/sdd-implementation.md`.

## 1. Objetivo

Establecer el plan de pruebas de MedSchedule sobre el estado real del código de la rama
`feat/unidad-docs-sdd`: qué se prueba, con qué herramienta, bajo qué criterios se entra y se sale
de un ciclo de pruebas, cómo se generan los datos de prueba, quién es responsable de cada nivel, y
cómo se gestionan los defectos que la ejecución real ya reveló. El objetivo no es diseñar una
suite nueva desde cero: es documentar la que ya existe (20 clases de PHPUnit, 1 spec de
Playwright), ejecutarla, y dejar registrada con precisión la taxonomía de sus fallos actuales para
que la unidad siguiente los corrija con criterio, no a ciegas.

## 2. Alcance

### 2.1 Dentro de alcance

- Pruebas de feature e integración HTTP con PHPUnit sobre los controladores, middleware y
  servicios existentes (`app/Http/Controllers/`, `app/Http/Middleware/`, `app/Services/`).
- Pruebas unitarias con PHPUnit sobre servicios aislados sin HTTP (`DashboardStatsServiceTest`) y
  sobre el middleware legado (`EnsureAdminRoleTest`).
- Pruebas end-to-end de navegador con Playwright sobre flujos de UI reales servidos por
  `php artisan serve`.
- Ejecución real de ambas suites en esta máquina, con su evidencia cruda capturada.
- Documentación de los defectos y brechas de cobertura que la ejecución revela, con su causa raíz
  verificada leyendo código, sin corregirlos.
- Documentación de los hallazgos del pipeline de CI (`.github/workflows/ci.yml`) que impiden que
  esta suite realmente gobierne el build, con su fix propuesto sin aplicarlo.

### 2.2 Fuera de alcance

- **Corregir cualquiera de los 15 fallos de PHPUnit ya identificados.** Es trabajo explícito de la
  unidad siguiente, ligado a la spec `specs/001-pruebas-e2e/`.
- **Llenar `tests/Feature/Auth/RegistrationTest.php`.** Se documenta como brecha de cobertura
  (sección 6.4), no se implementa.
- **Modificar `.github/workflows/ci.yml`.** Restricción vinculante de esta entrega (ver
  `global-constraints.md`); los hallazgos del pipeline (sección 8) se documentan con su fix
  propuesto, sin tocar el archivo.
- **Ampliar la cobertura E2E más allá del único flujo existente.** Es exactamente el contenido de
  la spec piloto `specs/001-pruebas-e2e/spec.md` (24 tareas en 7 fases), que es trabajo de
  implementación futura, no de esta entrega.
- **Pruebas de carga, de seguridad ofensiva (pentesting) o de accesibilidad formal.** No hay
  herramienta instalada para ninguna de las tres en el inventario de `01-configuracion-
  herramientas.md`; quedan fuera de este plan hasta que el proyecto decida adoptar una.
- **Pruebas manuales exploratorias.** Este plan cubre exclusivamente pruebas automáticas,
  conforme al encargo del punto 2 de la rúbrica.

## 3. Niveles de prueba y herramienta

| Nivel | Herramienta | Qué cubre en MedSchedule | Dónde vive |
|---|---|---|---|
| Unitario | PHPUnit `^11.5.3` | Lógica aislada sin HTTP ni base de datos real de negocio: caché de `DashboardStatsService`, comportamiento del middleware legado `EnsureAdminRole` | `tests/Unit/` (3 clases: `ExampleTest`, `EnsureAdminRoleTest`, `Services/DashboardStatsServiceTest`) |
| Feature / integración | PHPUnit `^11.5.3` | Rutas HTTP completas: autenticación (scaffold Breeze), autorización por rol, CRUD de especialidades, horarios, citas, perfiles, dashboard, bitácora de actividad, integración con Google Calendar | `tests/Feature/` (17 clases, incluidas las 6 de `tests/Feature/Auth/`) |
| End-to-end (navegador) | Playwright `@playwright/test ^1.62.1` | Flujos reales de UI contra la app servida (`php artisan serve`), con Chromium (`devices["Desktop Chrome"]`, `playwright.config.js:23-28`) | `tests/playwright_gestion_usuarios/` (1 spec hoy) |

Las tres herramientas y sus parámetros de configuración (`phpunit.xml`, `playwright.config.js`)
ya están inventariados con su línea exacta en `docs/entrega/01-configuracion-herramientas.md`
§2.1–2.2; este documento no repite esa tabla, solo la referencia.

### 3.1 Justificación de Playwright frente a Katalon y Selenium (nivel E2E)

| Criterio | Playwright | Selenium | Katalon |
|---|---|---|---|
| Costo de licencia | Código abierto (`@playwright/test`, licencia Apache 2.0), sin costo | Código abierto (Selenium WebDriver), sin costo | Tiene una capa gratuita (Katalon Studio) y planes de pago para funciones empresariales (ejecución en la nube, TestOps); **no se verificó en esta entrega el detalle vigente de precios ni límites exactos de la capa gratuita — se deja como incertidumbre explícita en vez de citar una cifra no comprobada** |
| Ejecución en CI sin interfaz gráfica | Nativo: `playwright.config.js` ya corre en modo headless por defecto y el proyecto ya lo invoca con `npx playwright test` sin ventana (evidencia de esta misma entrega, sección 6) | Requiere configurar explícitamente un driver headless (ChromeDriver/GeckoDriver) y gestionar versiones de binario compatibles con el navegador instalado | Katalon Studio es una aplicación de escritorio; correrla sin interfaz en CI requiere su modo "Katalon Runtime Engine" (licencia distinta o límites de la edición gratuita) — **no se verificó en esta entrega el detalle exacto de esa limitación, incertidumbre explícita** |
| Lenguaje compartido con el equipo | JavaScript/TypeScript — el mismo lenguaje que ya usa el proyecto en `resources/js/` (Vite, `driver.js`, los propios módulos de tour); un desarrollador front-end lee y escribe los specs sin cambiar de stack | Requiere un binding por lenguaje (Java, Python, C#, JS); el proyecto no tiene ningún otro consumidor de Selenium instalado | Katalon usa Groovy como lenguaje de scripting subyacente (con una capa de grabación "sin código" sobre él); el equipo no usa Groovy en ningún otro punto del stack (PHP + JS) |
| Captura de evidencia integrada | Integrada de fábrica y ya configurada: `playwright.config.js:19-21` fija `screenshot: "on"`, `video: "retain-on-failure"` y `trace: "on-first-retry"`, sin dependencia externa | No integrada: capturar pantalla o grabar vídeo requiere código adicional propio o una librería de terceros conectada al driver | Katalon Studio genera reportes propios con capturas; formato y flujo distintos a los que ya usa la evidencia de esta entrega (`docs/entrega/evidencia/*.txt`, reportes HTML de Playwright) |
| Velocidad | Se comunica con el navegador vía protocolo nativo (CDP para Chromium), arranques y ejecuciones rápidas; la corrida real de esta entrega tardó 4.8s para el único flujo existente (sección 6.2) | Pasa por el protocolo WebDriver (una capa HTTP intermedia por cada acción), generalmente más lento en flujos con muchas interacciones | No se verificó en esta entrega una comparación de velocidad reproducible entre Katalon y Playwright sobre el mismo flujo; incertidumbre explícita en vez de cifra inventada |

**Conclusión**: Playwright es la elección correcta para MedSchedule, y el argumento más fuerte no
es teórico — **ya está integrado y funcionando en el proyecto**: `package.json` fija
`@playwright/test ^1.62.1` como dependencia de desarrollo, `playwright.config.js` está configurado
con evidencia automática, y la sección 6.2 de este documento reproduce una corrida real y exitosa.
Adoptar Katalon o Selenium significaría reemplazar una herramienta que ya corre en verde por una
que empezaría desde cero, sin ninguna ventaja verificada que compense ese costo. Donde no hay
evidencia comprobada sobre Katalon (precio exacto vigente, límites de su edición gratuita para CI,
velocidad comparada) se deja anotado como incertidumbre, no como afirmación.

## 4. Criterios de entrada

Una ronda de ejecución de este plan puede empezar cuando:

1. El código a probar está en una rama identificable (`feat/unidad-docs-sdd` en esta entrega) y no
   tiene cambios sin commitear que afecten a `app/`, `routes/`, `config/`, `database/`, `tests/`
   ni `resources/`.
2. El entorno de base de datos de pruebas existe y es accesible: para PHPUnit, la base
   `medschedule_test` en el motor que exige `phpunit.xml:26-27` (`DB_CONNECTION=mysql`); para
   Playwright, la app debe poder arrancar con `php artisan serve` sobre esa misma base o sobre la
   de desarrollo, según el flujo a probar.
3. Las dependencias están instaladas: `vendor/` (Composer) para PHPUnit, `node_modules/` y el
   binario de Chromium en caché de Playwright (`npx playwright install chromium`, documentado como
   `[PREVISTO, NO EJECUTADO]` en `01-configuracion-herramientas.md` §4 porque ya estaba en caché en
   esta máquina).
4. No hay una corrida de PHPUnit o Playwright en curso contra el mismo puerto/base de datos, para
   evitar contaminación entre corridas paralelas.

## 5. Criterios de salida

Este plan **no** exige "0 fallos" como condición de salida de la ronda de ejecución de esta
entrega — sería una condición imposible de cumplir hoy sin violar la regla de no corregir defectos
existentes, y ocultaría el hallazgo más valioso de este documento. Los criterios de salida de
**esta** ronda son:

1. Ambas suites (PHPUnit y Playwright) se ejecutaron de principio a fin sin abortar por error de
   entorno, y su salida completa quedó capturada como evidencia (`docs/entrega/evidencia/*.txt`).
2. Todo fallo observado está clasificado dentro de una de las categorías de la sección 6.3, con su
   archivo, línea y causa raíz verificada — no basta con "falló", se exige la explicación.
3. Toda brecha de cobertura detectada (clase de prueba vacía, flujo sin prueba E2E) está
   documentada explícitamente (sección 6.4), no omitida por no ser técnicamente un "fallo".
4. Los hallazgos del pipeline de CI que impiden que un fallo real bloquee el build están
   verificados con `grep -n` contra `.github/workflows/ci.yml` y documentados con su fix propuesto
   (sección 8).

Para una **ronda futura**, una vez la unidad siguiente corrija la taxonomía de la sección 6.3, el
criterio de salida exigible sí es el estándar: 0 fallos en PHPUnit, la suite completa de Playwright
en verde (no solo el flujo actual), y el paso de tests de CI sin `--filter` reducido ni `|| true`.

## 6. Ejecución con evidencia real

### 6.1 Entorno de la corrida

Capturado en `docs/entrega/evidencia/entorno.txt`:

```
# Entorno de ejecución — 2026-09-09 19:26
PHP:      PHP 8.4.1 (cli) (built: Nov 21 2024 08:58:37) (NTS)
Composer: Composer version 2.8.12 2025-09-19 13:41:59
Node:     v25.9.0
npm:      11.12.1
Laravel:  Laravel Framework 12.53.0
Conexión BD por defecto: sqlite
```

Nota de entorno (comentario propio de `phpunit-baseline.txt`, líneas 2-8): `phpunit.xml` fuerza
`DB_CONNECTION=mysql` y `DB_DATABASE=medschedule_test`, mientras `config/database.php` apunta por
defecto al puerto `3306` sin contraseña — incorrecto para esta máquina, donde MySQL de MAMP
escucha en `8889`. Por eso la corrida real se invocó pasando host, puerto y credenciales de MAMP
por variable de entorno en línea de comando (redactadas aquí por ser un repositorio público),
igual que hace el CI, sin editar `.env`, `phpunit.xml` ni `config/database.php`.

### 6.2 PHPUnit — resultado real

Fuente: `docs/entrega/evidencia/phpunit-baseline.txt`. **Resultado: 64 pruebas pasan, 15 fallan,
149 aserciones, en 3.04s.**

Encabezado de clases y su veredicto (`PASS`/`FAIL`), tal como aparece en la evidencia:

```
   PASS  Tests\Unit\EnsureAdminRoleTest
   PASS  Tests\Unit\ExampleTest
   PASS  Tests\Unit\Services\DashboardStatsServiceTest
   PASS  Tests\Feature\ActivityLogControllerTest
   FAIL  Tests\Feature\AdminRbacAccessTest
   FAIL  Tests\Feature\AppointmentControllerTest
   PASS  Tests\Feature\Auth\AuthenticationTest
   PASS  Tests\Feature\Auth\EmailVerificationTest
   PASS  Tests\Feature\Auth\PasswordConfirmationTest
   PASS  Tests\Feature\Auth\PasswordResetTest
   PASS  Tests\Feature\Auth\PasswordUpdateTest
   PASS  Tests\Feature\AuthTest
   FAIL  Tests\Feature\DashboardControllerTest
   FAIL  Tests\Feature\DoctorProfileControllerTest
   PASS  Tests\Feature\ExampleTest
   FAIL  Tests\Feature\GoogleCalendarControllerTest
   PASS  Tests\Feature\ProfileTest
   FAIL  Tests\Feature\ScheduleControllerTest
   FAIL  Tests\Feature\SpecialtyControllerTest
```

(`tests/Feature/Auth/RegistrationTest.php` no aparece en este listado: no tiene métodos, ver
sección 6.4.)

Línea final de la corrida, textual:

```
  Tests:    15 failed, 64 passed (149 assertions)
  Duration: 3.04s
```

El detalle de cada uno de los 15 fallos, con su archivo y línea exacta, está en la sección 6.3
(taxonomía) y se puede confirmar íntegro en `docs/entrega/evidencia/phpunit-baseline.txt` líneas
127-357.

### 6.3 Playwright — resultado real

Fuente: `docs/entrega/evidencia/playwright-baseline.txt`. **Resultado: 1 prueba pasa, 0 fallan.**

```
Running 1 test using 1 worker

  ✓  1 [chromium] › tests/playwright_gestion_usuarios/gestion-usuarios.spec.js:23:1 › gestion de usuarios: crear un usuario y editar su rol (3.3s)

  1 passed (4.8s)
```

El servidor se levantó y se apagó con el patrón de PID explícito adoptado en esta entrega
(`php artisan serve --port=8000 > /dev/null 2>&1 & SERVE_PID=$!` … `kill $SERVE_PID`), sin usar
`kill %1`, según indica el comentario de cabecera del propio archivo de evidencia.

### 6.4 Taxonomía verificada de los 15 fallos de PHPUnit

**Los 15 fallos no se atribuyen a "base de pruebas vacía sin roles sembrados": esa lectura es
incorrecta y fue descartada explícitamente al leer el código de cada test y de la ruta o
controlador que ejercita. Son tres problemas distintos, con causas raíz diferentes que exigen
fixes diferentes:**

#### Grupo A — 10 fallos: tests obsoletos que nunca autentican

Archivos: `AdminRbacAccessTest` (2 fallos), `AppointmentControllerTest` (2), `DoctorProfileControllerTest`
(2), `ScheduleControllerTest` (2), `SpecialtyControllerTest` (2).

Ninguno de estos tests usa `actingAs()` ni `RefreshDatabase`. `AdminRbacAccessTest` en particular
inyecta `withSession(['mock_current_user' => [...]])`, el mecanismo del middleware legado
`App\Http\Middleware\EnsureAdminRole`. Pero las rutas que golpean ya están protegidas por
`Route::middleware(['auth', 'role:admin'|'role:doctor'])` de Spatie (`routes/web.php:46`, bloque de
administrador que cierra en la línea 101, y `routes/web.php:103`, bloque de doctor que cierra en la
línea 119, según `sdd-proposal.md` §5.2, verificado también con `grep -n 'Route::middleware'
routes/web.php`). Son tests que no se actualizaron cuando el proyecto migró
de autenticación simulada a autenticación real basada en roles: **sembrar roles en la base de
datos no los arreglaría, porque estos tests nunca inician sesión real** — la petición HTTP llega
sin usuario autenticado y el middleware `auth` la redirige (302) o la rechaza (401) antes de que
importe si hay roles sembrados o no. La evidencia real lo confirma: `AppointmentControllerTest`,
`ScheduleControllerTest` y `SpecialtyControllerTest` fallan con "received 401" o "received 302" en
vez del código esperado (`phpunit-baseline.txt` líneas 160-343), exactamente el síntoma de una
petición sin sesión.

#### Grupo B — 3 fallos: drift entre código y test

Archivo: `DashboardControllerTest`. A diferencia del grupo A, **este archivo sí autentica
correctamente** (`RefreshDatabase` + `setupRoles()` + `assignRole()`, sin depender de seeds
externos — ver `tests/Feature/DashboardControllerTest.php` líneas 17-29). La ruta `/dashboard`
(`routes/web.php:19-31`) siempre devuelve una redirección 302 según el rol, incluso para
`admin` y `patient`, cuando el test espera `200 OK`; y redirige al doctor hacia `doctor.dashboard`
en vez de `doctor.agenda`, como espera `test_doctor_dashboard_redirects_to_agenda`. La evidencia
real confirma exactamente esa divergencia:

```
Failed asserting that two strings are equal.
-'http://127.0.0.1:8000/doctor/agenda'
+'http://127.0.0.1:8000/doctor/dashboard'
```

Es una divergencia real entre el comportamiento del código y la expectativa del test — no un test
mal escrito ni una base de datos sin sembrar.

#### Grupo C — 2 fallos: defectos funcionales probables

Archivo: `GoogleCalendarControllerTest`. También autentica bien, usa `RefreshDatabase` y Mockery.
En `test_sync_confirmed_appointment_creates_event` el mock de
`GoogleCalendarService::createEvent` se define para devolver `'fake-event-id-123'`, pero la
respuesta observada trae `google_event_id` en `null`
(`Failed asserting that null is identical to 'fake-event-id-123'.`). En
`test_unsync_clears_google_event_id`, `deleteEvent` se invoca 0 veces en vez de 1 sobre el mock
inyectado (`InvalidCountException`). Apunta a que el controlador resuelve una instancia del
servicio distinta a la mockeada, o a que el flujo probado no llega a invocar el servicio como el
test asume — probablemente relacionado con que `sync()` despacha el trabajo de forma asíncrona
(`SyncAppointmentToCalendar::dispatch`, según `sdd-proposal.md` §5.2) mientras el test asume una
respuesta síncrona.

#### Brecha adicional de cobertura (sin fallo asociado)

`tests/Feature/Auth/RegistrationTest.php` existe pero está vacío:

```php
class RegistrationTest extends TestCase {}
```

Sin ningún método `test_*`. PHPUnit no lo reporta como fallo porque no tiene nada que ejecutar — es
un stub preexistente, no un archivo omitido por error de filtro ni de configuración. El registro
de usuarios nuevos queda sin cobertura automática real, a diferencia de login, verificación de
correo, reseteo de contraseña y actualización de perfil, que sí la tienen (`AuthenticationTest`,
`EmailVerificationTest`, `PasswordResetTest`, `PasswordUpdateTest`, `ProfileTest`, todas en
`PASS`).

### 6.5 Consecuencia para la unidad siguiente

Los 15 fallos **no se corrigen en esta entrega** (regla explícita: no arreglar nada existente). Se
reportan con esta taxonomía y quedan como trabajo de la unidad siguiente:

- Grupo A (10 fallos): reescribir los 5 archivos afectados para usar `actingAs($usuario)` con rol
  asignado vía Spatie, siguiendo el patrón ya correcto de `ActivityLogControllerTest` y
  `DashboardControllerTest`. Es exactamente lo que prohíbe reintroducir la skill
  `generar-casos-prueba` (sección 9.2).
- Grupo B (3 fallos): decidir cuál de los dos lados tiene razón — si `/dashboard` debe devolver
  `200` para `admin`/`patient` y redirigir al doctor directo a `doctor.agenda`, se corrige la ruta;
  si el comportamiento actual es el deseado, se corrigen los tres tests. Esa decisión de producto
  no le corresponde a este documento.
- Grupo C (2 fallos): investigar la resolución de dependencias de `GoogleCalendarService` en
  `GoogleCalendarController::sync`/`unsync` y si el despacho asíncrono del job es compatible con
  la aserción síncrona del test, o si el test debe reescribirse para esperar el job en cola
  (`Queue::fake()` + `assertPushed`) en vez de una respuesta inmediata.
- Brecha de `RegistrationTest`: implementar sus casos siguiendo el patrón de los demás archivos de
  `tests/Feature/Auth/`, ya en `PASS`.

Todo este trabajo queda ligado a `specs/001-pruebas-e2e/`, la spec piloto que ya declara como
criterio de éxito medible (`SC-003`) que "el 100% de las ejecuciones con al menos un fallo terminan
en estado fallido, sin el `|| true` que hoy oculta ese resultado" — ver sección 8.

## 7. Estrategia de datos de prueba

MedSchedule usa dos mecanismos de datos de prueba, sin mezclarlos dentro de una misma prueba:

1. **Datos generados en el propio test, con factories + helpers de rol.** Es el patrón correcto,
   usado por los archivos en `PASS` que dependen de un usuario autenticado
   (`ActivityLogControllerTest`, `DashboardControllerTest`): cada clase declara `use
   RefreshDatabase;` para partir de una base limpia en cada método, usa `User::factory()->create()`
   (`database/factories/UserFactory.php`, que genera nombre, apellido, correo único y contraseña
   hasheada con `fake()`) y crea/asigna el rol necesario en el momento con `Role::firstOrCreate([
   'name' => '<rol>', 'guard_name' => 'web'])` + `$user->assignRole($role)`. No depende de que
   exista ningún seeder ejecutado de antemano — es autocontenido y aislado entre pruebas.
2. **Seeders de datos de demostración, para entornos servidos manualmente (desarrollo local y
   Playwright).** `database/seeders/DatabaseSeeder.php` crea los tres roles (`admin`, `doctor`,
   `patient`) y un usuario fijo por rol (`admin@test.com`, más los correspondientes doctor/paciente,
   con contraseña `password` vía `bcrypt()`); `ScheduleSeeder`, `DoctorProfileSeeder`,
   `PatientProfileSeeder` y `AppointmentSeeder` completan perfiles, horarios y citas de ejemplo
   sobre esos mismos usuarios (`User::role('doctor')->first()`, etc.); `FullDataSeeder` agrega un
   catálogo más amplio de especialidades. Estos seeders **no** son la fuente de datos de PHPUnit —
   ninguna clase de prueba los invoca — son para levantar un entorno navegable con
   `php artisan serve` que Playwright pueda ejercitar con credenciales reproducibles.

**Regla para pruebas nuevas** (heredada de la skill `generar-casos-prueba`, sección 9.2): todo test
de ruta protegida debe autenticar con `actingAs()` y un rol real de Spatie asignado en el propio
test o vía un helper (`makeAdmin()`, `makeDoctor()`, `makePatient()`); nunca con
`withSession(['mock_current_user' => [...]])`, el patrón responsable del grupo A de fallos (sección
6.4). Ningún dato de prueba —seeder, factory o fixture de Playwright— usa datos reales de pacientes
ni credenciales de producción; los correos y contraseñas de los seeders son marcadores fijos de
entorno de prueba (`admin@test.com` / `password`), no secretos, y no dan acceso a ningún sistema
fuera de esta máquina.

## 8. Hallazgos del pipeline de CI

Verificado con `grep -n` contra `.github/workflows/ci.yml` en esta máquina, sin modificar el
archivo (restricción vinculante de esta entrega — ver `global-constraints.md`, regla "No se
modifica `.github/workflows/ci.yml` bajo ninguna circunstancia").

```
26:              run: npx eslint resources/js --ext .js --max-warnings=50 || true
29:              run: npx prettier --check "resources/**/*.{js,css}" || true
92:              run: php artisan test --filter="AuthTest|ActivityLogControllerTest|ExampleTest|EnsureAdminRoleTest" || true
```

**Efecto de las líneas 26, 29 y 92.** El sufijo `|| true` hace que el código de salida del comando
anterior se descarte y el paso reporte éxito sin importar el resultado real. Concretamente:

- Línea 26 (ESLint) y línea 29 (Prettier): el job `lint-format` nunca falla por errores de estilo
  ni de formato, sin importar cuántos warnings o violaciones existan.
- Línea 92 (PHPUnit): el job `php-tests` nunca falla aunque la suite tenga fallos reales. Con la
  taxonomía de la sección 6.4 ya confirmada, si esta línea no tuviera `|| true` y ejecutara la
  suite completa, el build fallaría hoy mismo por los 15 fallos reales — pero el pipeline actual lo
  reportaría en verde igual.

**Efecto adicional de la línea 92: el filtro.** `--filter="AuthTest|ActivityLogControllerTest|
ExampleTest|EnsureAdminRoleTest"` nombra cuatro patrones, pero casa por subcadena: `ExampleTest`
coincide con dos archivos distintos (`tests/Unit/ExampleTest.php` y `tests/Feature/ExampleTest.php`,
las pruebas de ejemplo de fábrica de Laravel, que no prueban nada del dominio), así que en realidad
se ejecutan cinco clases. El repositorio tiene 20 clases de prueba en total (verificado con
`find tests -name "*.php" -exec grep -l "^class " {} \; | wc -l` → `20`). Las cinco que sí corren en
CI —coincidentemente— son todas clases en `PASS` en la corrida real de esta entrega (sección 6.2);
las 15 restantes, incluidas las que hoy tienen fallos reales (`AdminRbacAccessTest`,
`AppointmentControllerTest`, `DashboardControllerTest`, `DoctorProfileControllerTest`,
`GoogleCalendarControllerTest`, `ScheduleControllerTest`, `SpecialtyControllerTest`), **nunca se
ejecutan en el pipeline**. El CI de este repositorio jamás ha visto los 15 fallos documentados en
este plan, porque nunca corrió las clases donde viven.

**Playwright no se ejecuta en el pipeline en absoluto.** `.github/workflows/ci.yml` no contiene
ningún paso que invoque `npx playwright test`, `playwright install` ni referencia alguna al
directorio `tests/playwright_gestion_usuarios/` (verificado leyendo el archivo completo, 92
líneas). El único flujo E2E del proyecto se ejecuta hoy exclusivamente de forma manual/local, como
en la sección 6.3 de este documento.

**Combinación de los tres hallazgos.** El pipeline actual no tiene ninguna compuerta real: el lint
nunca bloquea, el 20% de las clases de PHPUnit que sí corren siempre pasan porque son las únicas
elegidas, el 80% restante —donde viven los 15 fallos reales— nunca se ejecuta, y el único flujo E2E
no corre en absoluto. Un pull request podría introducir una regresión en cualquiera de esas tres
superficies y el check de GitHub Actions se vería en verde igual.

### 8.1 Fix propuesto para la unidad siguiente (sin aplicar)

No se modifica `.github/workflows/ci.yml` en esta entrega. El fix propuesto, en el orden en que
debería aplicarse:

1. Corregir primero los defectos reales que el filtro reducido esconde (grupos A, B y C de la
   sección 6.4, más la brecha de `RegistrationTest`), para que ampliar el `--filter` no reviente el
   build de inmediato sin poder distinguir "regresión nueva" de "deuda ya conocida".
2. Retirar el `--filter` de la línea 92 por completo, para que `php artisan test` corra las 20
   clases.
3. Quitar el `|| true` de las líneas 26, 29 y 92, en ese orden de riesgo creciente: primero lint
   (bajo riesgo de romper el build por ruido de estilo ya controlado), después Prettier, y al
   final el paso de PHPUnit, una vez el punto 1 ya esté resuelto.
4. Agregar un paso nuevo al job `php-tests` (o un job separado) que instale los navegadores de
   Playwright (`npx playwright install --with-deps chromium`), levante la app con el mismo patrón
   de PID explícito adoptado en esta entrega, corra `npx playwright test`, y apague el
   servidor — sin `|| true`, y publicando el reporte HTML (`tests/playwright-report/`) como
   artefacto descargable del workflow.

Este fix es exactamente el contenido de la Fase 2 ("Foundational") de `specs/001-pruebas-e2e/
tasks.md` (según `sdd-implementation.md` §5.1), que ya lo describe como "trabajo de implementación
posterior a esta especificación" — coherente con no tocar `ci.yml` en esta entrega.

## 9. El PR mínimo de esta entrega

### 9.1 Qué contiene

El PR de esta entrega —una sola entrega, un solo PR, por decisión del autor— incluye,
entre otros documentos, cuatro artefactos que son la respuesta directa a la exigencia del punto 2
de la rúbrica de "un PR mínimo que arroje los skills y specs para nuevos módulos":

1. `.claude/skills/generar-spec-modulo/SKILL.md` — skill de IA que investiga el dominio de
   MedSchedule (migraciones, modelos, patrón de autorización vigente) antes de redactar una
   especificación funcional para un módulo nuevo, en el formato exacto de
   `.specify/templates/spec-template.md`.
2. `.claude/skills/generar-casos-prueba/SKILL.md` — skill de IA que, a partir de una spec ya
   aprobada, deriva la matriz de casos de prueba y emite los esqueletos de test (PHPUnit o
   Playwright) correspondientes.
3. `specs/001-pruebas-e2e/` — spec piloto completa (`spec.md`, `plan.md`, `tasks.md`) para ampliar
   la cobertura E2E por rol, con 4 historias de usuario priorizadas y 24 tareas en 7 fases.
4. `specs/002-tours-guiados/` — spec piloto completa para tours guiados con `driver.js@1.8.0`, con
   4 historias de usuario priorizadas y 26 tareas en 7 fases.

### 9.2 Por qué este conjunto satisface el requisito

El profesor no pide una suite de pruebas más grande por sí sola: pide el **proceso** con el que se
aborda un módulo o requerimiento nuevo de forma repetible. Estos cuatro artefactos son exactamente
ese proceso, en dos mitades que se complementan:

- **`generar-spec-modulo` + las dos specs piloto** cubren la mitad "qué construir": la skill es el
  procedimiento reutilizable (leer la constitución, las migraciones y las rutas antes de escribir
  una sola línea de requisito), y `specs/001-pruebas-e2e/` y `specs/002-tours-guiados/` son la
  prueba de que ese procedimiento produce specs completas y verificables sobre este proyecto en
  concreto, no una plantilla teórica sin aplicar.
- **`generar-casos-prueba`** cubre la mitad "cómo se sabrá que quedó bien hecho": convierte cada
  historia de usuario y cada `FR-00X` de una spec en una matriz de casos de prueba trazable y en
  esqueletos de test ejecutables, antes de que exista una sola línea de implementación. Es la
  bisagra entre SDD y TDD que documenta `sdd-implementation.md` §1.

**El vínculo más directo con el hallazgo de mayor peso de este mismo documento**: el paso 3 de
`generar-casos-prueba` prohíbe explícitamente el patrón `withSession(['mock_current_user' =>
[...]])` en favor de `actingAs($usuario)` con rol asignado vía Spatie, y lo señala textualmente
como "la causa raíz de 10 de los 15 fallos actuales del suite" — el mismo grupo A de la sección
6.4 de este documento, verificado aquí de forma independiente leyendo la evidencia real de
ejecución. Esto no es una coincidencia de redacción: significa que **el conjunto de artefactos del
PR ya codifica, como regla escrita para el agente, la lección más cara del diagnóstico de esta
entrega**, de modo que ningún módulo nuevo construido con este flujo pueda reintroducir el fallo
más numeroso ya documentado. Un PR que solo agregara más pruebas no daría esa garantía hacia
adelante; uno que agrega el proceso que genera pruebas correctas por diseño, sí.

## 10. Responsables

| Rol | Responsabilidad en este plan |
|---|---|
| Autor de la entrega (desarrollador único de esta unidad) | Ejecuta ambas suites, captura la evidencia, clasifica los fallos con su causa raíz, y decide qué se corrige y en qué unidad — en esta entrega, ninguno, por regla explícita |
| Verificación cruzada del diagnóstico de fallos (control de calidad interno de esta entrega) | Confirmó, leyendo directamente el código de cada test y de la ruta o controlador que ejercita, la taxonomía de los 15 fallos que este documento reproduce (sección 6.4) |
| Unidad siguiente (mismo equipo, entrega futura) | Corrige los grupos A, B y C de la sección 6.4, llena `RegistrationTest`, implementa las tareas de `specs/001-pruebas-e2e/` y `specs/002-tours-guiados/`, y aplica el fix de `ci.yml` propuesto en la sección 8.1 |
| Quien apruebe el PR (revisión de código) | Verifica que la spec y la matriz de casos de prueba de todo módulo nuevo existan antes de aprobar su implementación, conforme al principio VI de `.specify/memory/constitution.md` ("Spec-Driven Development") |

No existe hoy un equipo de QA separado del equipo de desarrollo en este proyecto: las
responsabilidades de la tabla recaen sobre las mismas personas que escriben el código, apoyadas por
las skills de IA de la sección 9 como sustituto parcial de una revisión de pruebas dedicada.

## 11. Calendario

| Hito | Cuándo | Entregable |
|---|---|---|
| Línea base capturada | Fase temprana de esta entrega (ya ejecutada) | `docs/entrega/evidencia/{phpunit,playwright}-baseline.txt`, `entorno.txt` |
| Taxonomía de fallos verificada | Revisión posterior a la línea base (ya ejecutada) | `docs/entrega/02-plan-de-pruebas.md` §6.4 |
| Este plan de pruebas | Esta misma unidad | `docs/entrega/02-plan-de-pruebas.md` |
| Fix de `ci.yml` (quitar `|| true`, ampliar filtro, agregar Playwright) | Unidad siguiente, antes de corregir defectos de producto | Pull request que modifique `.github/workflows/ci.yml` |
| Corrección de los grupos A, B y C | Unidad siguiente, después del fix de CI (para que el pipeline sí detecte regresiones mientras se corrige) | Commits ligados a `specs/001-pruebas-e2e/tasks.md` |
| Implementación de `specs/001-pruebas-e2e/` (5 flujos E2E) | Unidad siguiente | Código + pruebas + `ci.yml` ejecutando Playwright en verde |
| Implementación de `specs/002-tours-guiados/` | Unidad siguiente, en paralelo si hay dos personas disponibles | Código + pruebas Playwright de cada tour |

Este calendario no fija fechas de calendario civil porque esta entrega es de una sola persona sin
sprint formal asignado; fija **orden de dependencia** entre hitos, que es la información con valor
real para quien retome el trabajo.

## 12. Gestión de defectos

1. **Registro.** Todo defecto detectado por una corrida de prueba automática se registra con: el
   nombre completo de la clase y método de test que lo detectó, el archivo y línea del código bajo
   prueba que corresponde a la causa raíz (no solo el mensaje de aserción), y el grupo de la
   taxonomía al que pertenece (A, B, C, o "brecha de cobertura" si no es un fallo sino una ausencia
   de prueba). Los 15 fallos y la brecha de `RegistrationTest` de este documento ya siguen ese
   formato en la sección 6.4.
2. **Clasificación por severidad**, aplicada aquí a los grupos ya identificados:
   - **Alta** (bloquea una funcionalidad de negocio en producción): grupo C
     (`GoogleCalendarControllerTest`) — si la integración con Google Calendar no crea ni borra
     eventos como se espera, el usuario final ve una sincronización que aparenta funcionar pero no
     lo hace.
   - **Media** (comportamiento de navegación incorrecto, pero la app sigue siendo usable):
     grupo B (`DashboardControllerTest`) — el dashboard redirige distinto de lo esperado, pero el
     usuario sigue llegando a alguna pantalla funcional.
   - **Baja / deuda técnica** (el código de producto puede ser correcto; el test está desactualizado):
     grupo A — no hay evidencia de que la autorización real (Spatie) esté rota; el problema es que
     el test no la ejercita.
   - **Brecha de cobertura** (no es un defecto de comportamiento, es ausencia de verificación):
     `RegistrationTest` vacío.
3. **No corrección en esta entrega.** Por regla explícita de esta unidad (`global-constraints.md`
   y el encargo original), ningún defecto de las categorías anteriores se corrige aquí. Cada uno
   queda anotado como pendiente, con dueño ("unidad siguiente", sección 10) y con la spec a la que
   se liga cuando aplica (`specs/001-pruebas-e2e/` para las correcciones del suite de pruebas).
4. **Verificación de cierre, cuando se corrija.** Un defecto de esta lista se considera cerrado
   solo cuando: (a) el test que lo detectó pasa a `PASS` sin haber sido debilitado (por ejemplo,
   relajar una aserción `assertOk()` a `assertStatus(302)` para que "pase" no cuenta como
   corrección), y (b) `php artisan test` sin `--filter` se ejecuta completo y el conteo de fallos
   baja respecto a la línea base de 15 documentada aquí, con la nueva salida capturada como
   evidencia igual que en la sección 6.

## 13. Conclusión

La ejecución real de esta entrega deja dos resultados igual de valiosos: Playwright confirma que
el único flujo E2E existente funciona de punta a punta (1 de 1, sección 6.3), y PHPUnit revela que
64 de 79 pruebas pasan mientras 15 fallan por tres causas raíz distintas y verificadas, no por una
suite genéricamente rota (sección 6.4). Que 15 pruebas fallen no es el problema de este documento:
es su hallazgo más valioso, porque cada grupo señala un trabajo concreto y distinto para la unidad
siguiente — reescribir tests obsoletos (grupo A), resolver una divergencia real de producto (grupo
B), e investigar un defecto funcional probable (grupo C) — en vez de un "arreglar los tests" sin
dirección. Sumado a que el pipeline de CI hoy no puede detectar ninguno de estos tres problemas
(sección 8, hallazgos ya verificados con `grep -n`), y a que el PR de esta entrega ya deja escrito
el proceso —skills y specs piloto (sección 9)— que evita que el módulo siguiente repita el fallo
más numeroso de este diagnóstico, este plan cierra con una ruta de trabajo verificable, no con una
promesa de calidad sin evidencia.
