# 2. Pipeline de liberación y despliegue continuo

## 2.1 Principio de diseño

El pipeline de liberación vive en `.github/workflows/release.yml` y no contiene lógica propia:
cada etapa invoca un script de `scripts/`, el mismo que se ejecuta en local. Así el pipeline no
puede divergir de lo que cada integrante corre en su máquina, y una compuerta que falla en GitHub
Actions se reproduce con un solo comando. En esta unidad los scripts se ejecutaron en dos
lugares: en GitHub Codespaces, el entorno de liberación del proyecto, donde se generó toda la
evidencia (pruebas, compuertas, stacks de `infra/`, carga con k6 y capturas), y en GitHub
Actions, en el pipeline de cada pull request. Para que los stacks corran dentro del Codespace se
agregó Docker al devcontainer (apartado 3.1).

Las etapas se encadenan con `needs`: ninguna corre si la anterior falló. Esta unidad agrega dos
compuertas entre la integración y las pruebas: la puerta de calidad de SonarQube y el análisis
de dependencias con Snyk.

## 2.2 Etapas

| # | Etapa (job de `release.yml`) | Qué hace | Script o acción | Depende de | Detiene la liberación si |
|---|---|---|---|---|---|
| 1 | Integración (`integracion`) | Instala dependencias, revisa sintaxis PHP, formato de los archivos modificados y compila assets | `php -l`, `scripts/verificar-formato.sh`, `npm run build` | — | Falla la sintaxis, el formato o la compilación |
| 2 | Calidad (`calidad`) | Análisis estático y espera del veredicto de la puerta | `SonarSource/sonarqube-scan-action@v5` con `sonar.qualitygate.wait=true`; en local, `scripts/sonarqube-escanear.sh` | `integracion` | La puerta de calidad no se supera |
| 3 | Seguridad (`seguridad`) | Análisis de `composer.lock` y `package-lock.json` | `scripts/snyk-escanear.sh` | `integracion` | Hay una vulnerabilidad de severidad alta o crítica, o el análisis falla |
| 4 | Pruebas en el entorno de liberación (`pruebas`) | Genera el entorno, corre PHPUnit y la prueba de carga de k6 | `scripts/entorno-liberacion.sh`, `scripts/pruebas-liberacion.sh` | `integracion`, `calidad`, `seguridad` | k6 termina con un código distinto de 0 (99 cuando incumple un umbral) |
| 5 | Despliegue (`despliegue`) | Dependencias de producción, migraciones, caché y verificación de salud | `scripts/despliegue.sh` | `pruebas` | El servicio no responde tras 15 intentos |

Las etapas 2 y 3 corren en paralelo, ambas después de la integración. La etapa 4 exige que la
integración haya terminado en éxito, que la calidad haya pasado o se haya omitido, y que la
seguridad haya terminado en éxito. La etapa 5 solo corre en un `push` a `main`.

## 2.3 Condiciones reales de cada compuerta

Una compuerta vale lo que valen sus condiciones. Estas son las que aplican hoy, tal como están
escritas en los archivos:

| Compuerta | Condición | Consecuencia |
|---|---|---|
| Calidad (SonarQube) | El job solo corre si la variable del repositorio `SONAR_HABILITADO` vale `true` | Un SonarQube en `localhost` no es alcanzable desde un runner de GitHub. Sin una instancia accesible el job se omite y el pipeline continúa; la misma compuerta se ejecuta en el Codespace con `scripts/sonarqube-escanear.sh` |
| Seguridad (Snyk) | El job siempre corre; el análisis solo si existe el secreto `SNYK_TOKEN` | Sin token, el análisis se omite con un aviso `::notice::` visible en la corrida y el job termina en éxito. Sin token no hay compuerta de dependencias, solo el aviso |
| Pruebas funcionales (PHPUnit) | `release.yml` define `PERMITIR_FALLO_FUNCIONAL: 'true'` | La suite arrastra fallos anteriores documentados en el issue #86. Mientras siga abierto, un fallo de PHPUnit se reporta en el resumen como "informativo" pero no detiene el pipeline. En `ci.yml` solo corre un subconjunto de cuatro clases, que además hereda un `\|\| true` que descarta el código de salida |
| Pruebas de carga (k6) | Cuatro umbrales en `tests/carga/jri-prueba.js`: `http_req_duration` `p(95)<5000`, `http_req_failed` `rate<0.01`, `tasa_login_exitoso` `rate>0.99` y `duracion_panel` `p(95)<5000` | Si k6 termina con cualquier código distinto de 0 (99 al incumplir un umbral, u otro si la prueba misma falla), `scripts/pruebas-liberacion.sh` sale con 1 y el despliegue no corre |
| Despliegue | Solo en `push` a `main` | En ramas de trabajo y pull requests el pipeline llega hasta las pruebas |

Conviene decirlo sin rodeos: **la compuerta funcional no es total**. Hoy la que detiene la
liberación de forma efectiva es la de rendimiento, además de la integración, la puerta de
calidad (cuando hay instancia) y el análisis de dependencias (cuando hay token). La diferencia
con el `|| true` de `ci.yml` es que `pruebas-liberacion.sh` no oculta el resultado: lo imprime
en el resumen de compuertas y la excepción se activa con una variable explícita que debe
quitarse al cerrar el issue #86.

Una limitación más del despliegue: `scripts/despliegue.sh` ejecuta `php artisan migrate --force`
sin poner antes la aplicación en modo mantenimiento. La migración de integridad de la auditoría
necesita ese modo (apartado 8.4), así que su primer despliegue requiere un paso manual
documentado; incorporarlo al script queda como mejora pendiente.

## 2.4 Diagrama del flujo

| Orden | Etapa | Corre en |
|---|---|---|
| 1 | `integracion` | GitHub Actions |
| 2a | `calidad` (si `SONAR_HABILITADO`) | GitHub Actions; en su defecto, `scripts/sonarqube-escanear.sh` en el Codespace, con Docker |
| 2b | `seguridad` | GitHub Actions |
| 3 | `pruebas` (entorno + PHPUnit + k6) | GitHub Actions, con servicio MySQL 8.0 |
| 4 | `despliegue` (solo `main`) | GitHub Actions, entorno `produccion` |

El flujo se dispara en cada `push` a `main` y `develop`, en cada pull request contra `main`,
`develop` o cualquier rama `feat/**` (necesario porque los pull requests de esta entrega están
encadenados) y a mano con `workflow_dispatch`.

## 2.5 Justificación

- **Calidad antes que pruebas.** El análisis estático es más barato que levantar el entorno y
  correr dos minutos de carga. Si el código no supera la puerta, no tiene sentido medirlo.
- **Dependencias antes que pruebas.** Una vulnerabilidad conocida en una dependencia no se
  arregla con pruebas funcionales: se detiene antes y se evita gastar el resto del pipeline.
- **Calidad y seguridad en paralelo.** No dependen una de otra; correrlas juntas acorta el
  tiempo de la liberación.
- **La observabilidad no es una etapa del pipeline.** Prometheus, Grafana, Loki y Tempo
  vigilan la aplicación ya liberada; no deciden si se libera. Lo que sí entra al pipeline es la
  suite de PHPUnit que cubre la instrumentación (con métricas en memoria y trazas apagadas). Las
  pruebas de las reglas de alerta con `promtool` se ejecutan a mano, fuera del pipeline
  (apartado 11).
