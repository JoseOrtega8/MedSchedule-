# 5. Parámetros de configuración de las herramientas

Cada parámetro de esta sección está copiado del archivo donde vive. Se indica el
archivo y la razón de ese valor, no solo el valor.

## 5.1 k6

Archivo: `tests/carga/jri-prueba.js`

| Parámetro | Valor | Por qué |
|---|---|---|
| `stages` | 30 s → 10 VUs, 1 min → 10 VUs, 30 s → 0 | El requisito pide más de 5 usuarios virtuales. La rampa evita medir el arranque en frío |
| `thresholds.http_req_duration` | `p(95)<5000` | El nivel de servicio de la unidad. k6 sale con código 99 si no se cumple |
| `thresholds.http_req_failed` | `rate<0.01` | Menos del 1 % de respuestas inesperadas |
| `thresholds.tasa_login_exitoso` | `rate>0.99` | Una autenticación fallida invalida el resto de la iteración |
| `thresholds.duracion_panel` | `p(95)<5000` | El endpoint con más consultas por petición se vigila aparte del agregado |
| `summaryTrendStats` | `avg, min, med, max, p(90), p(95), p(99)` | El valor por defecto de k6 no incluye p99, que es donde se ve la cola |
| `noCookiesReset` | `true` | **Imprescindible.** k6 vacía el almacén de cookies entre iteraciones; sin esto la sesión de Laravel se pierde y el panel redirige al login |
| `sleep` entre iteraciones | 1 s | Modela a un usuario que lee la pantalla; sin pausa se mide saturación, no uso |
| `responseCallback` en el login | `expectedStatuses(302, 429)` | El 429 del limitador es conducta prevista del sistema, no un fallo del servidor |
| `responseCallback` en `/about` | `expectedStatuses(200, 500)` | Evita que un defecto ya reportado (#97) distorsione `http_req_failed`. Su salud se sigue en `tasa_about_correcto` |

Variables de entorno reconocidas:

| Variable | Valor por defecto | Para qué |
|---|---|---|
| `URL_BASE` | `http://127.0.0.1:8000` | Destino de la prueba |
| `K6_PASSWORD` | sin valor, obligatoria | Contraseña de las cuentas de carga. **Nunca se escribe en el repositorio** |
| `K6_PREFIJO_USUARIO` | `carga` | Prefijo de los correos por usuario virtual |
| `K6_DOMINIO_USUARIO` | `test.com` | Dominio de esos correos |
| `K6_USUARIO` | sin valor | Fuerza una sola cuenta compartida, para reproducir el limitador a propósito |
| `K6_INTENTOS_LOGIN` | `6` | Reintentos frente a un 429 |
| `K6_ESPERA_LIMITADOR` | `15` | Segundos entre esos reintentos |

## 5.2 SonarQube

Archivo: `sonar-project.properties`

| Parámetro | Valor | Por qué |
|---|---|---|
| `sonar.projectKey` | `medschedule` | Identificador del proyecto. El script lo sustituye por `medschedule-<rama>` en cada análisis |
| `sonar.sources` | `app,routes,resources/js,database` | Solo código que escribe el equipo |
| `sonar.tests` | `tests` | Las pruebas se analizan con reglas propias, distintas de las del código de producción |
| `sonar.exclusions` | `vendor/**, node_modules/**, public/build/**, public/vendor/**, docs/**, storage/**, bootstrap/cache/**, database/migrations/**` | Dependencias y artefactos generados falsearían la deuda técnica |
| `sonar.test.exclusions` | `tests/playwright-report/**, tests/carga/resultados/**` | Informes generados, no código |
| `sonar.sourceEncoding` | `UTF-8` | El proyecto usa acentos en comentarios y cadenas |
| `sonar.php.coverage.reportPaths` | `coverage.xml` | Informe Clover de PHPUnit. Si falta, SonarQube reporta cobertura cero en vez de fallar |

**El host y el token no están en este archivo a propósito.** Llegan por
`SONAR_HOST_URL` y `SONAR_TOKEN` desde `scripts/sonarqube-escanear.sh`, que aborta si
el token no está definido.

Archivo: `infra/sonarqube/docker-compose.yml`

| Parámetro | Valor | Por qué |
|---|---|---|
| Imagen de SonarQube | `sonarqube:community` | Edición gratuita. Versión verificada en esta entrega: 26.9.0.129388 |
| Imagen de base de datos | `postgres:16` | La edición Community requiere base externa; H2 embebido solo sirve para evaluación |
| Puerto publicado | `9000:9000` | Solo en la máquina local; no se expone a internet |
| Volúmenes | `data`, `extensions`, `logs`, `postgres` | Conservan el histórico de análisis entre reinicios |

**Limitación verificada de la edición Community:** no analiza ramas.
`sonar.branch.name` existe a partir de la edición Developer. Por eso
`scripts/sonarqube-escanear.sh` deriva una clave de proyecto por rama
(`medschedule-<rama>`), de modo que cada rama aparece como su propio proyecto y las
comparaciones siguen siendo posibles.

## 5.3 Devcontainer

Archivo: `.devcontainer/devcontainer.json`

| Parámetro | Valor | Por qué |
|---|---|---|
| Imagen base | Construida desde `.devcontainer/Dockerfile` sobre `mcr.microsoft.com/devcontainers/php:1-8.2-bookworm` | PHP 8.2, el mínimo que declara `composer.json`. Se construye en vez de usarse tal cual por el defecto de apt que documenta §5.3.1 |
| `features` node | versión `20` | La misma que usan los workflows |
| `features` sshd | activada | Sin ella `gh codespace ssh` no conecta. Ver §5.3.3 |
| `forwardPorts` | `8000`, `3306` | Aplicación y base de datos. SonarQube no va aquí: corre en un stack local |
| `portsAttributes.visibility` | `private` en los tres | **Decisión de seguridad.** Un puerto público deja la aplicación accesible a cualquiera con la URL |
| `postCreateCommand` | `bash .devcontainer/post-create.sh` | Toda la preparación en un script versionado, no en el JSON |

Archivo: `.devcontainer/docker-compose.yml`

| Parámetro | Valor | Por qué |
|---|---|---|
| Imagen de base de datos | `mysql:8.0` | La misma versión que usan los workflows, para que un fallo de SQL aparezca igual en los dos sitios |
| `healthcheck` | `mysqladmin ping`, 10 s, 10 reintentos, 30 s de gracia | El contenedor de la aplicación no arranca hasta que la base acepta conexiones |
| `volumes` | `datos_mysql` | Sobrevive a reinicios del contenedor, no a borrar el Codespace |
| `image` del servicio `app` | `medschedule-devcontainer-app` | Se fija a mano. Ver §5.3.2 |

### 5.3.1 Por qué se construye la imagen en lugar de usarla directa

La imagen oficial `devcontainers/php` trae configurado el repositorio apt de Yarn con
una **clave GPG caducada**. `apt-get update` termina con error dentro del contenedor y
cualquier feature del devcontainer que instale paquetes falla con código 100:

```
W: GPG error: https://dl.yarnpkg.com/debian stable InRelease: The following signatures
   couldn't be verified because the public key is not available: NO_PUBKEY 62D54FD4003F6525
ERROR: Feature "Docker (Docker-in-Docker)" failed to install!
```

El síntoma en Codespaces es desconcertante: la creación del contenedor falla y el
servicio cae a un **contenedor de recuperación** basado en `base:alpine`, sin PHP ni
Node. La sesión entra con normalidad y `php` no existe.

`.devcontainer/Dockerfile` retira ese repositorio y comprueba que los índices se
actualizan:

```dockerfile
RUN rm -f /etc/apt/sources.list.d/*yarn* \
    && apt-get update \
    && rm -rf /var/lib/apt/lists/*
```

### 5.3.2 Por qué el nombre de la imagen se fija a mano

El repositorio se llama **`MedSchedule-`**, con guion final. Docker Compose deriva la
etiqueta de la imagen del nombre del proyecto, y en Codespaces el nombre de proyecto es
`medschedule-_devcontainer`. La etiqueta resultante sería:

```
invalid tag "medschedule-_devcontainer-app": invalid reference format
```

Docker rechaza esa referencia y la creación del contenedor vuelve a fallar, otra vez
hacia un contenedor de recuperación. Declarar `image: medschedule-devcontainer-app`
junto a `build:` evita que Compose derive el nombre.

El caso se reprodujo en local ejecutando `docker compose --project-name
medschedule-_devcontainer build app`, que falla antes del arreglo y construye después.

### 5.3.3 Por qué hace falta declarar la feature `sshd`

Corregidos los dos defectos anteriores, el contenedor se creó bien —cero contenedores
de recuperación en el registro— pero `gh codespace ssh` seguía sin conectar:

```
you can add the following to your devcontainer.json:
"features": { "ghcr.io/devcontainers/features/sshd:1": { "version": "latest" } }
```

La imagen base de devcontainers no incluye servidor SSH. El contenedor de recuperación
de Codespaces sí lo trae, lo que explica una confusión fácil de cometer: mientras el
devcontainer estuvo roto, la conexión por SSH funcionaba; en cuanto empezó a construirse
bien, dejó de funcionar. El síntoma mejora cuando la causa empeora.

Sin SSH no se puede ejecutar el pipeline desde la línea de comandos, que es justo lo que
esta unidad necesita del entorno.

### 5.3.4 Resumen de los tres defectos del entorno

| # | Defecto | Síntoma | Corrección |
|---|---|---|---|
| 1 | Repositorio apt de Yarn con clave GPG caducada | Features fallan con código 100; contenedor de recuperación sin PHP | `Dockerfile` propio que retira el repositorio |
| 2 | Guion final en el nombre del repositorio | `invalid tag "medschedule-_devcontainer-app"` | `image:` declarada a mano |
| 3 | Imagen base sin servidor SSH | `gh codespace ssh` no conecta | Feature `sshd` declarada |

Ninguno es un error de configuración del autor: los tres son propiedades de las imágenes
oficiales y del nombre heredado del repositorio. Los tres habrían impedido que cualquier
integrante levantara el entorno, y los tres quedan resueltos en archivos versionados, que
es exactamente el argumento del apartado 2: un entorno declarado como código se arregla
una vez para todos.

## 5.4 GitHub Actions

Archivo: `.github/workflows/release.yml`

| Parámetro | Valor | Por qué |
|---|---|---|
| Disparadores | push y PR a `main` y `develop`, más ejecución manual | Cubre integración y liberación sin correr en cada rama de trabajo |
| `concurrency` | `liberacion-<ref>`, cancela en curso | Un push nuevo invalida la corrida anterior de la misma rama |
| Encadenamiento | `integracion` → `pruebas` → `despliegue` mediante `needs` | Ninguna etapa corre si la anterior falló |
| Condición de despliegue | `github.ref == 'refs/heads/main'` y evento `push` | Solo se despliega lo integrado y ya aprobado |
| `environment` | `produccion` | Permite exigir aprobación manual desde la configuración del repositorio |
| Retención de artefactos | 30 días | Los resultados de k6 quedan descargables para comparar corridas |
| `fetch-depth` | `0` en integración | Se necesita el histórico para comparar contra la rama base |

## 5.5 Husky

Archivo: `.husky/pre-commit`

| Comprobación | Alcance | Por qué ese alcance |
|---|---|---|
| `php -l` | Solo archivos PHP en el índice | Detecta errores de sintaxis antes de que lleguen al pipeline |
| `scripts/verificar-formato.sh --staged` | Solo JS y CSS en el índice | **El repositorio arrastra 13 archivos que no cumplen Prettier desde antes de esta unidad.** Exigirlo sobre todo el árbol bloquearía cualquier commit |
| Prueba de humo de k6 | Solo si hay un entorno escuchando | No todo commit toca código que afecte al rendimiento; la ausencia de servidor no debe bloquear |

## 5.6 MySQL en el pipeline

| Parámetro | Valor | Por qué |
|---|---|---|
| Imagen | `mysql:8.0` | Misma versión que el entorno de liberación |
| `health-cmd` | `mysqladmin ping -h localhost -uroot -proot` | Sin la comprobación, las migraciones arrancan antes de que la base acepte conexiones |
| `health-retries` | 10, cada 10 s | MySQL 8 tarda en inicializar el directorio de datos en el primer arranque |

## 5.7 Nota sobre credenciales

Ningún valor de esta sección es un secreto de producción. Las contraseñas que
aparecen en los archivos de Docker Compose pertenecen a contenedores efímeros que se
destruyen con el entorno y no contienen datos reales. Los secretos verdaderos, cuando
existan, van en `.env` (excluido del control de versiones) o en los secretos del
repositorio de GitHub, y ningún script los imprime.
