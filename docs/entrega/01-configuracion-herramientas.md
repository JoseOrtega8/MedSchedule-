# 01 — Configuración de herramientas

Punto 1 de la rúbrica: "parámetros de configuración de las herramientas utilizadas, planeación de
su uso, instalación e implementación". Este documento cubre las tres partes en secciones separadas.

## 0. Método de verificación

Todo dato de este documento se obtuvo abriendo el archivo citado en esta máquina (rama
`feat/unidad-docs-sdd`) o ejecutando el comando de verificación indicado. Cuando el valor difiere
entre el entorno local y el de integración continua (GitHub Actions), se documentan ambos por
separado. No se cita ninguna versión ni parámetro sin su archivo y línea de origen.

Evidencia cruda de entorno capturada al preparar esta entrega: `docs/entrega/evidencia/entorno.txt` y
`docs/entrega/evidencia/phpunit-baseline.txt`.

## 1. Inventario de herramientas

| # | Herramienta | Propósito | Versión exacta | Dónde vive su configuración | Criterio de elección |
|---|---|---|---|---|---|
| 1 | PHP | Runtime del backend | Local: `8.4.1` (`php -v`). CI: `8.2` (`.github/workflows/ci.yml:51`, acción `shivammathur/setup-php@v2`) | `composer.json:9` (`"php": "^8.2"`) | El proyecto exige `^8.2` como mínimo; CI fija ese mínimo exacto para detectar código que dependa de sintaxis 8.3/8.4 inadvertidamente; en local se usa la versión que trae MAMP (más nueva, compatible con el constraint) |
| 2 | Composer | Gestor de dependencias PHP | `2.8.12` (`composer --version`) | `composer.json`, `composer.lock` | Estándar del ecosistema Laravel; `composer.lock` fija versiones exactas resueltas para reproducibilidad |
| 3 | Laravel (Framework) | Framework backend | Constraint `^12.0` (`composer.json:11`), resuelto `v12.53.0` (`composer.lock`, confirmado en `docs/entrega/evidencia/entorno.txt:6`) | `composer.json`, `config/*.php`, `bootstrap/app.php` | Heredado del proyecto (no elegido en esta unidad); provee ORM, rutas, colas, Blade y el resto de la base de la app |
| 4 | MySQL | Motor de BD para pruebas y CI | CI: imagen de servicio `mysql:8.0` (`.github/workflows/ci.yml:36`). Local: MySQL de MAMP en puerto `8889`, base `medschedule_test` | `phpunit.xml:26-27` (fuerza la conexión), `.github/workflows/ci.yml:34-42` | `phpunit.xml` exige `mysql`/`medschedule_test` explícitamente, en vez de la SQLite de desarrollo, para que las pruebas corran contra el mismo motor que producción |
| 5 | SQLite | Base de datos de desarrollo local | La que trae PHP 8.4.1 (extensión `pdo_sqlite`), fichero `database/database.sqlite` | `.env.example:` `DB_CONNECTION=sqlite`; ausente de `phpunit.xml` a propósito | Cero configuración para levantar el entorno de desarrollo; no se usa en pruebas porque `phpunit.xml` la sobreescribe |
| 6 | Node.js | Runtime de build de assets y Playwright | Local: `v25.9.0` (`node -v`). CI: `20` (`.github/workflows/ci.yml:20` y `:57`, acción `actions/setup-node@v4`) | `.github/workflows/ci.yml` | CI fija una LTS (20) para reproducibilidad del pipeline; local usa la versión ya instalada en la máquina |
| 7 | npm | Gestor de paquetes JS | `11.12.1` (`docs/entrega/evidencia/entorno.txt:5`) | `package.json`, `package-lock.json` | Viene con Node; usado para `npm ci`/`npm run build` en CI |
| 8 | Vite | Bundler de assets front-end | `^7.0.7` (`package.json:22`) | `vite.config.js` | Integración oficial de Laravel (`laravel-vite-plugin`) para hot-reload en dev y manifiesto versionado en build |
| 9 | Tailwind CSS | Framework de utilidades CSS | `^3.1.0` (`package.json:21`), con `@tailwindcss/forms ^0.5.2` (`package.json:12`) | `tailwind.config.js`, `postcss.config.js` | El proyecto usa la ruta clásica de PostCSS (`postcss.config.js` con `tailwindcss` + `autoprefixer`), no el plugin de Vite v4 (`@tailwindcss/vite ^4.0.0`, `package.json:13`, presente en `devDependencies` pero no importado en `vite.config.js`) — dato verificado, no corregido en esta unidad |
| 10 | PHPUnit | Pruebas unitarias y de feature en PHP | `^11.5.3` (`composer.json:24`) | `phpunit.xml` | Suite de pruebas estándar de Laravel, invocada vía `php artisan test` |
| 11 | Playwright | Pruebas E2E de navegador | `@playwright/test ^1.62.1` (`package.json:11`) | `playwright.config.js` | Único framework E2E del repositorio; cubre flujos reales de UI que PHPUnit no ejercita |
| 12 | driver.js | Tours guiados de UI (onboarding) | `1.8.0` exacto, sin caret (`package.json:18`) | `resources/js/tours/tour-ejemplo.js` | Versión fijada a propósito (a diferencia de Playwright, que sí lleva `^`) para que un tour de UI no cambie de comportamiento visual entre builds |
| 13 | spec-kit (`specify-cli`) | Herramienta de Spec-Driven Development: genera specs, planes y tareas como skills de Claude Code | `1.0.6.dev0` (`.specify/integration.json:2`) | `.specify/` (config, memoria, plantillas), `.claude/skills/speckit-*/SKILL.md` | Elegido para estructurar la documentación de esta unidad (specs, constitución, tareas) de forma trazable en vez de prosa libre |
| 14 | GitHub Actions | Orquestador de CI/CD | Acciones fijadas: `actions/checkout@v4`, `actions/setup-node@v4`, `shivammathur/setup-php@v2` | `.github/workflows/ci.yml`, `.github/workflows/cd-railway.yml` | Nativo de GitHub, sin infraestructura propia que mantener para CI |
| 15 | Railway (vía Nixpacks) | Constructor y host de despliegue | Configuración declarada en `nixpacks.toml` (formato Nixpacks, sin versión propia fijada en el repo) | `nixpacks.toml`, `.github/workflows/cd-railway.yml` | Railway detecta y construye con Nixpacks automáticamente a partir de este archivo; no requiere Dockerfile propio |
| 16 | Terraform | Infraestructura como código (esqueleto) | `required_version >= 1.6.0` (`terraform/providers.tf:4`), provider `terraform-community-providers/railway ~> 0.4` (`terraform/providers.tf:9`) | `terraform/main.tf`, `terraform/providers.tf`, `terraform/variables.tf` | **No instalado en esta máquina** (`terraform version` → `command not found`). Solo se escribió el esqueleto declarativo; no se ejecutó `init`/`plan`/`apply` en ningún momento de esta unidad |

## 2. Parámetros de configuración relevantes

### 2.1 `playwright.config.js`

| Línea | Parámetro | Valor | Por qué |
|---|---|---|---|
| 6 | `testDir` | `"./tests/playwright_gestion_usuarios"` | Único directorio de specs E2E existente hoy |
| 8 | `timeout` | `30000` (ms) | Margen suficiente para una app Laravel servida localmente sin ser excesivo |
| 10 | `retries` | `0` | La prueba debe pasar de forma determinista; un reintento silencioso ocultaría flakiness real |
| 11-14 | `reporter` | `["list"]` + `["html", {...}]` | Salida en consola durante la corrida y reporte HTML navegable en `tests/playwright-report` |
| 16 | `baseURL` | `process.env.APP_URL \|\| "http://127.0.0.1:8000"` | Permite apuntar a cualquier entorno vía variable de entorno; cae al puerto por defecto de `php artisan serve` |
| 19-20 | `screenshot` / `video` | `"on"` / `"retain-on-failure"` | Estrategia de evidencia: captura siempre, pero solo conserva video cuando la prueba falla (ahorra espacio) |
| 21 | `trace` | `"on-first-retry"` | Trace completo de Playwright solo si hay que reintentar, para depuración sin generar overhead en corridas verdes |
| 23-28 | `projects` | Un solo proyecto `chromium` (`devices["Desktop Chrome"]`) | El repositorio no prueba cross-browser todavía; un solo motor mantiene las corridas rápidas |

### 2.2 `phpunit.xml`

| Línea | Parámetro | Valor | Por qué |
|---|---|---|---|
| 8-10 | `<testsuite name="Unit">` | `tests/Unit` | Pruebas aisladas sin framework de Laravel arrancado |
| 11-13 | `<testsuite name="Feature">` | `tests/Feature` | Pruebas de integración HTTP/BD (la mayoría del suite) |
| 23 | `BCRYPT_ROUNDS` | `4` | Acelera hashing de contraseñas en pruebas (por defecto Laravel usa 12) |
| 25 | `CACHE_STORE` | `array` | Cache en memoria, sin tocar Redis/archivo entre corridas |
| 26 | `DB_CONNECTION` | `mysql` | **Sin indentar**, a diferencia del resto del bloque — señal de edición manual posterior a la plantilla original |
| 27 | `DB_DATABASE` | `medschedule_test` | Igual que la línea anterior, sin indentar; misma señal de edición manual |
| 28 | `MAIL_MAILER` | `array` | No se envían correos reales durante pruebas |
| 29 | `QUEUE_CONNECTION` | `sync` | Los jobs corren en el mismo request, sin worker de colas para pruebas |
| 30 | `SESSION_DRIVER` | `array` | Sesión en memoria, no persiste entre procesos |

Las líneas 26-27 fuerzan **toda** ejecución de `php artisan test` a conectarse a MySQL —
`medschedule_test`— sin importar qué diga `.env`. Esto es lo que obligó a invocar
PHPUnit con variables de entorno explícitas (`DB_HOST`, `DB_PORT`) apuntando al MySQL de MAMP en el
puerto `8889`, en vez de un `php artisan test` plano.

### 2.3 `vite.config.js`

Declara `laravel-vite-plugin` con un arreglo `input` de 7 hojas CSS y 10 módulos JS (líneas 7-25).
**Hallazgo de configuración** (no corregido, documentado también en los documentos 04 y 05 de esta
entrega): `resources/views/patient/dashboard.blade.php:18` referencia
`resources/js/patient-dashboard.js` vía `@vite([...])`, pero ese archivo **no** figura en el
`input` de `vite.config.js`. Con `npm run dev` funciona porque el servidor de Vite sirve cualquier
ruta; con `npm run build` el manifiesto no lo incluye y la vista falla con "Unable to locate file
in Vite manifest" en producción.

### 2.4 `tailwind.config.js` / `postcss.config.js`

`tailwind.config.js:6-10` limita el escaneo de clases a las vistas Blade del proyecto y a las
plantillas de paginación de Laravel; usa la fuente `Figtree` (línea 15) y el plugin
`@tailwindcss/forms` (línea 20). `postcss.config.js` encadena `tailwindcss` y `autoprefixer`
(líneas 2-5) — la vía clásica de integración de Tailwind 3 con Vite, no el plugin nativo v4.

### 2.5 `.editorconfig`

`indent_size = 4` para el general (línea 6), `indent_size = 2` específico para `*.{yml,yaml}`
(línea 15), `end_of_line = lf` (línea 5) e `insert_final_newline = true` (línea 8) en todo el
repositorio salvo Markdown, donde `trim_trailing_whitespace = false` (línea 12) para no romper
saltos de línea forzados con dos espacios.

### 2.6 `nixpacks.toml`

Archivo completo (2 líneas útiles):

```toml
[start]
cmd = "php artisan migrate --force && SERVER_NAME=:$PORT frankenphp run --config /Caddyfile"
```

Es lo que consume el constructor de Railway para arrancar el contenedor. Tiene **tres defectos ya
verificados**, numerados aquí igual que en `docs/entrega/05-estrategia-despliegue.md` §8 (donde se
documentan junto al resto de los defectos de despliegue de esta entrega), documentados aquí como
hallazgos de configuración y **sin corregir** en esta unidad:

- **Defecto 2 — `/Caddyfile` inexistente**: el comando invoca `frankenphp run --config /Caddyfile`, pero
  no hay ningún archivo `Caddyfile` en el repositorio (verificado con `ls` y `find` en la raíz y
  primer nivel). El proceso de arranque fallaría y el contenedor no llegaría a servir la app.
- **Defecto 3 — FrankenPHP no declarado como dependencia**: `composer.json` no menciona `frankenphp` ni
  `laravel/octane` (verificado con `grep -iE`). El comando asume un binario que el builder no
  garantiza proveer.
- **Defecto 4 — migraciones duplicadas**: este `cmd` ya ejecuta `php artisan migrate --force`; el paso
  "Ejecutar migraciones en el entorno desplegado" de `.github/workflows/cd-railway.yml:58-61`
  ejecuta el mismo comando después. No es destructivo (las migraciones de Laravel son idempotentes
  por su tabla de control), pero es redundante y ambiguo sobre cuál de los dos lugares es el
  responsable.

### 2.7 `composer.json` / `package.json`

`composer.json:8-15` fija `php ^8.2`, `laravel/framework ^12.0`, `spatie/laravel-permission ^6.0`
(control de roles) y `google/apiclient ^2.16` (integración con Google Calendar);
`require-dev` (líneas 16-25) incluye `phpunit/phpunit ^11.5.3` y `laravel/pint ^1.24` (formateo).
`package.json:5-9` define los scripts `build` (`vite build`), `dev` (`vite`) y `test:e2e`
(`playwright test`); `devDependencies` (líneas 10-23) fija `driver.js` en `1.8.0` exacto y todo lo
demás con caret (`^`), incluyendo `@playwright/test ^1.62.1`.

### 2.8 `.github/workflows/ci.yml` (preexistente, no modificado en esta unidad)

Dos jobs: `lint-format` (Node 20, `npx eslint`/`npx prettier` ambos con `|| true`, líneas 26 y 29)
y `php-tests` (PHP 8.2 con extensiones `mbstring, pdo, pdo_mysql`, línea 52; servicio `mysql:8.0`
con `MYSQL_DATABASE: medschedule_test`, líneas 36-42; y un `php artisan test` filtrado a cuatro
clases específicas con `|| true` al final, línea 92). El `|| true` en ambos jobs significa que el
pipeline nunca falla por errores de lint ni por pruebas rotas — es la compuerta ausente que
documentan los hallazgos de esta entrega.

### 2.9 `.github/workflows/cd-railway.yml` (creado en esta unidad)

Disparo exclusivamente manual (`workflow_dispatch` con input `entorno`, líneas 6-16); usa
`concurrency` (líneas 18-20) para evitar despliegues simultáneos al mismo entorno; instala el CLI
de Railway (línea 48) y despliega con `railway up --service medschedule-app` (línea 56) **sin**
`--detach` a propósito, para que el paso de migraciones (línea 61) no corra contra un servicio
todavía en transición.

### 2.10 `.specify/integration.json`

`{"version": "1.0.6.dev0", ...}` (línea 2) — versión instalada del CLI de spec-kit, confirma el
dato de la instalación inicial de spec-kit y el de la tabla del punto 1.

### 2.11 `terraform/*.tf`

`providers.tf:4` fija `required_version >= 1.6.0`; `providers.tf:6-11` declara el provider
comunitario `terraform-community-providers/railway ~> 0.4`, con el token inyectado por variable
sensible (`variables.tf:1-7`, `sensitive = true`, sin valor por defecto). `main.tf` deja los
recursos (`railway_project`, `railway_service`) **comentados a propósito** (líneas 15-22) porque
aplicarlos provisionaría infraestructura de pago; solo queda un `output` informativo (líneas
24-27) que recuerda que es un esqueleto sin aplicar.

## 3. Planeación de uso en el ciclo de vida

| Etapa del ciclo de vida | Herramienta(s) que interviene(n) | Rol en esa etapa |
|---|---|---|
| Especificación y documentación | spec-kit (`specify-cli`) | Genera specs, constitución y tareas versionadas en `.specify/` y `.claude/skills/speckit-*` en vez de prosa suelta |
| Desarrollo local (backend) | PHP 8.4.1, Composer, Laravel 12, SQLite | Autoload, ORM, rutas y migraciones contra `database/database.sqlite`, sin dependencias externas para arrancar |
| Desarrollo local (frontend) | Node 25.9, npm, Vite 7, Tailwind 3 | Compilación en caliente de JS/CSS vía `vite.config.js` durante `npm run dev` |
| Onboarding de usuario en UI | driver.js 1.8.0 | Tours guiados dentro de las vistas Blade ya compiladas por Vite |
| Pruebas unitarias y de feature | PHPUnit `^11.5.3` | Corre contra MySQL (`phpunit.xml`), aislado del SQLite de desarrollo |
| Pruebas end-to-end | Playwright `^1.62.1` | Levanta la app servida (`php artisan serve`) y ejercita flujos reales de navegador |
| Control de calidad de código | ESLint, Prettier (vía `npx`, invocados desde `ci.yml`) | Lint y formato de JS/CSS en el job `lint-format` |
| Empaquetado de producción | Vite (`vite build`) | Genera el manifiesto versionado que Laravel sirve en producción |
| Integración continua | GitHub Actions, PHP 8.2, Node 20, MySQL 8.0 (servicio) | Reproduce el entorno de forma aislada por cada push/PR a `main`/`develop`/`backend`/`frontend` |
| Empaquetado de despliegue | Nixpacks (`nixpacks.toml`) | Railway lo usa como receta de build del contenedor |
| Despliegue continuo | GitHub Actions (`cd-railway.yml`), CLI de Railway | Despliegue manual (`workflow_dispatch`) a un entorno (`staging`/`production`) |
| Infraestructura como código | Terraform (esqueleto, sin aplicar) | Describe declarativamente el proyecto y servicio de Railway objetivo de la unidad siguiente |

## 4. Instalación

Comandos verificados desde el estado real de esta máquina. Cada uno está marcado
`[EJECUTADO]` (se corrió literalmente en fases tempranas de esta entrega, con evidencia en
`.superpowers/sdd/2026-09-08-entrega-sdd-cicd/tarea-*-report.md`) o `[PREVISTO, NO EJECUTADO]`
(comando reproducible desde un clon limpio, pero no se ejecutó en esta entrega porque el estado
correspondiente ya existía en la máquina antes de empezar, o porque requiere un recurso —proyecto
de Railway, token— que aún no existe).

```bash
# Clonado e instalación de dependencias — [PREVISTO, NO EJECUTADO]
# (vendor/ y node_modules/ ya existían en esta máquina antes de empezar esta unidad)
git clone <url-del-repositorio>
cd MedSchedule-
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm install
npm run build
```

```bash
# Prerrequisito: arrancar el MySQL de MAMP y confirmar que quedó escuchando —
# [EJECUTADO] (preparación manual del autor, antes de la primera ejecución de pruebas)
# MAMP usa el puerto 8889, no el 3306 por defecto de MySQL. Esa diferencia de puerto es la razón
# por la que el bloque de PHPUnit de abajo recibe DB_HOST/DB_PORT/DB_USERNAME/DB_PASSWORD
# explícitos en vez de depender de config/database.php (ver el bloque "Intento de reproducir..."
# más abajo y el §2.2 de este documento).
/Applications/MAMP/bin/startMysql.sh
nc -z 127.0.0.1 8889 && echo "MySQL de MAMP escuchando en 8889"
```

```bash
# Prerrequisito: crear la base de pruebas — [EJECUTADO] (preparación manual del autor, antes de
# la primera ejecución de pruebas). No documentado en su momento; se reconstruye aquí porque
# `phpunit.xml:26-27` fuerza DB_CONNECTION=mysql / DB_DATABASE=medschedule_test y ningún artefacto
# de la aplicación (migración o seeder) crea esa base — debe existir de antemano o el suite falla
# con "Unknown database 'medschedule_test'". Se usa utf8mb4/utf8mb4_unicode_ci para igualar el
# juego de caracteres esperado en producción y para que los acentos y la eñe de nombres de
# pacientes y doctores se almacenen correctamente.
/Applications/MAMP/Library/bin/mysql80/bin/mysql \
  --socket=/Applications/MAMP/tmp/mysql/mysql.sock -u<usuario_mamp> -p<password_mamp> \
  -e "CREATE DATABASE IF NOT EXISTS medschedule_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

```bash
# Suite de PHPUnit contra el MySQL de MAMP — [EJECUTADO]
# Requiere los dos bloques anteriores (servicio arrancado, base creada).
# Puerto y usuario documentables; la contraseña nunca se imprime ni se commitea.
DB_HOST=127.0.0.1 DB_PORT=8889 DB_USERNAME=<usuario_mamp> DB_PASSWORD=<password_mamp> php artisan test
```

```bash
# Intento de reproducir la línea base con la conexión por defecto — [EJECUTADO]
# No reprodujo el 64/15 porque phpunit.xml fuerza mysql/medschedule_test pero config/database.php
# apunta por defecto al puerto 3306, mientras el MySQL de MAMP de los dos bloques anteriores
# escucha en 8889 (§2.2).
php artisan test
```

```bash
# Prerrequisito: instalar el binario de Chromium que usa Playwright — [PREVISTO, NO EJECUTADO]
# (esta máquina ya tenía binarios de Chromium en caché en ~/Library/Caches/ms-playwright desde
# antes de empezar esta unidad; `playwright install` nunca se corrió durante esta entrega. Desde un clon
# limpio sin esa caché, `npx playwright test` fallaría con "browserType.launch: Executable doesn't
# exist" si se omite este paso).
npx playwright install chromium
```

```bash
# Playwright end-to-end, arranque y apagado explícitos del servidor — [EJECUTADO]
# (arranque y apagado con PID explícito, sin usar "kill %1")
php artisan serve --port=8000 > /dev/null 2>&1 & SERVE_PID=$!
sleep 3
npx playwright test
kill $SERVE_PID
```

```bash
# Instalación de spec-kit — [EJECUTADO]
uv tool install specify-cli --from git+https://github.com/github/spec-kit.git
specify init --here --force --non-interactive --integration claude
```

```bash
# Instalación de driver.js con versión exacta — [EJECUTADO]
npm install --save-dev driver.js@1.8.0
npx vite build --logLevel warn
npx esbuild resources/js/tours/tour-ejemplo.js --bundle --format=esm --loader:.css=text --outfile=/tmp/tour-ejemplo-check.js
```

```bash
# Terraform — [PREVISTO, NO EJECUTADO]
# Terraform no está instalado en esta máquina (`terraform version` -> command not found).
# Ni `init`, ni `plan`, ni `apply` se han corrido en ningún momento de esta unidad.
cd terraform
terraform init
terraform plan -var="railway_token=$TF_VAR_railway_token"
```

```bash
# Despliegue a Railway — [PREVISTO, NO EJECUTADO]
# Requiere el secreto RAILWAY_TOKEN configurado en el repositorio y un proyecto de Railway
# existente; ninguno de los dos existe todavía. Se dispara manualmente (workflow_dispatch),
# nunca automáticamente al hacer push.
railway up --service medschedule-app
railway run --service medschedule-app php artisan migrate --force
```

```bash
# Generación del DOCX de entrega — [EJECUTADO]
# pandoc 3.11 está instalado en /opt/homebrew/bin/pandoc. docs/entrega/MedSchedule_Entrega.docx
# ya existe (555 KB), generado con los cinco diagramas Mermaid de esta entrega renderizados a
# imagen con mermaid-cli antes de la conversión con pandoc.
brew install pandoc
```

CI (`ci.yml`) se dispara automáticamente en GitHub Actions con cada `push`/`pull_request` a
`main`/`develop`/`backend`/`frontend`; como esta entrega no hace `git push` (regla de la unidad),
sus jobs **no se han ejecutado** para los commits de esta rama — quedan `[PREVISTO, NO EJECUTADO]`
hasta que se abra el PR final de esta entrega.

## 5. Implementación: qué queda instalado y qué construye la unidad siguiente

| Herramienta / archivo | Estado al cierre de esta unidad | Qué construye la unidad siguiente |
|---|---|---|
| spec-kit | Instalado (`specify-cli 1.0.6.dev0`), inicializado, con specs piloto y skills funcionando | Specs adicionales por feature, uso continuo de `/speckit-*` en el desarrollo normal |
| PHPUnit | Suite corriendo, línea base 64/15 capturada y taxonomizada (grupos A/B/C + `RegistrationTest` vacío) | Corregir los 15 fallos según la taxonomía, llenar `RegistrationTest` |
| Playwright | Un spec E2E pasando (`gestion-usuarios.spec.js`), config con evidencia automática | Ampliar cobertura E2E (rutas por rol, y una prueba que cargue `patient/dashboard.blade.php` para atrapar el hallazgo de Vite) |
| driver.js | Instalado en versión exacta, con un módulo de tour de ejemplo verificado con `esbuild` | Tours reales integrados en las vistas de producción |
| Vite / `vite.config.js` | Build funcional para los 10 módulos JS declarados; hallazgo documentado de `patient-dashboard.js` faltante en `input` | Agregar `patient-dashboard.js` al `input` y cubrir esa vista con la prueba E2E mencionada arriba |
| GitHub Actions (`ci.yml`) | Preexistente, sin modificar (regla explícita de esta unidad); documentado con sus `|| true` como compuerta ausente | Retirar los `|| true` una vez que las 15 pruebas y el manifiesto de Vite estén resueltos, para que el pipeline realmente pueda fallar |
| GitHub Actions (`cd-railway.yml`) | Creado, con disparo manual y orden correcto (`railway up` bloqueante antes de migrar) | Automatizar el disparo al hacer merge a `main`, una vez el proyecto de Railway y el secreto `RAILWAY_TOKEN` existan |
| `nixpacks.toml` | Sin modificar; tres defectos (numerados como Defecto 2, 3 y 4 en `docs/entrega/05-estrategia-despliegue.md` §8) verificados y documentados | Corregir el Defecto 2 (agregar `Caddyfile` o quitar la bandera `--config`), el Defecto 3 (declarar `frankenphp` u `octane` en `composer.json`) y el Defecto 4 (dejar las migraciones en un solo lugar) antes de intentar un despliegue real |
| Terraform | Esqueleto escrito (`main.tf`, `providers.tf`, `variables.tf`, `README.md`), sin `init`/`apply`; recursos comentados a propósito | Instalar Terraform, ejecutar `init`/`plan`, y descomentar los recursos solo cuando el proyecto de Railway deba crearse por código |
| pandoc | Instalado (`3.11`, `/opt/homebrew/bin/pandoc`); DOCX de entrega ya generado (`docs/entrega/MedSchedule_Entrega.docx`, 555 KB) | Regenerar el DOCX si el contenido de esta entrega cambia |
