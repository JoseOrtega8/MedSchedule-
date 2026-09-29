# 11. Integración en CI/CD

## 11.1 Dos workflows

| Workflow | Cuándo corre | Papel |
|---|---|---|
| `.github/workflows/ci.yml` | `push` a `main`, `develop`, `backend`, `frontend`; pull requests contra `main` y `develop` | Integración continua heredada del equipo: formato, pruebas de PHPUnit y, si hay instancia, análisis estático |
| `.github/workflows/release.yml` | `push` a `main` y `develop`; pull requests contra `main`, `develop` y `feat/**`; `workflow_dispatch` | Pipeline de liberación y despliegue (apartado 2), con las compuertas de esta unidad |

## 11.2 Cambios de esta unidad en los workflows

| Archivo | Cambio | Pull request |
|---|---|---|
| `ci.yml` | El job `analisis-estatico` pasa `-Dsonar.qualitygate.wait=true` y `-Dsonar.qualitygate.timeout=300` | [[PR-105]] |
| `release.yml` | Job nuevo `calidad` (puerta de calidad de SonarQube), `needs: integracion`, condicionado a `SONAR_HABILITADO` | [[PR-105]] |
| `release.yml` | Job nuevo `seguridad` (Snyk), `needs: integracion`, siempre corre | [[PR-109]] |
| `release.yml` | El job `pruebas` pasa a `needs: [integracion, calidad, seguridad]` con su condición | [[PR-105]] y [[PR-109]] |

Los módulos de monitoreo, trazabilidad y auditoría no modifican los workflows: su código se
valida con la suite de PHPUnit que ya corre en el pipeline, con `METRICAS_ALMACEN=memoria` y
`OTEL_ENABLED=false` fijados en `phpunit.xml`, sin Redis, Tempo ni Loki.

## 11.3 Qué compuerta detiene qué

| Compuerta | Detiene | Dónde corre | Condición para que actúe |
|---|---|---|---|
| Sintaxis, formato y compilación | Calidad, seguridad, pruebas y despliegue | GitHub Actions (`integracion`) | Siempre |
| Puerta de calidad de SonarQube | Pruebas y despliegue | GitHub Actions (`calidad`) o local con Docker (`scripts/sonarqube-escanear.sh`) | En Actions, solo con `SONAR_HABILITADO=true` y una instancia accesible; en local, siempre que se ejecuta el script |
| Vulnerabilidades altas o críticas (Snyk) | Pruebas y despliegue | GitHub Actions (`seguridad`) o local (`npm run seguridad:snyk`) | Solo con `SNYK_TOKEN`; sin él, aviso visible y el job termina en éxito |
| Umbrales de k6 | Despliegue | GitHub Actions (`pruebas`) | Siempre |
| PHPUnit | Nada, por ahora | GitHub Actions (`pruebas` y `ci.yml`) | En modo informativo por `PERMITIR_FALLO_FUNCIONAL` en `release.yml` y con `\|\| true` en `ci.yml`, mientras siga abierto el issue #86 |
| Pruebas de las reglas de alerta (`promtool test rules`) | Nada en el pipeline | Local, en la imagen `prom/prometheus:v3.14.0` | Se ejecutan a mano al cambiar `alertas.yml` |
| Pruebas del script de Snyk (`npm run test:scripts`) | Nada en el pipeline | Local | Se ejecutan a mano al cambiar el script |

Queda dicho de forma explícita: la compuerta funcional no es total mientras siga abierto el
issue #86, que corresponde a fallos anteriores a esta unidad. `pruebas-liberacion.sh` imprime el
resultado real de PHPUnit en su resumen de compuertas; no lo oculta.

## 11.4 Por qué SonarQube corre fuera de Actions

El SonarQube de la Unidad 2 es un stack local (`infra/sonarqube/`). Un runner de GitHub no
alcanza `localhost` de otra máquina, así que los jobs `analisis-estatico` y `calidad` quedan
escritos y solo corren cuando la variable `SONAR_HABILITADO` vale `true` y los secretos
`SONAR_TOKEN` y `SONAR_HOST_URL` apuntan a una instancia accesible. Mientras tanto, la misma
compuerta se ejecuta con `scripts/sonarqube-escanear.sh`, que termina con código 1 si la puerta
no se supera (evidencia en el apartado 0). La compuerta existe y funciona, pero mientras no haya
una instancia accesible se ejecuta fuera de GitHub Actions y depende de que alguien la corra.

## 11.5 Lo que corre fuera del pipeline, a propósito

| Proceso | Dónde | Frecuencia |
|---|---|---|
| Stack de observabilidad (`infra/monitoreo/`) | Docker local | Continuo mientras la aplicación está arriba |
| Evaluación de las reglas de alerta | Prometheus | Cada 15 s |
| Verificación de integridad de la auditoría | Programador de Laravel (`auditoria:verificar`, `dailyAt('03:00')`) | Diaria, siempre que el cron del sistema ejecute `php artisan schedule:run` cada minuto |

El cron del programador no está declarado en el repositorio. En un servidor se configura con
una línea de crontab que ejecute `php artisan schedule:run` cada minuto; sin ella, la
verificación diaria no ocurre y el indicador del visor muestra la última verificación manual.
La verificación también puede ejecutarse a demanda con `php artisan auditoria:verificar`, cuyo
código de salida distinto de cero permite usarla como paso de cualquier automatización.
