---

description: "Lista de tareas de implementación de observabilidad y auditoría"
---

# Tasks: Observabilidad y auditoría de MedSchedule

> **Para trabajadores agénticos:** SUB-SKILL REQUERIDA: usar `superpowers:subagent-driven-development` (recomendado) o `superpowers:executing-plans` para ejecutar este plan tarea por tarea. Los pasos usan casillas (`- [ ]`) para seguimiento.

**Goal:** Atender la observación de SonarQube de la unidad 2 y entregar monitoreo con alertas, visor de trazabilidad (logs + trazas) y visor de auditoría con detección de manipulación.

**Architecture:** Tres stacks independientes en contenedores (`infra/sonarqube/` existente, `infra/monitoreo/` nuevo) leen a la aplicación Laravel por HTTP (`/metrics`, `/up`), por archivo (`storage/logs/medschedule.json`) y por OTLP (trazas). La auditoría vive dentro de la aplicación, sobre la tabla `activity_logs` existente, con un trait en los modelos y una cadena HMAC. Cada punto vive en su propio service provider, su propio archivo de configuración y su propia rama, para que los PRs no se pisen.

**Tech Stack:** PHP 8.2+ / Laravel 12.53.0, MySQL 8, `promphp/prometheus_client_php` 2.15.1, `open-telemetry/sdk` 1.15.0, `open-telemetry/exporter-otlp` 1.4.0, Prometheus v3.14.0, Alertmanager v0.34.1, Grafana 13.2.2, Loki 3.7.8, Tempo 2.9.5, Grafana Alloy v1.19.2, blackbox-exporter v0.28.0, mysqld-exporter v0.20.0, Redis 8.8.3-alpine, Mailpit v1.31.2.

**Spec:** `specs/003-observabilidad-auditoria/spec.md` (qué y por qué) y `specs/003-observabilidad-auditoria/plan.md` (diseño técnico). Leer ambos antes de empezar.

**Input**: Design documents from `/specs/003-observabilidad-auditoria/`

**Prerequisites**: plan.md (listo), spec.md (listo)

**Tests**: TDD. Cada tarea de código escribe primero la prueba que falla. La configuración de los stacks se prueba con `promtool test rules`, `promtool check config` y `docker compose config`.

**Organization**: Tareas agrupadas por User Story (US1 SonarQube, US2 monitoreo, US3 trazabilidad, US4 auditoría) y por la rama/PR en que se entregan.

## Cambios respecto al plan original

Este archivo describe lo que realmente se implementó. Decisiones que cambiaron durante la implementación y las revisiones:

- **T007**: `alertas.test.yml` incluye casos negativos (la alerta NO dispara bajo el umbral) además de los positivos.
- **T010**: el span raíz guarda `url.template` (plantilla de la ruta) en lugar de `url.path`: la ruta real podía llevar tokens (`reset-password/{token}`, `verify-email/{id}/{hash}`).
- **T010**: el exportador OTLP usa timeout de 1 s y sin reintentos: con los valores por defecto un Tempo inalcanzable bloqueaba ~40 s cada petición.
- **T010**: `/metrics` no se traza (el scrape de Prometheus cada 15 s solo generaba ruido); el `traceparent` entrante se acepta tal cual, válido en local y a validar en el borde en producción.
- **T011**: el span del job se cierra también en `JobExceptionOccurred`: si al job le quedan reintentos, Laravel no dispara `JobProcessed` ni `JobFailed` y el span quedaba abierto.
- **T012**: el canal `json` usa driver `monolog` + `StreamHandler`: el driver `single` ignora `processors` en Laravel 12.53 y los logs salían sin redactar ni contexto de traza.
- **T012**: la redacción se amplió a `PATRONES` en el texto (correo, `Bearer`/`Basic`, CURP), a excepciones del contexto (clase, mensaje redactado, archivo y línea; `QueryException` sin bindings) y a claves por subcadena (`remember_token`, `access_token`, `api_secret`…).
- **T012**: hueco residual (repo público, datos clínicos): `Handler::reportThrowable` escribe el log con `$e->getMessage()` como mensaje principal, y en una `QueryException` ese mensaje trae los bindings interpolados (`SQL: ... allergies = Penicilina-Grave ...`); `RedactarDatosSensibles` solo pasaba el mensaje por `PATRONES` (correo/Bearer/CURP), no por eso. Se agregó `App\Support\MensajeSeguro::de_excepcion()` (para `QueryException`, `'QueryException: ' . getSql()` + SQLSTATE si `getCode()` no está vacío; para el resto, el mensaje por los mismos `PATRONES`), usado en `RedactarDatosSensibles` (contexto y ahora también el `message` del registro, buscando `QueryException` a cualquier profundidad, incluida una encadenada como `getPrevious()`) y en los `catch` de `Trazas::en_span` e `IniciarTraza::handle`, que cambiaron `$span->recordException($error)` por `$span->addEvent('exception', ['exception.type' => ..., 'exception.message' => MensajeSeguro::de_excepcion($error)])` (mismo evento, sin bindings ni stacktrace con argumentos). Se agregaron a `CLAVES` las subcadenas `api_key`, `apikey`, `credential`, `phone`, `telefono`, `signature` (no `key` ni `hash` sueltos: romperían claves inocentes como `cache_key`). El `catch` de `TrazasServiceProvider::trazar_jobs` (cierre de span de job fallido) conserva `recordException()`: queda fuera del alcance de esta corrección.
- **T013**: el folio usa el `request_id` como respaldo cuando las trazas están apagadas (antes decía "no disponible").
- **T016**: no existe el concepto de fila histórica sin sello: la migración sella las filas existentes y `verificar()` trata como rota cualquier fila con `hash` nulo (se quitó `sin_sellar_historicos`).
- **T016**: se quitó la FK `activity_logs.user_id` (conservando el índice): su `ON DELETE SET NULL` alteraba filas selladas al borrar un usuario.
- **T016**: `DB::transaction(..., 3)` reintenta ante deadlocks del `lockForUpdate`; el JSON canónico ordena claves con `ksort(SORT_STRING)`.
- **T017**: `Appointment` protege `reason` y `observaciones` (texto clínico libre) como `[protegido]`.
- **T018**: el `logout` lo registra solo el listener; se quitó el `ActivityLog::create` de `AuthenticatedSessionController@destroy`, que duplicaba la fila.
- **T018/T020**: orden del grupo admin `auth`, `throttle:60,1`, `auditar.denegado`, `role:admin`: el límite de tasa corta antes de escribir auditoría de accesos denegados.
- **T019**: sin comando `auditoria:sellar-historico` (lo cubre la migración); el comando de verificación informa "Registros revisados".
- **T020**: `fputcsv` con escape `''`, `try/catch` en el stream de exportación, `id` de la línea de tiempo con `[0-9]{1,18}` (evita el desbordamiento a 500) y pruebas 404/403.
- **T021**: sin sellado histórico; `migrate` sella la fila alterada del "antes", hay que alterarla de nuevo después; `AUDIT_HMAC_KEY` antes de migrar, modo mantenimiento, no alternar ramas sobre la misma base.
- **T022**: el documento recoge File sharing de Docker Desktop, `LOG_STACK=single,json`, los límites completos de la auditoría y la condición `SONAR_HABILITADO` con el `|| true` heredado del CI.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Puede ejecutarse en paralelo (archivos distintos, sin dependencias)
- **[Story]**: A qué User Story pertenece la tarea (US1, US2, US3, US4)

## Global Constraints

- Repositorio **público**: ningún secreto en archivos versionados. `METRICS_TOKEN`, `AUDIT_HMAC_KEY`, credenciales de Grafana y del exporter de MySQL solo en `.env` / `infra/monitoreo/.env` (gitignored). Los `.env.example` llevan valores vacíos.
- Nunca mostrar, imprimir ni commitear `.env`, `docs/IONOS_Deploy_Checklist.md` ni `docs/MedSchedule_Estructura_DB_IONOS.md`.
- Antes de **cada** commit: `git diff --staged` completo y leído. Si hay duda, no se commitea.
- Commits convencionales en español (`type(scope): descripcion`), sin `Co-Authored-By` ni trailers.
- Comentarios en español; variables y funciones propias en `snake_case`; clases en PascalCase (convención de Laravel). Indentación con **tabuladores** en PHP y Blade, igual que el código existente.
- Manejo de errores con `try/catch` explícito; nunca silenciar: lo que se captura se registra con `Log::warning` o `Log::error`.
- Los errores internos no se exponen al cliente.
- PII médica (alergias, padecimientos, tipo de sangre, CURP, contactos de emergencia) nunca en claro en logs, trazas ni auditoría.
- La vista `/admin/logs` y las migraciones existentes no se modifican.
- No se toca trabajo asignado a otros integrantes (#97, EQ2 #65).
- Nada se publica en GitHub (issues, push, PRs, kanban) sin confirmación explícita del usuario en ese momento.
- Umbrales de alerta tomados de `docs/entrega-u2/03-niveles-de-servicio.md`: p95 < 5000 ms, errores < 1 %.

## Entorno de pruebas (no obvio)

- Las pruebas corren contra **MySQL de MAMP en el puerto 8889**, base `medschedule_test` (lo fuerza `phpunit.xml`). Arrancar MySQL: `/Applications/MAMP/bin/startMysql.sh`.
- `DB_PASSWORD` se define **solo en el shell** (MAMP local): el usuario exporta en su sesión la contraseña de MySQL de MAMP como `MAMP_DB_PASSWORD` y la función de abajo la pasa como `DB_PASSWORD`. Nunca se escribe en `phpunit.xml`, `.env.example` ni en ningún otro archivo versionado.
- Función de apoyo, definida una vez por sesión de shell:

```bash
cd /Applications/MAMP/htdocs/MedSchedule-
pruebas() { env DB_HOST=127.0.0.1 DB_PORT=8889 DB_USERNAME=root DB_PASSWORD="$MAMP_DB_PASSWORD" php artisan test "$@"; }
```

- La suite completa arrastra fallos anteriores a esta unidad (issue #86). La comparación válida es "mismos fallos antes y después", no "todo en verde". T002 guarda la línea base.
- Docker Desktop debe estar corriendo para los stacks (`open -a Docker`).

---

## Phase 1: Setup (PR general — rama `feat/<R17>-u3-sdd`)

### T001 Issues, ramas y tablero

**Files:** ninguno en el árbol de trabajo. Solo GitHub y ramas locales.

**Interfaces:**
- Produce: números reales de issue R17–R20, usados en los nombres de rama `feat/<n>-u3-sdd`, `feat/<n>-monitoreo`, `feat/<n>-trazabilidad`, `feat/<n>-auditoria`.

- [ ] **Paso 1: Confirmar con el usuario antes de crear nada en GitHub**

Mostrar los cuatro títulos y esperar aprobación explícita (repositorio de equipo, acción hacia afuera):

```
R17 docs: SDD y documento de entrega de la unidad 3 (observabilidad y auditoría)
R18 feat: monitoreo con Prometheus, Grafana y alertas
R19 feat: visor de trazabilidad con logs y trazas (Loki + Tempo)
R20 feat: visor de auditoría con detección de manipulación
```

- [ ] **Paso 2: Escribir el cuerpo de cada issue en archivos temporales**

En el scratchpad de la sesión (no en el repo), un archivo por issue con: contexto (enunciado de la unidad 3), User Story que cubre (US1…US4 de `spec.md`), criterios de aceptación copiados de `spec.md` y enlace a `specs/003-observabilidad-auditoria/`.

- [ ] **Paso 3: Crear los issues**

```bash
cd /Applications/MAMP/htdocs/MedSchedule-
gh issue create --repo JoseOrtega8/MedSchedule- --assignee ramonibr \
  --title "R17 docs: SDD y documento de entrega de la unidad 3 (observabilidad y auditoría)" \
  --label documentation --body-file "$SCRATCH/r17.md"
gh issue create --repo JoseOrtega8/MedSchedule- --assignee ramonibr \
  --title "R18 feat: monitoreo con Prometheus, Grafana y alertas" \
  --label feat --label perf --body-file "$SCRATCH/r18.md"
gh issue create --repo JoseOrtega8/MedSchedule- --assignee ramonibr \
  --title "R19 feat: visor de trazabilidad con logs y trazas (Loki + Tempo)" \
  --label feat --label Backend --body-file "$SCRATCH/r19.md"
gh issue create --repo JoseOrtega8/MedSchedule- --assignee ramonibr \
  --title "R20 feat: visor de auditoría con detección de manipulación" \
  --label feat --label security --body-file "$SCRATCH/r20.md"
```

Anotar los cuatro números que devuelve GitHub.

- [ ] **Paso 4: Agregar al tablero y mover a "In Progress"**

```bash
# Por cada issue
gh project item-add 2 --owner JoseOrtega8 --url https://github.com/JoseOrtega8/MedSchedule-/issues/<n>
# IDs necesarios para mover la tarjeta
gh project view 2 --owner JoseOrtega8 --format json --jq '.id'
gh project field-list 2 --owner JoseOrtega8 --format json --jq '.fields[] | select(.name=="Status")'
gh project item-list 2 --owner JoseOrtega8 --format json --limit 300 --jq '.items[] | select(.content.number==<n>) | .id'
gh project item-edit --project-id <id-proyecto> --id <id-item> --field-id <id-campo-status> --single-select-option-id <id-opcion-in-progress>
```

Si existe `scripts/kanban.sh`, usarlo en su lugar.

- [ ] **Paso 5: Renombrar la rama local del SDD**

```bash
git branch -m feat/u3-sdd feat/<R17>-u3-sdd
```

---

### T002 Base del documento de entrega y línea base de pruebas

**Files:**
- Modify: `.gitignore`
- Create: `docs/entrega-u3/evidencia/.gitkeep`

**Interfaces:**
- Produce: carpeta `docs/entrega-u3/` versionable; archivo local de línea base de fallos (fuera del repo).

- [ ] **Paso 1: Reincluir la carpeta de la entrega en `.gitignore`**

Agregar al final de `.gitignore`:

```gitignore
# Documentacion entregable de la unidad 3. Igual que en la unidad 2, la regla
# /docs/* ignora las subcarpetas de docs/ y hay que reincluirla.
!/docs/entrega-u3/

# DOCX generado con pandoc a partir de los .md de docs/entrega-u3.
/docs/entrega-u3/*.docx

# Configuracion local del stack de monitoreo: credenciales de Grafana, del
# exporter de MySQL y el token de metricas que lee Prometheus.
/infra/monitoreo/.env
/infra/monitoreo/prometheus/secretos/
```

- [ ] **Paso 2: Crear la carpeta de evidencia y comprobar que es versionable**

```bash
mkdir -p docs/entrega-u3/evidencia && touch docs/entrega-u3/evidencia/.gitkeep
git check-ignore -v docs/entrega-u3/evidencia/.gitkeep || echo "versionable"
git check-ignore -v docs/entrega-u3/prueba.docx
git check-ignore -v infra/monitoreo/.env
```

Expected: `versionable` para el `.gitkeep`; las dos últimas líneas muestran la regla que las ignora.

- [ ] **Paso 3: Guardar la línea base de la suite completa (fuera del repo)**

```bash
/Applications/MAMP/bin/startMysql.sh
pruebas 2>&1 | tee "$SCRATCH/linea-base-pruebas.txt" | tail -5
grep -E '^\s+(FAIL|⨯)|FAILED' "$SCRATCH/linea-base-pruebas.txt" > "$SCRATCH/linea-base-fallos.txt" || true
```

Registrar en el resumen de la tarea cuántas pruebas pasan y cuántas fallan.

- [ ] **Paso 4: Commit**

```bash
git add .gitignore docs/entrega-u3/evidencia/.gitkeep
git diff --staged
git commit -m "chore(entrega): preparar la carpeta de la entrega de la unidad 3"
```

---

## Phase 2: User Story 1 — Dashboards de SonarQube localizables (Priority: P1) — rama `feat/<R17>-u3-sdd`

**Goal**: Que el evaluador vea los dashboards sin buscarlos y que la puerta de calidad detenga el pipeline.

**Independent Test**: El scanner termina con código distinto de 0 cuando la puerta falla; el documento y el PR muestran las imágenes.

### T003 [US1] Puerta de calidad en el escaneo y en el pipeline

**Files:**
- Modify: `scripts/sonarqube-escanear.sh`
- Modify: `.github/workflows/ci.yml` (job `analisis-estatico`)
- Modify: `.github/workflows/release.yml` (job nuevo `calidad`, `needs` del job de pruebas)

**Interfaces:**
- Produce: `scripts/sonarqube-escanear.sh [rama]` termina con código 1 si la puerta de calidad no se supera.

- [ ] **Paso 1: Hacer que el scanner espere el veredicto de la puerta**

En `scripts/sonarqube-escanear.sh`, sustituir el bloque `docker run ... -Dsonar.scm.disabled=...` y el `echo` final por:

```bash
# sonar.qualitygate.wait hace que el scanner espere el veredicto de la puerta
# de calidad y termine con codigo distinto de cero si no se supera. Asi el
# analisis funciona como compuerta del pipeline y no solo como reporte.
if ! docker run --rm \
    "${argumentos_red[@]}" \
    -e SONAR_HOST_URL="${url_sonarqube}" \
    -e SONAR_TOKEN="${SONAR_TOKEN}" \
    -v "$(pwd):/usr/src" \
    sonarsource/sonar-scanner-cli \
    -Dsonar.projectKey="${clave_proyecto}" \
    -Dsonar.projectName="MedSchedule (${rama})" \
    -Dsonar.scm.disabled="${SONAR_SCM:-true}" \
    -Dsonar.qualitygate.wait=true \
    -Dsonar.qualitygate.timeout=300; then
    echo "" >&2
    echo "La puerta de calidad no se supero o el analisis fallo." >&2
    echo "Detalle en http://localhost:9000/dashboard?id=${clave_proyecto}" >&2
    exit 1
fi

echo ""
echo "Analisis terminado: puerta de calidad superada."
echo "Resultados en http://localhost:9000/dashboard?id=${clave_proyecto}"
```

- [ ] **Paso 2: Validar la sintaxis del script**

Run: `bash -n scripts/sonarqube-escanear.sh && echo ok`
Expected: `ok`

- [ ] **Paso 3: Pedir el veredicto también en `ci.yml`**

En el paso `Analizar con SonarQube` del job `analisis-estatico`, agregar debajo de `uses:`:

```yaml
              with:
                  # Espera el veredicto de la puerta de calidad y falla el job si no se supera
                  args: >
                      -Dsonar.qualitygate.wait=true
                      -Dsonar.qualitygate.timeout=300
```

- [ ] **Paso 4: Agregar el job `calidad` a `release.yml`**

Insertar entre el job `integracion` y el de pruebas (clave del job de pruebas: la que hoy tiene `needs: integracion` y nombre `Entorno de liberacion y pruebas`):

```yaml
    calidad:
        name: Puerta de calidad (SonarQube)
        runs-on: ubuntu-latest
        needs: integracion
        # Igual que en ci.yml: un SonarQube en localhost no es alcanzable desde un
        # runner de GitHub. El job corre cuando exista una instancia accesible; en
        # el Codespace la misma compuerta se ejecuta con scripts/sonarqube-escanear.sh.
        if: ${{ vars.SONAR_HABILITADO == 'true' }}
        steps:
            - name: Descargar el codigo
              uses: actions/checkout@v4
              with:
                  fetch-depth: 0
            - name: Analizar y esperar la puerta de calidad
              uses: SonarSource/sonarqube-scan-action@v5
              env:
                  SONAR_TOKEN: ${{ secrets.SONAR_TOKEN }}
                  SONAR_HOST_URL: ${{ secrets.SONAR_HOST_URL }}
              with:
                  args: >
                      -Dsonar.qualitygate.wait=true
                      -Dsonar.qualitygate.timeout=300
```

Y en el job de pruebas sustituir `needs: integracion` por:

```yaml
        needs: [integracion, calidad]
        # Corre si la integracion paso y la puerta de calidad paso o no aplica
        # (job omitido porque no hay instancia de SonarQube accesible).
        if: ${{ always() && needs.integracion.result == 'success' && (needs.calidad.result == 'success' || needs.calidad.result == 'skipped') }}
```

- [ ] **Paso 5: Validar los workflows**

Run: `ruby -ryaml -e 'ARGV.each { |f| YAML.load_file(f) }; puts "ok"' .github/workflows/release.yml .github/workflows/ci.yml`
Expected: `ok` (Ruby viene con macOS; si falla por sintaxis, marca el archivo y la línea)

- [ ] **Paso 6: Commit**

```bash
git add scripts/sonarqube-escanear.sh .github/workflows/ci.yml .github/workflows/release.yml
git diff --staged
git commit -m "ci(sonar): detener la liberacion cuando no se supera la puerta de calidad"
```

### T004 [US1] Evidencia de la puerta de calidad y capturas de los dashboards

**Files:**
- Create: `docs/entrega-u3/evidencia/sonar-01-panel-general.png`, `sonar-02-puerta-calidad.png`, `sonar-03-hotspots-seguridad.png`, `sonar-04-actividad.png`, `sonar-05-duplicacion.png`, `sonar-06-medidas.png`
- Create: `docs/entrega-u3/evidencia/sonar-puerta-pasa.txt`, `docs/entrega-u3/evidencia/sonar-puerta-falla.txt`

**Interfaces:**
- Consumes: `scripts/sonarqube-escanear.sh` de T003; stack de `infra/sonarqube/` de la U2 (`scripts/sonarqube-local.sh`).

- [ ] **Paso 1: Levantar SonarQube**

```bash
open -a Docker   # si no esta corriendo
bash scripts/sonarqube-local.sh
```

El usuario genera un token en la interfaz (My Account → Security) y lo exporta en su sesión como `SONAR_TOKEN`. No se escribe en ningún archivo.

- [ ] **Paso 2: Corrida que pasa**

```bash
bash scripts/sonarqube-escanear.sh 2>&1 | tee docs/entrega-u3/evidencia/sonar-puerta-pasa.txt; echo "codigo de salida: ${PIPESTATUS[0]}" | tee -a docs/entrega-u3/evidencia/sonar-puerta-pasa.txt
```

Expected: `QUALITY GATE STATUS: PASSED` y `codigo de salida: 0`. Revisar el archivo: no debe contener el token (el scanner no lo imprime; comprobar con `grep -c "$SONAR_TOKEN" docs/entrega-u3/evidencia/sonar-puerta-pasa.txt` → `0`).

- [ ] **Paso 3: Corrida que falla con una puerta estricta**

La cobertura medida es 0 % (explicado en la U2, apartado 8.5), así que una puerta que exige 80 % de cobertura en código nuevo falla de forma honesta:

```bash
clave="medschedule-$(git rev-parse --abbrev-ref HEAD | tr '/' '-' | tr -cd '[:alnum:]-_.')"
curl -sf -u "${SONAR_TOKEN}:" -X POST "http://localhost:9000/api/qualitygates/create" --data-urlencode "name=MedSchedule estricta"
curl -sf -u "${SONAR_TOKEN}:" -X POST "http://localhost:9000/api/qualitygates/create_condition" \
  --data-urlencode "gateName=MedSchedule estricta" -d "metric=new_coverage&op=LT&error=80"
curl -sf -u "${SONAR_TOKEN}:" -X POST "http://localhost:9000/api/qualitygates/select" \
  --data-urlencode "gateName=MedSchedule estricta" -d "projectKey=${clave}"
bash scripts/sonarqube-escanear.sh 2>&1 | tee docs/entrega-u3/evidencia/sonar-puerta-falla.txt; echo "codigo de salida: ${PIPESTATUS[0]}" | tee -a docs/entrega-u3/evidencia/sonar-puerta-falla.txt
```

Expected: `QUALITY GATE STATUS: FAILED` y `codigo de salida: 1`. Si la condición no hace fallar la puerta (por ejemplo, porque no hay "código nuevo" definido en la rama), usar `metric=coverage` en lugar de `new_coverage` y documentar cuál se usó.

Tomar en este momento la captura `sonar-02-puerta-calidad.png` (puerta en rojo con la condición incumplida). Después regresar el proyecto a la puerta por defecto:

```bash
curl -sf -u "${SONAR_TOKEN}:" -X POST "http://localhost:9000/api/qualitygates/deselect" -d "projectKey=${clave}"
```

- [ ] **Paso 4: Capturas de los dashboards**

El usuario inicia sesión en SonarQube en su navegador (la sesión principal nunca ve la contraseña). Las capturas se toman con el navegador automatizado sobre esa sesión, o las toma el usuario, con estos nombres:

| Archivo | Pantalla |
|---|---|
| `sonar-01-panel-general.png` | Overview del proyecto de la rama U3 |
| `sonar-02-puerta-calidad.png` | Puerta de calidad fallida (paso 3) |
| `sonar-03-hotspots-seguridad.png` | Security Hotspots |
| `sonar-04-actividad.png` | Activity (historial de análisis) |
| `sonar-05-duplicacion.png` | Measures → Duplications |
| `sonar-06-medidas.png` | Measures (resumen completo) |

Revisar cada imagen: no debe mostrar tokens ni correos.

- [ ] **Paso 5: Commit**

```bash
git add docs/entrega-u3/evidencia/sonar-*
git diff --staged --stat
git commit -m "docs(entrega): evidencia de la puerta de calidad y capturas de SonarQube"
```

**Checkpoint**: US1 completa en la rama del PR general.

---

## Phase 3: User Story 2 — Monitoreo con alertas (Priority: P2) — rama `feat/<R18>-monitoreo` (sale de `feat/<R17>-u3-sdd`)

**Goal**: Métricas de la app, tres dashboards y seis alertas que notifican a Mailpit.

**Independent Test**: Con el stack arriba, detener la app provoca `AplicacionCaida` y llega el correo.

```bash
git switch -c feat/<R18>-monitoreo feat/<R17>-u3-sdd
```

### T005 [US2] Registro de métricas HTTP

**Files:**
- Create: `config/metricas.php`
- Create: `app/Observability/Metricas/Metricas.php`
- Create: `app/Http/Middleware/RegistrarMetricasHttp.php`
- Create: `app/Providers/MetricasServiceProvider.php`
- Modify: `bootstrap/providers.php`, `bootstrap/app.php`, `phpunit.xml`, `.env.example`, `composer.json`/`composer.lock`
- Test: `tests/Feature/Metricas/MetricasHttpTest.php`

**Interfaces:**
- Produce: `App\Observability\Metricas\Metricas` (singleton) con
  - `registrar_peticion(string $metodo, string $ruta, int $estado, float $segundos): void`
  - `contar_cita(string $evento): void` — `$evento` ∈ `agendadas`, `canceladas`
  - `exportar(): string` — texto en formato Prometheus
- Produce: middleware global `RegistrarMetricasHttp`.

- [ ] **Paso 1: Instalar la dependencia**

```bash
composer require promphp/prometheus_client_php:^2.15.1
ls vendor/promphp/prometheus_client_php/src/Prometheus/Storage/Predis.php
```

Expected: el archivo existe (adaptador que usa `predis/predis`, ya instalado; no requiere la extensión phpredis).

- [ ] **Paso 2: Configuración**

`config/metricas.php`:

```php
<?php

// Configuracion de las metricas de Prometheus que expone la aplicacion
return [
	// 'redis' en ejecucion normal; 'memoria' en pruebas
	'almacen' => env('METRICAS_ALMACEN', 'redis'),

	// Token que Prometheus envia como Bearer; sin token el endpoint no existe
	'token' => env('METRICS_TOKEN'),

	// Redis propio del stack de monitoreo (puerto 6380 para no chocar con otro Redis local)
	'redis' => [
		'host' => env('METRICAS_REDIS_HOST', '127.0.0.1'),
		'port' => (int) env('METRICAS_REDIS_PORT', 6380),
		'timeout' => 0.2,
		'read_write_timeout' => 0.2,
	],
];
```

En `phpunit.xml`, dentro del bloque `<php>`, junto a los demás `<env>`:

```xml
<env name="METRICAS_ALMACEN" value="memoria"/>
<env name="METRICS_TOKEN" value="token-solo-para-pruebas"/>
```

En `.env.example`, al final:

```dotenv
# Metricas para Prometheus (unidad 3). Generar el token con: php -r "echo bin2hex(random_bytes(24));"
METRICAS_ALMACEN=redis
METRICAS_REDIS_HOST=127.0.0.1
METRICAS_REDIS_PORT=6380
METRICS_TOKEN=
```

- [ ] **Paso 3: Escribir la prueba que falla**

`tests/Feature/Metricas/MetricasHttpTest.php`:

```php
<?php

namespace Tests\Feature\Metricas;

use App\Observability\Metricas\Metricas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricasHttpTest extends TestCase
{
	use RefreshDatabase;

	public function test_peticion_registra_contador_e_histograma(): void
	{
		$this->get(route('login'))->assertOk();

		$texto = app(Metricas::class)->exportar();

		$this->assertStringContainsString(
			'medschedule_http_requests_total{method="GET",route="login",status="200"} 1',
			$texto
		);
		$this->assertStringContainsString(
			'medschedule_http_request_duration_seconds_count{method="GET",route="login"} 1',
			$texto
		);
	}

	public function test_redis_caido_no_afecta_la_respuesta(): void
	{
		// Puerto cerrado: cualquier escritura de metricas falla
		config([
			'metricas.almacen' => 'redis',
			'metricas.redis.port' => 1,
		]);
		app()->forgetInstance(Metricas::class);

		$this->get(route('login'))->assertOk();
	}
}
```

- [ ] **Paso 4: Correr la prueba y verificar que falla**

Run: `pruebas --filter=MetricasHttpTest`
Expected: FAIL con `Target class [App\Observability\Metricas\Metricas] does not exist`.

- [ ] **Paso 5: Implementar `Metricas`**

`app/Observability/Metricas/Metricas.php`:

```php
<?php

namespace App\Observability\Metricas;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;

// Punto unico de registro de metricas de la aplicacion
class Metricas
{
	private const ESPACIO = 'medschedule';

	// Cubren desde respuestas rapidas hasta el SLO de 5 s y el doble
	private const BUCKETS = [0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10];

	private const EVENTOS_CITA = ['agendadas', 'canceladas'];

	public function __construct(private CollectorRegistry $registro)
	{
	}

	// Registra una peticion HTTP atendida; $ruta es el nombre de la ruta, nunca la URL
	public function registrar_peticion(string $metodo, string $ruta, int $estado, float $segundos): void
	{
		$this->registro
			->getOrRegisterCounter(self::ESPACIO, 'http_requests_total', 'Peticiones HTTP atendidas', ['method', 'route', 'status'])
			->inc([$metodo, $ruta, (string) $estado]);

		$this->registro
			->getOrRegisterHistogram(self::ESPACIO, 'http_request_duration_seconds', 'Duracion de las peticiones HTTP', ['method', 'route'], self::BUCKETS)
			->observe($segundos, [$metodo, $ruta]);
	}

	// Cuenta una cita agendada o cancelada
	public function contar_cita(string $evento): void
	{
		if (!in_array($evento, self::EVENTOS_CITA, true)) {
			throw new InvalidArgumentException("Evento de cita no soportado: {$evento}");
		}

		$this->registro
			->getOrRegisterCounter(self::ESPACIO, "citas_{$evento}_total", "Citas {$evento}")
			->inc();
	}

	// Calcula los gauges de negocio y devuelve el texto que lee Prometheus
	public function exportar(): string
	{
		$this->registro
			->getOrRegisterGauge(self::ESPACIO, 'citas_hoy', 'Citas programadas para hoy')
			->set(DB::table('appointments')->whereDate('appointment_date', now()->toDateString())->count());

		$this->registro
			->getOrRegisterGauge(self::ESPACIO, 'jobs_fallidos', 'Jobs registrados en failed_jobs')
			->set(DB::table('failed_jobs')->count());

		return (new RenderTextFormat())->render($this->registro->getMetricFamilySamples());
	}
}
```

- [ ] **Paso 6: Implementar el middleware**

`app/Http/Middleware/RegistrarMetricasHttp.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Observability\Metricas\Metricas;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

// Mide cada peticion HTTP y la registra en Prometheus
class RegistrarMetricasHttp
{
	public function __construct(private Metricas $metricas)
	{
	}

	public function handle(Request $request, Closure $next): Response
	{
		$inicio = hrtime(true);

		$respuesta = $next($request);

		$ruta = $request->route();
		$nombre_ruta = $ruta ? ($ruta->getName() ?? 'sin_nombre') : 'sin_ruta';

		// El scrape de Prometheus no se cuenta como trafico de la aplicacion
		if ($nombre_ruta !== 'metricas') {
			try {
				$this->metricas->registrar_peticion(
					$request->method(),
					$nombre_ruta,
					$respuesta->getStatusCode(),
					(hrtime(true) - $inicio) / 1e9
				);
			} catch (Throwable $error) {
				// El monitoreo nunca debe tumbar la aplicacion
				Log::warning('No se pudieron registrar las metricas HTTP', ['error' => $error->getMessage()]);
			}
		}

		return $respuesta;
	}
}
```

- [ ] **Paso 7: Provider y registro del middleware**

`app/Providers/MetricasServiceProvider.php`:

```php
<?php

namespace App\Providers;

use App\Observability\Metricas\Metricas;
use Illuminate\Support\ServiceProvider;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\Predis;

class MetricasServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->app->singleton(Metricas::class, function () {
			$config = config('metricas');

			$almacen = $config['almacen'] === 'memoria'
				? new InMemory()
				: new Predis([
					'host' => $config['redis']['host'],
					'port' => $config['redis']['port'],
					'timeout' => $config['redis']['timeout'],
					'read_write_timeout' => $config['redis']['read_write_timeout'],
				]);

			// false: sin la metrica php_info que agrega la libreria por defecto
			return new Metricas(new CollectorRegistry($almacen, false));
		});
	}
}
```

`bootstrap/providers.php`: agregar `App\Providers\MetricasServiceProvider::class,` después de `AppServiceProvider`.

`bootstrap/app.php`, dentro de `withMiddleware`, después del `append(SecurityHeaders)`:

```php
		// Metricas de Prometheus de cada peticion (unidad 3)
		$middleware->append(\App\Http\Middleware\RegistrarMetricasHttp::class);
```

- [ ] **Paso 8: Correr la prueba y verificar que pasa**

Run: `pruebas --filter=MetricasHttpTest`
Expected: PASS (2 pruebas). Si el nombre de la ruta de login no es `login`, revisar `php artisan route:list --name=login` y ajustar la prueba, no el middleware.

- [ ] **Paso 9: Commit**

```bash
git add config/metricas.php app/Observability app/Http/Middleware/RegistrarMetricasHttp.php app/Providers/MetricasServiceProvider.php bootstrap/providers.php bootstrap/app.php phpunit.xml .env.example composer.json composer.lock tests/Feature/Metricas
git diff --staged
git commit -m "feat(metricas): registrar trafico y duracion de las peticiones HTTP"
```

### T006 [US2] Endpoint `/metrics` protegido y métricas de citas

**Files:**
- Create: `routes/observabilidad.php`
- Create: `app/Http/Controllers/MetricasController.php`
- Create: `app/Observers/ContadorCitasObserver.php`
- Modify: `bootstrap/app.php` (`withRouting`), `app/Providers/MetricasServiceProvider.php` (`boot`)
- Test: `tests/Feature/Metricas/EndpointMetricasTest.php`

**Interfaces:**
- Consumes: `Metricas::exportar()`, `Metricas::contar_cita()` de T005.
- Produce: ruta `GET /metrics` con nombre `metricas`, fuera del grupo `web` (sin sesión ni cookies).

- [ ] **Paso 1: Escribir la prueba que falla**

`tests/Feature/Metricas/EndpointMetricasTest.php`:

```php
<?php

namespace Tests\Feature\Metricas;

use App\Models\Appointment;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndpointMetricasTest extends TestCase
{
	use RefreshDatabase;

	public function test_sin_token_responde_404(): void
	{
		$this->get('/metrics')->assertNotFound();
	}

	public function test_token_incorrecto_responde_404(): void
	{
		$this->withToken('otro-token')->get('/metrics')->assertNotFound();
	}

	public function test_token_correcto_devuelve_metricas(): void
	{
		$respuesta = $this->withToken('token-solo-para-pruebas')->get('/metrics');

		$respuesta->assertOk();
		$this->assertStringStartsWith('text/plain', $respuesta->headers->get('Content-Type'));
		$this->assertStringContainsString('medschedule_citas_hoy', $respuesta->getContent());
		$this->assertStringContainsString('medschedule_jobs_fallidos 0', $respuesta->getContent());
	}

	public function test_cita_agendada_y_cancelada_incrementa_contadores(): void
	{
		$especialidad = Specialty::create(['name' => 'Cardiologia']);
		$paciente = User::factory()->create();
		$doctor = User::factory()->create();

		$cita = Appointment::create([
			'patient_id' => $paciente->id,
			'doctor_id' => $doctor->id,
			'specialty_id' => $especialidad->id,
			'appointment_date' => now()->toDateString(),
			'start_time' => '10:00:00',
			'end_time' => '10:30:00',
			'status' => 'pending',
		]);
		$cita->update(['status' => 'cancelled']);

		$texto = $this->withToken('token-solo-para-pruebas')->get('/metrics')->getContent();

		$this->assertStringContainsString('medschedule_citas_agendadas_total 1', $texto);
		$this->assertStringContainsString('medschedule_citas_canceladas_total 1', $texto);
		$this->assertStringContainsString('medschedule_citas_hoy 1', $texto);
	}
}
```

- [ ] **Paso 2: Correr la prueba y verificar que falla**

Run: `pruebas --filter=EndpointMetricasTest`
Expected: FAIL; la prueba con token correcto recibe 404 porque la ruta no existe.

- [ ] **Paso 3: Implementar el controlador**

`app/Http/Controllers/MetricasController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Observability\Metricas\Metricas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Prometheus\RenderTextFormat;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

// Expone las metricas a Prometheus; para cualquier otro cliente la ruta no existe
class MetricasController extends Controller
{
	public function __invoke(Request $request, Metricas $metricas): Response
	{
		$esperado = (string) config('metricas.token');
		$recibido = (string) $request->bearerToken();

		// Sin token configurado o con token incorrecto se responde 404, no 401,
		// para no revelar que el endpoint existe
		if ($esperado === '' || !hash_equals($esperado, $recibido)) {
			abort(404);
		}

		try {
			$cuerpo = $metricas->exportar();
		} catch (Throwable $error) {
			Log::warning('No se pudieron exportar las metricas', ['error' => $error->getMessage()]);
			abort(503);
		}

		return response($cuerpo, 200, ['Content-Type' => RenderTextFormat::MIME_TYPE]);
	}
}
```

- [ ] **Paso 4: Ruta fuera del grupo `web`**

`routes/observabilidad.php`:

```php
<?php

use App\Http\Controllers\MetricasController;
use Illuminate\Support\Facades\Route;

// Rutas de observabilidad: sin sesion, cookies ni CSRF, porque las consume
// Prometheus cada 15 s y no un navegador
Route::get('/metrics', MetricasController::class)->name('metricas');
```

En `bootstrap/app.php`, dentro de `withRouting(...)`, agregar el argumento `then`:

```php
		then: function () {
			\Illuminate\Support\Facades\Route::middleware([])
				->group(base_path('routes/observabilidad.php'));
		},
```

- [ ] **Paso 5: Observer de citas**

`app/Observers/ContadorCitasObserver.php`:

```php
<?php

namespace App\Observers;

use App\Models\Appointment;
use App\Observability\Metricas\Metricas;
use Illuminate\Support\Facades\Log;
use Throwable;

// Alimenta los contadores de negocio de citas
class ContadorCitasObserver
{
	public function __construct(private Metricas $metricas)
	{
	}

	public function created(Appointment $cita): void
	{
		$this->contar('agendadas');
	}

	public function updated(Appointment $cita): void
	{
		if ($cita->wasChanged('status') && $cita->status === 'cancelled') {
			$this->contar('canceladas');
		}
	}

	private function contar(string $evento): void
	{
		try {
			$this->metricas->contar_cita($evento);
		} catch (Throwable $error) {
			// Un fallo de metricas no debe impedir agendar o cancelar
			Log::warning('No se pudo contar la cita', ['evento' => $evento, 'error' => $error->getMessage()]);
		}
	}
}
```

En `MetricasServiceProvider` agregar:

```php
	public function boot(): void
	{
		\App\Models\Appointment::observe(\App\Observers\ContadorCitasObserver::class);
	}
```

- [ ] **Paso 6: Correr las pruebas y verificar que pasan**

Run: `pruebas --filter="EndpointMetricasTest|MetricasHttpTest"`
Expected: PASS (6 pruebas).

- [ ] **Paso 7: Commit**

```bash
git add routes/observabilidad.php app/Http/Controllers/MetricasController.php app/Observers/ContadorCitasObserver.php app/Providers/MetricasServiceProvider.php bootstrap/app.php tests/Feature/Metricas/EndpointMetricasTest.php
git diff --staged
git commit -m "feat(metricas): exponer /metrics protegido y contar citas agendadas y canceladas"
```

### T007 [US2] Reglas de alerta con pruebas de `promtool`

**Files:**
- Create: `infra/monitoreo/prometheus/alertas.yml`
- Test: `infra/monitoreo/prometheus/alertas.test.yml`

**Interfaces:**
- Consumes: nombres de métricas de T005/T006 (`medschedule_http_requests_total`, `medschedule_http_request_duration_seconds_bucket`, `medschedule_jobs_fallidos`); `probe_success{job="disponibilidad"}` y `mysql_up` de los exporters (T008).
- Produce: alertas `AplicacionCaida`, `LatenciaP95FueraDeAcuerdo`, `LatenciaP95Degradada`, `TasaErrores5xxAlta`, `BaseDeDatosCaida`, `JobsFallidos`, con etiqueta `severidad` (`critica` | `advertencia`).

- [ ] **Paso 1: Escribir las pruebas de reglas (fallan: no existe `alertas.yml`)**

`infra/monitoreo/prometheus/alertas.test.yml`:

```yaml
# Pruebas unitarias de las reglas de alerta: promtool test rules alertas.test.yml
rule_files:
    - alertas.yml

evaluation_interval: 1m

tests:
    - interval: 1m
      input_series:
          - series: 'probe_success{job="disponibilidad",instance="app"}'
            values: '1 1 0 0 0 0'
      alert_rule_test:
          - eval_time: 2m
            alertname: AplicacionCaida
            exp_alerts: []
          - eval_time: 4m
            alertname: AplicacionCaida
            exp_alerts:
                - exp_labels: { severidad: critica, job: disponibilidad, instance: app }
                  exp_annotations:
                      summary: 'MedSchedule no responde en /up'
                      description: 'La sonda de disponibilidad falla desde hace mas de 1 minuto.'

    - interval: 1m
      input_series:
          - series: 'medschedule_http_request_duration_seconds_bucket{method="GET",route="login",le="0.5"}'
            values: '0+1x20'
          - series: 'medschedule_http_request_duration_seconds_bucket{method="GET",route="login",le="5"}'
            values: '0+2x20'
          - series: 'medschedule_http_request_duration_seconds_bucket{method="GET",route="login",le="10"}'
            values: '0+100x20'
          - series: 'medschedule_http_request_duration_seconds_bucket{method="GET",route="login",le="+Inf"}'
            values: '0+100x20'
      alert_rule_test:
          - eval_time: 15m
            alertname: LatenciaP95FueraDeAcuerdo
            exp_alerts:
                - exp_labels: { severidad: critica }
                  exp_annotations:
                      summary: 'p95 por encima del acuerdo de 5 s'
                      description: 'El percentil 95 de duracion supera 5 s durante 5 minutos.'
          - eval_time: 15m
            alertname: LatenciaP95Degradada
            exp_alerts:
                - exp_labels: { severidad: advertencia }
                  exp_annotations:
                      summary: 'p95 degradado por encima de 500 ms'
                      description: 'El percentil 95 supera 500 ms durante 10 minutos; todavia dentro del acuerdo.'

    - interval: 1m
      input_series:
          - series: 'medschedule_http_request_duration_seconds_bucket{method="GET",route="login",le="0.5"}'
            values: '0+100x20'
          - series: 'medschedule_http_request_duration_seconds_bucket{method="GET",route="login",le="+Inf"}'
            values: '0+100x20'
      alert_rule_test:
          - eval_time: 15m
            alertname: LatenciaP95Degradada
            exp_alerts: []

    - interval: 1m
      input_series:
          - series: 'medschedule_http_requests_total{method="GET",route="login",status="500"}'
            values: '0+2x10'
          - series: 'medschedule_http_requests_total{method="GET",route="login",status="200"}'
            values: '0+98x10'
      alert_rule_test:
          - eval_time: 8m
            alertname: TasaErrores5xxAlta
            exp_alerts:
                - exp_labels: { severidad: critica }
                  exp_annotations:
                      summary: 'Mas de 1 % de respuestas 5xx'
                      description: 'La tasa de errores 5xx de los ultimos 5 minutos supera el 1 % acordado.'

    - interval: 1m
      input_series:
          - series: 'mysql_up{job="mysql",instance="mysqld-exporter:9104"}'
            values: '1 0 0 0'
      alert_rule_test:
          - eval_time: 3m
            alertname: BaseDeDatosCaida
            exp_alerts:
                - exp_labels: { severidad: critica, job: mysql, instance: 'mysqld-exporter:9104' }
                  exp_annotations:
                      summary: 'MySQL no responde'
                      description: 'El exporter no logra conectarse a MySQL desde hace mas de 1 minuto.'

    - interval: 1m
      input_series:
          - series: 'medschedule_jobs_fallidos{job="medschedule",instance="app"}'
            values: '0 1 1 1 1 1 1 1 1 1 1 1 1'
      alert_rule_test:
          - eval_time: 12m
            alertname: JobsFallidos
            exp_alerts:
                - exp_labels: { severidad: advertencia, job: medschedule, instance: app }
                  exp_annotations:
                      summary: 'Hay jobs fallidos'
                      description: 'La tabla failed_jobs tiene registros desde hace mas de 10 minutos.'
          - eval_time: 6m
            alertname: JobsFallidos
            exp_alerts: []

    - interval: 1m
      input_series:
          - series: 'medschedule_http_requests_total{method="GET",route="login",status="500"}'
            values: '0+5x10'
          - series: 'medschedule_http_requests_total{method="GET",route="login",status="200"}'
            values: '0+995x10'
      alert_rule_test:
          - eval_time: 8m
            alertname: TasaErrores5xxAlta
            exp_alerts: []

    - interval: 1m
      input_series:
          - series: 'mysql_up{job="mysql",instance="mysqld-exporter:9104"}'
            values: '1 0 0 0'
      alert_rule_test:
          - eval_time: 1m
            alertname: BaseDeDatosCaida
            exp_alerts: []

    - interval: 1m
      input_series:
          - series: 'medschedule_http_request_duration_seconds_bucket{method="GET",route="login",le="0.5"}'
            values: '0+10x20'
          - series: 'medschedule_http_request_duration_seconds_bucket{method="GET",route="login",le="2.5"}'
            values: '0+80x20'
          - series: 'medschedule_http_request_duration_seconds_bucket{method="GET",route="login",le="5"}'
            values: '0+96x20'
          - series: 'medschedule_http_request_duration_seconds_bucket{method="GET",route="login",le="+Inf"}'
            values: '0+100x20'
      alert_rule_test:
          - eval_time: 15m
            alertname: LatenciaP95Degradada
            exp_alerts:
                - exp_labels: { severidad: advertencia }
                  exp_annotations:
                      summary: 'p95 degradado por encima de 500 ms'
                      description: 'El percentil 95 supera 500 ms durante 10 minutos; todavia dentro del acuerdo.'
          - eval_time: 15m
            alertname: LatenciaP95FueraDeAcuerdo
            exp_alerts: []
```

- [ ] **Paso 2: Correr las pruebas y verificar que fallan**

```bash
open -a Docker
docker run --rm -v "$PWD/infra/monitoreo/prometheus:/reglas" -w /reglas --entrypoint promtool prom/prometheus:v3.14.0 test rules alertas.test.yml
```

Expected: FAIL (`alertas.yml: no such file or directory`).

- [ ] **Paso 3: Escribir las reglas**

`infra/monitoreo/prometheus/alertas.yml`:

```yaml
# Reglas de alerta de MedSchedule. Los umbrales salen de los niveles de servicio
# de la unidad 2 (docs/entrega-u2/03-niveles-de-servicio.md).
groups:
    - name: medschedule
      rules:
          - alert: AplicacionCaida
            expr: probe_success{job="disponibilidad"} == 0
            for: 1m
            labels:
                severidad: critica
            annotations:
                summary: 'MedSchedule no responde en /up'
                description: 'La sonda de disponibilidad falla desde hace mas de 1 minuto.'

          # SLO acordado: p95 < 5000 ms
          - alert: LatenciaP95FueraDeAcuerdo
            expr: histogram_quantile(0.95, sum by (le) (rate(medschedule_http_request_duration_seconds_bucket[5m]))) > 5
            for: 5m
            labels:
                severidad: critica
            annotations:
                summary: 'p95 por encima del acuerdo de 5 s'
                description: 'El percentil 95 de duracion supera 5 s durante 5 minutos.'

          # Aviso temprano: la U2 senalo que 5 s es holgado frente al p95 real de 76 ms
          - alert: LatenciaP95Degradada
            expr: histogram_quantile(0.95, sum by (le) (rate(medschedule_http_request_duration_seconds_bucket[5m]))) > 0.5
            for: 10m
            labels:
                severidad: advertencia
            annotations:
                summary: 'p95 degradado por encima de 500 ms'
                description: 'El percentil 95 supera 500 ms durante 10 minutos; todavia dentro del acuerdo.'

          # SLO acordado: http_req_failed < 1 %
          - alert: TasaErrores5xxAlta
            expr: sum(rate(medschedule_http_requests_total{status=~"5.."}[5m])) / sum(rate(medschedule_http_requests_total[5m])) > 0.01
            for: 1m
            labels:
                severidad: critica
            annotations:
                summary: 'Mas de 1 % de respuestas 5xx'
                description: 'La tasa de errores 5xx de los ultimos 5 minutos supera el 1 % acordado.'

          - alert: BaseDeDatosCaida
            expr: mysql_up == 0
            for: 1m
            labels:
                severidad: critica
            annotations:
                summary: 'MySQL no responde'
                description: 'El exporter no logra conectarse a MySQL desde hace mas de 1 minuto.'

          - alert: JobsFallidos
            expr: medschedule_jobs_fallidos > 0
            for: 10m
            labels:
                severidad: advertencia
            annotations:
                summary: 'Hay jobs fallidos'
                description: 'La tabla failed_jobs tiene registros desde hace mas de 10 minutos.'
```

- [ ] **Paso 4: Correr las pruebas y verificar que pasan**

Run: el mismo `docker run ... promtool test rules alertas.test.yml` del paso 2.
Expected: `SUCCESS`. Si un caso falla por el momento exacto de disparo (`eval_time`), ajustar el `eval_time` de la prueba razonando la ventana `rate[5m]` + `for`, nunca el umbral.

- [ ] **Paso 5: Commit**

```bash
git add infra/monitoreo/prometheus/alertas.yml infra/monitoreo/prometheus/alertas.test.yml
git diff --staged
git commit -m "feat(monitoreo): reglas de alerta ligadas a los niveles de servicio con pruebas de promtool"
```

### T008 [US2] Stack de monitoreo y dashboards como código

**Files:**
- Create: `infra/monitoreo/docker-compose.yml`, `infra/monitoreo/.env.example`
- Create: `infra/monitoreo/prometheus/prometheus.yml`
- Create: `infra/monitoreo/alertmanager/alertmanager.yml`
- Create: `infra/monitoreo/grafana/provisioning/datasources/monitoreo.yml`
- Create: `infra/monitoreo/grafana/provisioning/dashboards/medschedule.yml`
- Create: `infra/monitoreo/grafana/dashboards/servicio.json`, `negocio.json`, `base-de-datos.json`
- Create: `scripts/monitoreo-local.sh`

**Interfaces:**
- Consumes: `alertas.yml` (T007); `GET /metrics` con Bearer (T006); `GET /up` (Laravel).
- Produce: Grafana en `http://localhost:3000` con datasources de uid `prometheus` y `alertmanager`; carpeta de dashboards `MedSchedule`. T011 agrega Loki y Tempo a este mismo compose.

- [ ] **Paso 1: Compose**

`infra/monitoreo/docker-compose.yml`:

```yaml
# Stack de monitoreo de MedSchedule (unidad 3). Se levanta con scripts/monitoreo-local.sh.
# Todos los puertos se publican solo en 127.0.0.1: es un entorno local.
name: medschedule-monitoreo

services:
    prometheus:
        image: prom/prometheus:v3.14.0
        command:
            - --config.file=/etc/prometheus/prometheus.yml
            - --storage.tsdb.retention.time=15d
            - --web.enable-lifecycle
        volumes:
            - ./prometheus:/etc/prometheus:ro
            - datos_prometheus:/prometheus
        ports:
            - '127.0.0.1:9090:9090'
        extra_hosts:
            - 'host.docker.internal:host-gateway'
        restart: unless-stopped

    alertmanager:
        image: prom/alertmanager:v0.34.1
        command:
            - --config.file=/etc/alertmanager/alertmanager.yml
        volumes:
            - ./alertmanager:/etc/alertmanager:ro
        ports:
            - '127.0.0.1:9093:9093'
        restart: unless-stopped

    # Buzon SMTP falso: evidencia visible de que la alerta llego, sin correo real
    mailpit:
        image: axllent/mailpit:v1.31.2
        ports:
            - '127.0.0.1:8025:8025'
        restart: unless-stopped

    grafana:
        image: grafana/grafana:13.2.2
        environment:
            GF_SECURITY_ADMIN_USER: ${GRAFANA_ADMIN_USER:?falta GRAFANA_ADMIN_USER en infra/monitoreo/.env}
            GF_SECURITY_ADMIN_PASSWORD: ${GRAFANA_ADMIN_PASSWORD:?falta GRAFANA_ADMIN_PASSWORD en infra/monitoreo/.env}
            GF_USERS_ALLOW_SIGN_UP: 'false'
            # Lectura anonima solo en local, para capturas sin exponer credenciales
            GF_AUTH_ANONYMOUS_ENABLED: 'true'
            GF_AUTH_ANONYMOUS_ORG_ROLE: Viewer
        volumes:
            - ./grafana/provisioning:/etc/grafana/provisioning:ro
            - ./grafana/dashboards:/etc/grafana/dashboards:ro
            - datos_grafana:/var/lib/grafana
        ports:
            - '127.0.0.1:3000:3000'
        restart: unless-stopped

    blackbox-exporter:
        image: prom/blackbox-exporter:v0.28.0
        extra_hosts:
            - 'host.docker.internal:host-gateway'
        restart: unless-stopped

    mysqld-exporter:
        image: prom/mysqld-exporter:v0.20.0
        command:
            - --mysqld.address=${MYSQL_EXPORTER_HOST:-host.docker.internal:8889}
            - --mysqld.username=exporter
        environment:
            MYSQLD_EXPORTER_PASSWORD: ${MYSQL_EXPORTER_PASSWORD:?falta MYSQL_EXPORTER_PASSWORD en infra/monitoreo/.env}
        extra_hosts:
            - 'host.docker.internal:host-gateway'
        restart: unless-stopped

    # Almacen compartido de las metricas de la app entre peticiones
    redis:
        image: redis:8.8.3-alpine
        ports:
            - '127.0.0.1:6380:6379'
        restart: unless-stopped

volumes:
    datos_prometheus:
    datos_grafana:
```

`infra/monitoreo/.env.example`:

```dotenv
# Copiar a infra/monitoreo/.env (gitignored) y llenar. Nunca commitear el .env.
GRAFANA_ADMIN_USER=admin
GRAFANA_ADMIN_PASSWORD=
# Usuario de solo lectura para el exporter; se crea con el SQL de scripts/monitoreo-local.sh
MYSQL_EXPORTER_PASSWORD=
# MAMP local: host.docker.internal:8889. Codespace: mysql:3306
MYSQL_EXPORTER_HOST=host.docker.internal:8889
```

- [ ] **Paso 2: Prometheus y Alertmanager**

`infra/monitoreo/prometheus/prometheus.yml`:

```yaml
global:
    scrape_interval: 15s
    evaluation_interval: 15s

rule_files:
    - /etc/prometheus/alertas.yml

alerting:
    alertmanagers:
        - static_configs:
              - targets: ['alertmanager:9093']

scrape_configs:
    # Metricas de la aplicacion; el token lo escribe scripts/monitoreo-local.sh
    - job_name: medschedule
      metrics_path: /metrics
      authorization:
          type: Bearer
          credentials_file: /etc/prometheus/secretos/metrics_token
      static_configs:
          - targets: ['host.docker.internal:8000']

    # Disponibilidad vista desde afuera, contra la ruta de salud de Laravel
    - job_name: disponibilidad
      metrics_path: /probe
      params:
          module: [http_2xx]
      static_configs:
          - targets: ['http://host.docker.internal:8000/up']
      relabel_configs:
          - source_labels: [__address__]
            target_label: __param_target
          - source_labels: [__param_target]
            target_label: instance
          - target_label: __address__
            replacement: blackbox-exporter:9115

    - job_name: mysql
      static_configs:
          - targets: ['mysqld-exporter:9104']

    - job_name: prometheus
      static_configs:
          - targets: ['localhost:9090']
```

`infra/monitoreo/alertmanager/alertmanager.yml`:

```yaml
global:
    smtp_smarthost: 'mailpit:1025'
    smtp_from: 'alertas@medschedule.local'
    smtp_require_tls: false

route:
    receiver: correo-equipo
    group_by: [alertname]
    group_wait: 30s
    group_interval: 1m
    repeat_interval: 1h

receivers:
    - name: correo-equipo
      email_configs:
          - to: 'equipo@medschedule.local'
            send_resolved: true
```

- [ ] **Paso 3: Provisioning de Grafana**

`infra/monitoreo/grafana/provisioning/datasources/monitoreo.yml`:

```yaml
apiVersion: 1
datasources:
    - name: Prometheus
      type: prometheus
      uid: prometheus
      url: http://prometheus:9090
      isDefault: true
    - name: Alertmanager
      type: alertmanager
      uid: alertmanager
      url: http://alertmanager:9093
      jsonData:
          implementation: prometheus
```

`infra/monitoreo/grafana/provisioning/dashboards/medschedule.yml`:

```yaml
apiVersion: 1
providers:
    - name: medschedule
      folder: MedSchedule
      type: file
      allowUiUpdates: false
      options:
          path: /etc/grafana/dashboards
```

- [ ] **Paso 4: Dashboards**

`infra/monitoreo/grafana/dashboards/servicio.json`:

```json
{
	"uid": "medschedule-servicio",
	"title": "MedSchedule - Servicio",
	"schemaVersion": 39,
	"refresh": "15s",
	"time": { "from": "now-1h", "to": "now" },
	"panels": [
		{
			"type": "timeseries", "title": "Peticiones por segundo por ruta",
			"gridPos": { "h": 8, "w": 12, "x": 0, "y": 0 },
			"datasource": { "type": "prometheus", "uid": "prometheus" },
			"targets": [{ "refId": "A", "expr": "sum by (route) (rate(medschedule_http_requests_total[5m]))", "legendFormat": "{{route}}" }]
		},
		{
			"type": "timeseries", "title": "Tasa de errores 5xx (acuerdo < 1 %)",
			"gridPos": { "h": 8, "w": 12, "x": 12, "y": 0 },
			"datasource": { "type": "prometheus", "uid": "prometheus" },
			"fieldConfig": { "defaults": { "unit": "percentunit" } },
			"targets": [
				{ "refId": "A", "expr": "sum(rate(medschedule_http_requests_total{status=~\"5..\"}[5m])) / sum(rate(medschedule_http_requests_total[5m]))", "legendFormat": "errores 5xx" },
				{ "refId": "B", "expr": "vector(0.01)", "legendFormat": "acuerdo 1 %" }
			]
		},
		{
			"type": "timeseries", "title": "Duracion p50 / p95 / p99 contra el acuerdo de 5 s",
			"gridPos": { "h": 8, "w": 12, "x": 0, "y": 8 },
			"datasource": { "type": "prometheus", "uid": "prometheus" },
			"fieldConfig": { "defaults": { "unit": "s" } },
			"targets": [
				{ "refId": "A", "expr": "histogram_quantile(0.50, sum by (le) (rate(medschedule_http_request_duration_seconds_bucket[5m])))", "legendFormat": "p50" },
				{ "refId": "B", "expr": "histogram_quantile(0.95, sum by (le) (rate(medschedule_http_request_duration_seconds_bucket[5m])))", "legendFormat": "p95" },
				{ "refId": "C", "expr": "histogram_quantile(0.99, sum by (le) (rate(medschedule_http_request_duration_seconds_bucket[5m])))", "legendFormat": "p99" },
				{ "refId": "D", "expr": "vector(5)", "legendFormat": "acuerdo p95 (5 s)" }
			]
		},
		{
			"type": "stat", "title": "Disponibilidad ultima hora",
			"gridPos": { "h": 8, "w": 6, "x": 12, "y": 8 },
			"datasource": { "type": "prometheus", "uid": "prometheus" },
			"fieldConfig": { "defaults": { "unit": "percentunit", "decimals": 2 } },
			"targets": [{ "refId": "A", "expr": "avg_over_time(probe_success{job=\"disponibilidad\"}[1h])" }]
		},
		{
			"type": "table", "title": "Alertas activas",
			"gridPos": { "h": 8, "w": 6, "x": 18, "y": 8 },
			"datasource": { "type": "prometheus", "uid": "prometheus" },
			"targets": [{ "refId": "A", "expr": "ALERTS{alertstate=\"firing\"}", "format": "table", "instant": true }]
		}
	]
}
```

`infra/monitoreo/grafana/dashboards/negocio.json`:

```json
{
	"uid": "medschedule-negocio",
	"title": "MedSchedule - Negocio",
	"schemaVersion": 39,
	"refresh": "30s",
	"time": { "from": "now-24h", "to": "now" },
	"panels": [
		{
			"type": "timeseries", "title": "Citas agendadas y canceladas por hora",
			"gridPos": { "h": 8, "w": 12, "x": 0, "y": 0 },
			"datasource": { "type": "prometheus", "uid": "prometheus" },
			"targets": [
				{ "refId": "A", "expr": "increase(medschedule_citas_agendadas_total[1h])", "legendFormat": "agendadas" },
				{ "refId": "B", "expr": "increase(medschedule_citas_canceladas_total[1h])", "legendFormat": "canceladas" }
			]
		},
		{
			"type": "stat", "title": "Citas de hoy",
			"gridPos": { "h": 8, "w": 6, "x": 12, "y": 0 },
			"datasource": { "type": "prometheus", "uid": "prometheus" },
			"targets": [{ "refId": "A", "expr": "medschedule_citas_hoy" }]
		},
		{
			"type": "stat", "title": "Jobs fallidos",
			"gridPos": { "h": 8, "w": 6, "x": 18, "y": 0 },
			"datasource": { "type": "prometheus", "uid": "prometheus" },
			"fieldConfig": { "defaults": { "thresholds": { "mode": "absolute", "steps": [{ "color": "green", "value": null }, { "color": "red", "value": 1 }] } } },
			"targets": [{ "refId": "A", "expr": "medschedule_jobs_fallidos" }]
		}
	]
}
```

`infra/monitoreo/grafana/dashboards/base-de-datos.json`:

```json
{
	"uid": "medschedule-base-de-datos",
	"title": "MedSchedule - Base de datos",
	"schemaVersion": 39,
	"refresh": "15s",
	"time": { "from": "now-1h", "to": "now" },
	"panels": [
		{
			"type": "stat", "title": "MySQL disponible",
			"gridPos": { "h": 6, "w": 6, "x": 0, "y": 0 },
			"datasource": { "type": "prometheus", "uid": "prometheus" },
			"targets": [{ "refId": "A", "expr": "mysql_up" }]
		},
		{
			"type": "timeseries", "title": "Conexiones activas",
			"gridPos": { "h": 8, "w": 12, "x": 6, "y": 0 },
			"datasource": { "type": "prometheus", "uid": "prometheus" },
			"targets": [{ "refId": "A", "expr": "mysql_global_status_threads_connected", "legendFormat": "conexiones" }]
		},
		{
			"type": "timeseries", "title": "Consultas por segundo y consultas lentas",
			"gridPos": { "h": 8, "w": 12, "x": 0, "y": 8 },
			"datasource": { "type": "prometheus", "uid": "prometheus" },
			"targets": [
				{ "refId": "A", "expr": "rate(mysql_global_status_queries[5m])", "legendFormat": "consultas/s" },
				{ "refId": "B", "expr": "rate(mysql_global_status_slow_queries[5m])", "legendFormat": "lentas/s" }
			]
		}
	]
}
```

- [ ] **Paso 5: Script de arranque**

`scripts/monitoreo-local.sh`:

```bash
#!/usr/bin/env bash
# Levanta el stack de monitoreo (Prometheus, Alertmanager, Grafana, exporters,
# Redis y Mailpit) y espera a que responda. No imprime secretos.
#
# Requisitos: Docker corriendo, infra/monitoreo/.env lleno a partir de
# .env.example, METRICS_TOKEN en el .env de la aplicacion, y la aplicacion
# escuchando en el puerto 8000 (php artisan serve --host=0.0.0.0 --port=8000).
set -euo pipefail

raiz="$(cd "$(dirname "$0")/.." && pwd)"
dir="${raiz}/infra/monitoreo"

if [ ! -f "${dir}/.env" ]; then
    echo "Falta infra/monitoreo/.env: copiar infra/monitoreo/.env.example y llenarlo." >&2
    exit 1
fi

# El token sale del .env de la app y se deja en un archivo que solo monta
# Prometheus. La carpeta secretos/ esta en .gitignore.
token="$(grep -E '^METRICS_TOKEN=' "${raiz}/.env" | head -1 | cut -d= -f2- || true)"
if [ -z "${token}" ]; then
    echo "Falta METRICS_TOKEN en el .env de la aplicacion." >&2
    exit 1
fi
mkdir -p "${dir}/prometheus/secretos"
# 644 porque Prometheus corre como otro usuario dentro del contenedor; el
# archivo es local y esta fuera del control de versiones.
printf '%s' "${token}" > "${dir}/prometheus/secretos/metrics_token"
chmod 644 "${dir}/prometheus/secretos/metrics_token"

docker compose -f "${dir}/docker-compose.yml" --env-file "${dir}/.env" up -d

esperar() {
    local url="$1"
    for _ in $(seq 1 45); do
        if curl -sf "${url}" > /dev/null; then
            echo "  listo: ${url}"
            return 0
        fi
        sleep 2
    done
    echo "  sin respuesta: ${url}" >&2
    return 1
}

echo "==> Esperando a los servicios"
esperar "http://localhost:9090/-/ready"
esperar "http://localhost:9093/-/ready"
esperar "http://localhost:3000/api/health"

echo ""
echo "Prometheus:   http://localhost:9090"
echo "Alertmanager: http://localhost:9093"
echo "Grafana:      http://localhost:3000"
echo "Mailpit:      http://localhost:8025"
```

```bash
chmod +x scripts/monitoreo-local.sh
```

- [ ] **Paso 6: Validar la configuración sin levantar nada**

```bash
cp infra/monitoreo/.env.example /tmp/monitoreo-validar.env
sed -i '' 's/^GRAFANA_ADMIN_PASSWORD=.*/GRAFANA_ADMIN_PASSWORD=x/; s/^MYSQL_EXPORTER_PASSWORD=.*/MYSQL_EXPORTER_PASSWORD=x/' /tmp/monitoreo-validar.env
docker compose -f infra/monitoreo/docker-compose.yml --env-file /tmp/monitoreo-validar.env config -q && echo "compose ok"
rm /tmp/monitoreo-validar.env
mkdir -p infra/monitoreo/prometheus/secretos && printf 'x' > infra/monitoreo/prometheus/secretos/metrics_token
docker run --rm -v "$PWD/infra/monitoreo/prometheus:/etc/prometheus" --entrypoint promtool prom/prometheus:v3.14.0 check config /etc/prometheus/prometheus.yml
for f in infra/monitoreo/grafana/dashboards/*.json; do python3 -m json.tool "$f" > /dev/null && echo "json ok: $f"; done
bash -n scripts/monitoreo-local.sh && echo "script ok"
```

Expected: `compose ok`, `SUCCESS` de promtool (config y 1 archivo de reglas), `json ok` ×3, `script ok`.

- [ ] **Paso 7: Levantar de verdad y comprobar los objetivos**

El usuario crea `infra/monitoreo/.env` a partir del ejemplo y agrega `METRICS_TOKEN` a su `.env` (generado con `php -r "echo bin2hex(random_bytes(24));"`). Crear el usuario del exporter en MySQL de MAMP, leyendo la contraseña del `.env` sin imprimirla:

```bash
( set -a; . infra/monitoreo/.env; set +a
  /Applications/MAMP/Library/bin/mysql80/bin/mysql -h127.0.0.1 -P8889 -uroot -p"$MAMP_DB_PASSWORD" -e \
  "CREATE USER IF NOT EXISTS 'exporter'@'%' IDENTIFIED BY '${MYSQL_EXPORTER_PASSWORD}' WITH MAX_USER_CONNECTIONS 3;
   GRANT PROCESS, REPLICATION CLIENT, SELECT ON *.* TO 'exporter'@'%';" )
```

(Si la ruta del cliente `mysql` de MAMP es otra, localizarla con `ls /Applications/MAMP/Library/bin/`.)

```bash
php artisan serve --host=0.0.0.0 --port=8000 &   # en otra terminal
bash scripts/monitoreo-local.sh
curl -s 'http://localhost:9090/api/v1/targets' | python3 -c 'import json,sys;[print(t["labels"]["job"], t["health"], t.get("lastError","")) for t in json.load(sys.stdin)["data"]["activeTargets"]]'
```

Expected: los cuatro jobs (`medschedule`, `disponibilidad`, `mysql`, `prometheus`) con `up`. Abrir Grafana → carpeta MedSchedule: los tres dashboards muestran datos después de navegar un poco por la app.

- [ ] **Paso 8: Commit**

```bash
git status --short   # comprobar que ni infra/monitoreo/.env ni prometheus/secretos/ aparecen
git add infra/monitoreo scripts/monitoreo-local.sh
git diff --staged --stat
git diff --staged | grep -iE 'password=.+|token=.+' || echo "sin secretos"
git commit -m "feat(monitoreo): stack de Prometheus, Alertmanager y Grafana con dashboards como codigo"
```

### T009 [US2] Evidencia antes/después del monitoreo

**Files:**
- Create: `docs/entrega-u3/evidencia/monitoreo-01-servicio.png`, `monitoreo-02-negocio.png`, `monitoreo-03-base-de-datos.png`, `monitoreo-04-alerta-antes.png`, `monitoreo-05-alerta-grafana.png`, `monitoreo-06-correo-mailpit.png`, `monitoreo-alerta-tiempos.txt`

- [ ] **Paso 1: Generar tráfico real**

Con la app y el stack arriba, correr la prueba de carga de la U2 contra local durante 2 minutos (`npm run carga:humo` o el script de k6 de `tests/carga/`), con `K6_PASSWORD` del usuario de pruebas.

- [ ] **Paso 2: "Antes": sin monitoreo nadie se entera**

Documentar el comportamiento previo: con el stack detenido (`docker compose -f infra/monitoreo/docker-compose.yml stop`), detener `php artisan serve`; no hay aviso de ningún tipo. Captura `monitoreo-04-alerta-antes.png` de la terminal o del navegador con la app caída y sin notificación.

- [ ] **Paso 3: "Después": la alerta dispara y llega el correo**

```bash
docker compose -f infra/monitoreo/docker-compose.yml --env-file infra/monitoreo/.env start
date -u +"inicio caida: %H:%M:%S" | tee docs/entrega-u3/evidencia/monitoreo-alerta-tiempos.txt
# detener php artisan serve; esperar
```

Refrescar `http://localhost:9090/alerts` hasta ver `AplicacionCaida` en `firing` y Mailpit (`http://localhost:8025`) hasta ver el correo. Anotar la hora de llegada:

```bash
date -u +"correo recibido: %H:%M:%S" | tee -a docs/entrega-u3/evidencia/monitoreo-alerta-tiempos.txt
```

Criterio SC-003: menos de 3 minutos entre ambas horas. Capturas `monitoreo-05-alerta-grafana.png` (dashboard Servicio con disponibilidad cayendo y alerta en la tabla) y `monitoreo-06-correo-mailpit.png`. Volver a levantar la app y capturar el correo de resolución si llega.

- [ ] **Paso 4: Capturas de los tres dashboards con datos**

`monitoreo-01-servicio.png`, `monitoreo-02-negocio.png`, `monitoreo-03-base-de-datos.png` (Grafana anónimo en modo lectura, sin credenciales en pantalla).

- [ ] **Paso 5: Commit**

```bash
git add docs/entrega-u3/evidencia/monitoreo-*
git commit -m "docs(entrega): evidencia antes y despues del monitoreo y sus alertas"
```

**Checkpoint**: US2 completa; PR del punto 1 listo para abrir (T022).

---

## Phase 4: User Story 3 — Visor de trazabilidad (Priority: P3) — rama `feat/<R19>-trazabilidad` (sale de `feat/<R18>-monitoreo`)

```bash
git switch -c feat/<R19>-trazabilidad feat/<R18>-monitoreo
```

### T010 [US3] Servicio de trazas y middleware `IniciarTraza`

**Files:**
- Create: `config/trazas.php`
- Create: `app/Observability/Trazas/Trazas.php`
- Create: `app/Http/Middleware/IniciarTraza.php`
- Create: `app/Providers/TrazasServiceProvider.php`
- Modify: `bootstrap/providers.php`, `bootstrap/app.php`, `phpunit.xml`, `.env.example`, `composer.json`/`composer.lock`
- Test: `tests/Feature/Trazas/TrazasTestCase.php`, `tests/Feature/Trazas/IniciarTrazaTest.php`

**Interfaces:**
- Produce: `App\Observability\Trazas\Trazas` (singleton) con
  - `tracer(): OpenTelemetry\API\Trace\TracerInterface`
  - `trace_id_actual(): ?string` y `span_id_actual(): ?string`
  - `en_span(string $nombre, callable $accion, array $atributos = []): mixed`
  - `vaciar(): void`
- Produce: atributos de la petición `trace_id` y `request_id` (`$request->attributes`), que usan T011 y la auditoría (T015).
- Produce: cabecera de respuesta `X-Trace-Id`.

- [ ] **Paso 1: Instalar dependencias**

```bash
composer config allow-plugins.php-http/discovery true
composer config allow-plugins.tbachert/spi true
composer require open-telemetry/sdk:^1.15 open-telemetry/exporter-otlp:^1.4
php -r 'require "vendor/autoload.php"; foreach (["OpenTelemetry\\SDK\\Trace\\TracerProvider","OpenTelemetry\\SDK\\Trace\\SpanProcessor\\BatchSpanProcessor","OpenTelemetry\\SDK\\Trace\\SpanProcessor\\SimpleSpanProcessor","OpenTelemetry\\SDK\\Trace\\SpanExporter\\InMemoryExporter","OpenTelemetry\\Contrib\\Otlp\\SpanExporter","OpenTelemetry\\Contrib\\Otlp\\OtlpHttpTransportFactory","OpenTelemetry\\API\\Trace\\NoopTracerProvider","OpenTelemetry\\API\\Trace\\Propagation\\TraceContextPropagator","OpenTelemetry\\SDK\\Resource\\ResourceInfo","OpenTelemetry\\SDK\\Resource\\ResourceInfoFactory","OpenTelemetry\\SDK\\Common\\Attribute\\Attributes","OpenTelemetry\\SDK\\Trace\\Sampler\\ParentBased","OpenTelemetry\\SDK\\Trace\\Sampler\\TraceIdRatioBasedSampler"] as $c) echo (class_exists($c) ? "ok  " : "FALTA ") . $c . PHP_EOL;'
```

Expected: `ok` en las 13 clases. Si alguna dice `FALTA`, buscar su nombre actual en `vendor/open-telemetry/` y usarlo en el código de esta tarea.

- [ ] **Paso 2: Configuración**

`config/trazas.php`:

```php
<?php

// Trazas OpenTelemetry. Apagadas por defecto: las pruebas y el CI no necesitan Tempo.
return [
	'habilitadas' => (bool) env('OTEL_ENABLED', false),
	'endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://127.0.0.1:4318'),
	'muestreo' => (float) env('OTEL_TRACES_SAMPLER_ARG', 1.0),
	'servicio' => env('OTEL_SERVICE_NAME', 'medschedule'),
];
```

`phpunit.xml`: `<env name="OTEL_ENABLED" value="false"/>`.

`.env.example`, al final:

```dotenv
# Trazas OpenTelemetry hacia Tempo (unidad 3)
OTEL_ENABLED=false
OTEL_EXPORTER_OTLP_ENDPOINT=http://127.0.0.1:4318
OTEL_TRACES_SAMPLER_ARG=1.0
OTEL_SERVICE_NAME=medschedule
```

- [ ] **Paso 3: Base de pruebas con exportador en memoria**

`tests/Feature/Trazas/TrazasTestCase.php`:

```php
<?php

namespace Tests\Feature\Trazas;

use App\Observability\Trazas\Trazas;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Tests\TestCase;

// Sustituye el proveedor de trazas por uno que guarda los spans en memoria
abstract class TrazasTestCase extends TestCase
{
	protected InMemoryExporter $exportador;

	protected function setUp(): void
	{
		parent::setUp();

		$this->exportador = new InMemoryExporter();
		$proveedor = TracerProvider::builder()
			->addSpanProcessor(new SimpleSpanProcessor($this->exportador))
			->build();

		config(['trazas.habilitadas' => true]);
		$this->app->instance(TracerProviderInterface::class, $proveedor);
		$this->app->forgetInstance(Trazas::class);
	}

	// Devuelve los spans exportados cuyo nombre empieza con $prefijo
	protected function spans_que_empiezan_con(string $prefijo): array
	{
		return array_values(array_filter(
			$this->exportador->getSpans(),
			fn ($span) => str_starts_with($span->getName(), $prefijo)
		));
	}
}
```

- [ ] **Paso 4: Escribir la prueba que falla**

`tests/Feature/Trazas/IniciarTrazaTest.php`:

```php
<?php

namespace Tests\Feature\Trazas;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

class IniciarTrazaTest extends TrazasTestCase
{
	use RefreshDatabase;

	public function test_peticion_genera_span_raiz_y_cabecera_x_trace_id(): void
	{
		$respuesta = $this->get(route('login'));

		$respuesta->assertOk();
		$trace_id = $respuesta->headers->get('X-Trace-Id');
		$this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', (string) $trace_id);

		$raices = $this->spans_que_empiezan_con('GET ');
		$this->assertCount(1, $raices);
		$this->assertSame($trace_id, $raices[0]->getTraceId());
		$this->assertSame('login', $raices[0]->getAttributes()->get('http.route'));
		$this->assertSame(200, $raices[0]->getAttributes()->get('http.response.status_code'));
	}

	public function test_respeta_traceparent_entrante(): void
	{
		$padre = '4bf92f3577b34da6a3ce929d0e0e4736';

		$respuesta = $this->withHeader('traceparent', "00-{$padre}-00f067aa0ba902b7-01")->get(route('login'));

		$this->assertSame($padre, $respuesta->headers->get('X-Trace-Id'));
	}

	// La ruta real puede llevar tokens (reset-password/{token}); solo se guarda la plantilla
	public function test_ningun_span_guarda_la_ruta_real_con_tokens(): void
	{
		Route::middleware('web')->get('/_prueba/reset/{token}', fn () => 'ok');

		$this->get('/_prueba/reset/TOKEN-SECRETO-123')->assertOk();

		$spans = $this->exportador->getSpans();
		$this->assertNotEmpty($spans);
		foreach ($spans as $span) {
			$this->assertStringNotContainsString('TOKEN-SECRETO-123', $span->getName());
			foreach ($span->getAttributes()->toArray() as $valor) {
				$this->assertStringNotContainsString('TOKEN-SECRETO-123', (string) $valor);
			}
		}
		$raiz = $this->spans_que_empiezan_con('GET ')[0];
		$this->assertSame('_prueba/reset/{token}', $raiz->getAttributes()->get('url.template'));
	}

	// El scrape de Prometheus (cada 15 s) no se traza
	public function test_scrape_de_metricas_no_produce_spans(): void
	{
		$this->withToken('token-solo-para-pruebas')->get('/metrics')->assertOk();

		$this->assertCount(0, $this->exportador->getSpans());
	}
}
```

- [ ] **Paso 5: Correr la prueba y verificar que falla**

Run: `pruebas --filter=IniciarTrazaTest`
Expected: FAIL (`Target class [App\Observability\Trazas\Trazas] does not exist`).

- [ ] **Paso 6: Implementar `Trazas`**

`app/Observability/Trazas/Trazas.php`:

```php
<?php

namespace App\Observability\Trazas;

use Illuminate\Support\Facades\Log;
use App\Support\MensajeSeguro;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface as ProveedorSdk;
use Throwable;

// Fachada minima sobre OpenTelemetry para el resto de la aplicacion
class Trazas
{
	public function __construct(private TracerProviderInterface $proveedor)
	{
	}

	public function tracer(): TracerInterface
	{
		return $this->proveedor->getTracer('medschedule');
	}

	public function trace_id_actual(): ?string
	{
		$contexto = Span::getCurrent()->getContext();

		return $contexto->isValid() ? $contexto->getTraceId() : null;
	}

	public function span_id_actual(): ?string
	{
		$contexto = Span::getCurrent()->getContext();

		return $contexto->isValid() ? $contexto->getSpanId() : null;
	}

	// Ejecuta $accion dentro de un span hijo del span activo
	public function en_span(string $nombre, callable $accion, array $atributos = []): mixed
	{
		$span = $this->tracer()
			->spanBuilder($nombre)
			->setSpanKind(SpanKind::KIND_CLIENT)
			->setAttributes($atributos)
			->startSpan();
		$alcance = $span->activate();

		try {
			return $accion();
		} catch (Throwable $error) {
			// No se usa recordException(): guarda el stacktrace con argumentos
			// y, en una QueryException, exception.message trae los bindings
			// (getMessage() los interpola). addEvent() con MensajeSeguro deja
			// el mismo evento 'exception' pero sin esos valores.
			$span->addEvent('exception', [
				'exception.type' => $error::class,
				'exception.message' => MensajeSeguro::de_excepcion($error),
			]);
			$span->setStatus(StatusCode::STATUS_ERROR);
			throw $error;
		} finally {
			$alcance->detach();
			$span->end();
		}
	}

	// Envia los spans pendientes; se llama al terminar la peticion o el job
	public function vaciar(): void
	{
		if (!$this->proveedor instanceof ProveedorSdk) {
			return;
		}

		try {
			$this->proveedor->forceFlush();
		} catch (Throwable $error) {
			// Si Tempo no responde, la peticion ya se atendio; solo se avisa
			Log::warning('No se pudieron enviar las trazas', ['error' => $error->getMessage()]);
		}
	}
}
```

- [ ] **Paso 7: Implementar el middleware**

`app/Http/Middleware/IniciarTraza.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Observability\Trazas\Trazas;
use App\Support\MensajeSeguro;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

// Abre el span raiz de cada peticion y deja el trace_id disponible para
// logs, auditoria y la pagina de error
class IniciarTraza
{
	public function __construct(private Trazas $trazas)
	{
	}

	public function handle(Request $request, Closure $next): Response
	{
		// El scrape de Prometheus (ruta 'metricas', cada 15 s) no se traza: solo
		// generaria ruido y costo de exportacion. Conserva su request_id.
		if ($request->is('metrics')) {
			$request->attributes->set('request_id', (string) Str::uuid());

			return $next($request);
		}

		// El traceparent entrante se acepta tal cual: un cliente puede fijar el
		// trace_id o marcarlo como no muestreado. Aceptable en local; en
		// produccion deberia validarse o reescribirse en el borde (proxy/WAF).

		// Las cabeceras llegan como arreglos; el propagador espera cadenas
		$cabeceras = array_map(
			fn ($valor) => is_array($valor) ? implode(',', $valor) : $valor,
			$request->headers->all()
		);
		$contexto_padre = TraceContextPropagator::getInstance()->extract($cabeceras);

		$span = $this->trazas->tracer()
			->spanBuilder('HTTP ' . $request->method())
			->setParent($contexto_padre)
			->setSpanKind(SpanKind::KIND_SERVER)
			->setAttribute('http.request.method', $request->method())
			->startSpan();
		$alcance = $span->activate();

		$trace_id = $span->getContext()->isValid() ? $span->getContext()->getTraceId() : null;
		$request->attributes->set('trace_id', $trace_id);
		$request->attributes->set('request_id', (string) Str::uuid());

		try {
			$respuesta = $next($request);

			$ruta = $request->route();
			$span->updateName($request->method() . ' /' . ltrim($ruta?->uri() ?? 'sin_ruta', '/'));
			$span->setAttribute('http.route', $ruta?->getName() ?? $ruta?->uri() ?? 'sin_ruta');
			// Solo la plantilla (reset-password/{token}), nunca la ruta real:
			// la ruta real puede llevar tokens o firmas en sus parametros
			if ($ruta) {
				$span->setAttribute('url.template', $ruta->uri());
			}
			$span->setAttribute('http.response.status_code', $respuesta->getStatusCode());
			if ($request->user()) {
				$span->setAttribute('enduser.id', (string) $request->user()->getAuthIdentifier());
			}
			if ($respuesta->getStatusCode() >= 500) {
				$span->setStatus(StatusCode::STATUS_ERROR);
			}
			if ($trace_id !== null) {
				$respuesta->headers->set('X-Trace-Id', $trace_id);
			}

			return $respuesta;
		} catch (Throwable $error) {
			// Ver Trazas::en_span: addEvent() + MensajeSeguro en lugar de
			// recordException() para no exportar valores de consulta a Tempo.
			$span->addEvent('exception', [
				'exception.type' => $error::class,
				'exception.message' => MensajeSeguro::de_excepcion($error),
			]);
			$span->setStatus(StatusCode::STATUS_ERROR);
			throw $error;
		} finally {
			$alcance->detach();
			$span->end();
		}
	}

	public function terminate(Request $request, Response $response): void
	{
		$this->trazas->vaciar();
	}
}
```

- [ ] **Paso 8: Provider y registro**

`app/Providers/TrazasServiceProvider.php`:

```php
<?php

namespace App\Providers;

use App\Observability\Trazas\Trazas;
use Illuminate\Support\ServiceProvider;
use OpenTelemetry\API\Trace\NoopTracerProvider;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

class TrazasServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->app->singleton(TracerProviderInterface::class, function () {
			// Apagadas: proveedor nulo, sin costo ni conexiones
			if (!config('trazas.habilitadas')) {
				return new NoopTracerProvider();
			}

			// Tempo inalcanzable no debe frenar la peticion: con los valores por
			// defecto (timeout 10 s, 3 reintentos) vaciar() bloqueaba ~40 s.
			// Timeout de 1 s (segundos, se pasa al cliente HTTP) y sin reintentos:
			// si falla, esos spans se pierden y solo queda el aviso en el log.
			$transporte = (new OtlpHttpTransportFactory())->create(
				rtrim((string) config('trazas.endpoint'), '/') . '/v1/traces',
				'application/json',
				timeout: 1.0,
				maxRetries: 0,
			);
			$recurso = ResourceInfoFactory::emptyResource()->merge(
				ResourceInfo::create(Attributes::create(['service.name' => config('trazas.servicio')]))
			);

			return TracerProvider::builder()
				->addSpanProcessor(BatchSpanProcessor::builder(new SpanExporter($transporte))->build())
				->setResource($recurso)
				->setSampler(new ParentBased(new TraceIdRatioBasedSampler((float) config('trazas.muestreo'))))
				->build();
		});

		$this->app->singleton(Trazas::class, fn ($app) => new Trazas($app->make(TracerProviderInterface::class)));
	}
}
```

`bootstrap/providers.php`: agregar `App\Providers\TrazasServiceProvider::class,`.

`bootstrap/app.php`, dentro de `withMiddleware`, como **primera** línea (debe envolver a todo lo demás, métricas incluidas):

```php
		// Span raiz de cada peticion (unidad 3); va primero para envolver al resto
		$middleware->prepend(\App\Http\Middleware\IniciarTraza::class);
```

- [ ] **Paso 9: Correr las pruebas y verificar que pasan**

Run: `pruebas --filter="IniciarTrazaTest|Metricas"`
Expected: PASS (las 4 de trazas y las de métricas). El span guarda `url.template` (la plantilla de la ruta, p. ej. `reset-password/{token}`) y nunca la ruta real; `/metrics` no abre span.

Comprobación manual del timeout del exportador (no hay prueba automática sin red): con `OTEL_ENABLED=true` y `OTEL_EXPORTER_OTLP_ENDPOINT=http://10.255.255.1:4318` (IP no enrutable), medir una llamada a `app(Trazas::class)->vaciar()` después de crear un span. Expected: ≲ 2 s (con los valores por defecto del SDK tardaba ~40 s).

- [ ] **Paso 10: Commit**

```bash
git add config/trazas.php app/Observability/Trazas app/Http/Middleware/IniciarTraza.php app/Providers/TrazasServiceProvider.php bootstrap phpunit.xml .env.example composer.json composer.lock tests/Feature/Trazas
git diff --staged
git commit -m "feat(trazas): span raiz por peticion con OpenTelemetry y cabecera X-Trace-Id"
```

### T011 [US3] Spans de consultas, jobs y Google Calendar

**Files:**
- Modify: `app/Providers/TrazasServiceProvider.php` (`boot`)
- Modify: `app/Services/GoogleCalendarService.php` (tres llamadas a `$service->events->...`)
- Test: `tests/Feature/Trazas/SpansHijosTest.php`

**Interfaces:**
- Consumes: `Trazas::tracer()`, `Trazas::en_span()`, `Trazas::vaciar()` de T010.

- [ ] **Paso 1: Escribir la prueba que falla**

`tests/Feature/Trazas/SpansHijosTest.php`:

```php
<?php

namespace Tests\Feature\Trazas;

use App\Observability\Trazas\Trazas;
use App\Models\User;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;

class SpansHijosTest extends TrazasTestCase
{
	use RefreshDatabase;

	public function test_consultas_de_la_peticion_son_spans_hijos_sin_bindings(): void
	{
		$usuario = User::factory()->create(['email' => 'paciente.secreto@example.com']);

		\Illuminate\Support\Facades\Route::middleware('web')->get('/_prueba/consulta', function () {
			DB::table('users')->where('email', 'paciente.secreto@example.com')->first();
			return 'ok';
		});

		$this->get('/_prueba/consulta')->assertOk();

		$raiz = $this->spans_que_empiezan_con('GET ')[0];
		$consultas = $this->spans_que_empiezan_con('db.query');

		$this->assertNotEmpty($consultas);
		foreach ($consultas as $consulta) {
			$this->assertSame($raiz->getTraceId(), $consulta->getTraceId());
			$this->assertStringNotContainsString('paciente.secreto', (string) $consulta->getAttributes()->get('db.statement'));
		}
		$this->assertTrue(collect($consultas)->contains(
			fn ($s) => str_contains((string) $s->getAttributes()->get('db.statement'), 'where `email` = ?')
		));
	}

	public function test_job_genera_su_propio_span(): void
	{
		config(['queue.default' => 'sync']);

		dispatch(function () {
			DB::select('select 1');
		});

		$this->assertNotEmpty($this->spans_que_empiezan_con('job '));
	}

	public function test_en_span_registra_y_propaga_excepciones(): void
	{
		$trazas = app(Trazas::class);

		try {
			$trazas->en_span('google_calendar.prueba', fn () => throw new RuntimeException('fallo externo'));
			$this->fail('Debio propagar la excepcion');
		} catch (RuntimeException $error) {
			$this->assertSame('fallo externo', $error->getMessage());
		}

		$span = $this->spans_que_empiezan_con('google_calendar.prueba')[0];
		$this->assertSame('Error', $span->getStatus()->getCode());
	}

	// Simula el camino de Worker::handleJobException: al job le quedan intentos,
	// Laravel dispara JobExceptionOccurred y lo libera de vuelta a la cola SIN
	// disparar JobProcessed ni JobFailed. El span del job debe cerrarse igual.
	public function test_reintento_de_job_cierra_su_span_via_exception_occurred(): void
	{
		$job = Mockery::mock(Job::class);
		$job->shouldReceive('getJobId')->andReturn('job-de-prueba-1');
		$job->shouldReceive('resolveName')->andReturn('App\\Jobs\\JobDePrueba');
		$job->shouldReceive('getQueue')->andReturn('default');
		$job->shouldReceive('payload')->andReturn([]);

		event(new JobProcessing('sync', $job));
		event(new JobExceptionOccurred('sync', $job, new RuntimeException('fallo con reintento pendiente')));

		$spans = $this->spans_que_empiezan_con('job ');
		$this->assertNotEmpty($spans, 'El span del job debio exportarse aunque el job se libere para reintento');
		$this->assertSame('Error', $spans[0]->getStatus()->getCode());
		$this->assertNull(app(Trazas::class)->trace_id_actual(), 'El scope del span del job debio liberarse');
	}
}
```

- [ ] **Paso 2: Correr la prueba y verificar que falla**

Run: `pruebas --filter=SpansHijosTest`
Expected: FAIL en la de consultas, la de job y la de reintento (no hay spans `db.query` ni `job `); la de `en_span` ya pasa.

- [ ] **Paso 3: Registrar consultas y jobs en el provider**

Agregar a `TrazasServiceProvider`:

```php
	public function boot(): void
	{
		$this->trazar_consultas();
		$this->trazar_jobs();
	}

	// Cada consulta SQL es un span hijo del span activo. Solo la sentencia
	// parametrizada: los bindings pueden contener PII y nunca se guardan.
	private function trazar_consultas(): void
	{
		\Illuminate\Support\Facades\DB::listen(function (\Illuminate\Database\Events\QueryExecuted $consulta) {
			if (!\OpenTelemetry\API\Trace\Span::getCurrent()->isRecording()) {
				return;
			}

			$fin = (int) (microtime(true) * 1e9);
			$inicio = $fin - (int) ($consulta->time * 1e6);

			$span = app(Trazas::class)->tracer()
				->spanBuilder('db.query')
				->setSpanKind(\OpenTelemetry\API\Trace\SpanKind::KIND_CLIENT)
				->setStartTimestamp($inicio)
				->setAttribute('db.system', $consulta->connection->getDriverName())
				->setAttribute('db.statement', $consulta->sql)
				->setAttribute('db.connection', $consulta->connectionName)
				->startSpan();
			$span->end($fin);
		});
	}

	// Cada job de cola abre un span propio y lo envia al terminar
	private function trazar_jobs(): void
	{
		$abiertos = [];

		\Illuminate\Support\Facades\Queue::before(function (\Illuminate\Queue\Events\JobProcessing $evento) use (&$abiertos) {
			if (!config('trazas.habilitadas')) {
				return;
			}
			$span = app(Trazas::class)->tracer()
				->spanBuilder('job ' . $evento->job->resolveName())
				->setSpanKind(\OpenTelemetry\API\Trace\SpanKind::KIND_CONSUMER)
				->setAttribute('messaging.destination.name', $evento->job->getQueue())
				->startSpan();
			$abiertos[$evento->job->getJobId() ?? spl_object_id($evento->job)] = [$span, $span->activate()];
		});

		$cerrar = function ($evento, ?\Throwable $error = null) use (&$abiertos) {
			$clave = $evento->job->getJobId() ?? spl_object_id($evento->job);
			if (!isset($abiertos[$clave])) {
				return;
			}
			[$span, $alcance] = $abiertos[$clave];
			unset($abiertos[$clave]);
			if ($error !== null) {
				$span->recordException($error);
				$span->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR);
			}
			$alcance->detach();
			$span->end();
			app(Trazas::class)->vaciar();
		};

		\Illuminate\Support\Facades\Queue::after(fn (\Illuminate\Queue\Events\JobProcessed $evento) => $cerrar($evento));
		\Illuminate\Support\Facades\Queue::failing(fn (\Illuminate\Queue\Events\JobFailed $evento) => $cerrar($evento, $evento->exception));
		// Si al job le quedan reintentos, Laravel lo libera de vuelta a la cola sin
		// disparar JobProcessed ni JobFailed: el guard evita el doble cierre cuando,
		// en el ultimo intento, JobFailed ya cerro el mismo span antes que este evento.
		\Illuminate\Support\Facades\Queue::exceptionOccurred(fn (\Illuminate\Queue\Events\JobExceptionOccurred $evento) => $cerrar($evento, $evento->exception));
	}
```

(Pasar los `use` al encabezado del archivo en lugar de nombres completos si el estilo del archivo lo prefiere; el comportamiento es el mismo.)

- [ ] **Paso 4: Envolver las llamadas a Google Calendar**

En `app/Services/GoogleCalendarService.php`, envolver cada llamada externa sin cambiar su lógica. Ejemplo para `insert` (línea ~67):

```php
			$createdEvent = app(\App\Observability\Trazas\Trazas::class)->en_span(
				'google_calendar.events.insert',
				fn () => $service->events->insert($calendarId, $event, [
					// ... mismos parametros que hoy ...
				]),
				['peer.service' => 'google-calendar']
			);
```

Igual para `$service->events->delete(...)` (`google_calendar.events.delete`) y `$service->events->listEvents(...)` (`google_calendar.events.list`). Correr la prueba existente para no romper nada:

Run: `pruebas --filter=GoogleCalendarControllerTest`
Expected: mismo resultado que en la línea base de T002.

- [ ] **Paso 5: Correr las pruebas y verificar que pasan**

Run: `pruebas --filter="SpansHijosTest|IniciarTrazaTest"`
Expected: PASS (8 pruebas). El span del job se cierra en `JobProcessed`, `JobFailed` y `JobExceptionOccurred` (reintento pendiente). Si `getStatus()->getCode()` devuelve la constante en otro formato, comparar con `\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR`.

- [ ] **Paso 6: Commit**

```bash
git add app/Providers/TrazasServiceProvider.php app/Services/GoogleCalendarService.php tests/Feature/Trazas/SpansHijosTest.php
git diff --staged
git commit -m "feat(trazas): spans de consultas sin bindings, jobs y llamadas a Google Calendar"
```

### T012 [US3] Logs estructurados con contexto de traza y redacción de PII

**Files:**
- Create: `app/Logging/AgregarContextoTraza.php`, `app/Logging/RedactarDatosSensibles.php`, `app/Support/MensajeSeguro.php`
- Modify: `config/logging.php` (canal `json`), `.env.example` (`LOG_STACK`)
- Test: `tests/Unit/Logging/RedactarDatosSensiblesTest.php`, `tests/Feature/Trazas/ContextoLogTest.php`, `tests/Feature/Trazas/SpanExcepcionSeguraTest.php`

**Interfaces:**
- Consumes: `Trazas::trace_id_actual()`, `Trazas::span_id_actual()`; atributo `request_id`.
- Produce: archivo `storage/logs/medschedule.json`, una línea JSON por registro con `extra.trace_id`, `extra.span_id`, `extra.request_id`, `extra.user_id`, `extra.route`, `extra.method`. Lo lee Alloy (T013).

- [ ] **Paso 1: Escribir las pruebas que fallan**

`tests/Unit/Logging/RedactarDatosSensiblesTest.php`:

```php
<?php

namespace Tests\Unit\Logging;

use App\Logging\RedactarDatosSensibles;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use PDOException;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

class RedactarDatosSensiblesTest extends TestCase
{
	private function registro(array $contexto, string $mensaje = 'mensaje'): LogRecord
	{
		return new LogRecord(new DateTimeImmutable(), 'pruebas', Level::Info, $mensaje, $contexto);
	}

	public function test_enmascara_claves_sensibles_a_cualquier_profundidad(): void
	{
		$resultado = (new RedactarDatosSensibles())($this->registro([
			'password' => 'secreta',
			'Authorization' => 'Bearer abc',
			'paciente' => [
				'curp' => 'XXXX000000HSRXXX00',
				'allergies' => 'Penicilina',
				'nombre' => 'Ana',
			],
		]));

		$this->assertSame('[redactado]', $resultado->context['password']);
		$this->assertSame('[redactado]', $resultado->context['Authorization']);
		$this->assertSame('[redactado]', $resultado->context['paciente']['curp']);
		$this->assertSame('[redactado]', $resultado->context['paciente']['allergies']);
		$this->assertSame('Ana', $resultado->context['paciente']['nombre']);
	}

	public function test_enmascara_correo_dentro_del_mensaje(): void
	{
		$resultado = (new RedactarDatosSensibles())($this->registro([], 'reset para ana@ejemplo.com'));

		$this->assertSame('reset para [redactado]', $resultado->message);
	}

	public function test_enmascara_credencial_bearer_dentro_del_mensaje(): void
	{
		$resultado = (new RedactarDatosSensibles())($this->registro([], 'token invalido Bearer abc.def'));

		$this->assertSame('token invalido [redactado]', $resultado->message);
	}

	public function test_enmascara_curp_dentro_del_mensaje(): void
	{
		$resultado = (new RedactarDatosSensibles())($this->registro([], 'paciente CURP XXXX000000HSRXXX00 actualizado'));

		$this->assertSame('paciente CURP [redactado] actualizado', $resultado->message);
	}

	public function test_no_modifica_texto_sin_pii(): void
	{
		$resultado = (new RedactarDatosSensibles())($this->registro([], 'cita confirmada correctamente'));

		$this->assertSame('cita confirmada correctamente', $resultado->message);
	}

	public function test_enmascara_correo_en_valor_de_context_con_clave_no_sensible(): void
	{
		$resultado = (new RedactarDatosSensibles())($this->registro([
			'mensaje' => 'contactar a ana@ejemplo.com',
		]));

		$this->assertSame('contactar a [redactado]', $resultado->context['mensaje']);
	}

	public function test_enmascara_claves_que_contienen_token_por_subcadena(): void
	{
		$resultado = (new RedactarDatosSensibles())($this->registro([
			'remember_token' => 'abc123',
			'access_token' => 'def456',
			'Refresh_Token' => 'ghi789',
			'api_secret' => 'jkl000',
			'nombre' => 'Ana',
		]));

		$this->assertSame('[redactado]', $resultado->context['remember_token']);
		$this->assertSame('[redactado]', $resultado->context['access_token']);
		$this->assertSame('[redactado]', $resultado->context['Refresh_Token']);
		$this->assertSame('[redactado]', $resultado->context['api_secret']);
		$this->assertSame('Ana', $resultado->context['nombre']);
	}

	public function test_query_exception_no_expone_los_valores_de_binding(): void
	{
		$excepcion = new QueryException(
			'mysql',
			'update patient_profiles set allergies = ? where id = ?',
			['Penicilina-Grave', 77],
			new PDOException('SQLSTATE[HY000]: fallo simulado')
		);

		$resultado = (new RedactarDatosSensibles())($this->registro(['exception' => $excepcion]));

		$serializado = json_encode($resultado->context);
		$this->assertStringNotContainsString('Penicilina-Grave', $serializado);
		$this->assertSame(QueryException::class, $resultado->context['exception']['class']);
		$this->assertSame('update patient_profiles set allergies = ? where id = ?', $resultado->context['exception']['message']);
		$this->assertArrayHasKey('file', $resultado->context['exception']);
		$this->assertArrayHasKey('line', $resultado->context['exception']);
	}
}
```

`tests/Feature/Trazas/ContextoLogTest.php`:

```php
<?php

namespace Tests\Feature\Trazas;

use App\Logging\AgregarContextoTraza;
use App\Observability\Trazas\Trazas;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use Monolog\Level;
use Monolog\LogRecord;
use RuntimeException;

class ContextoLogTest extends TrazasTestCase
{
	public function test_agrega_trace_id_y_span_id_del_span_activo(): void
	{
		$trazas = app(Trazas::class);
		$registro = new LogRecord(new DateTimeImmutable(), 'pruebas', Level::Info, 'mensaje');

		$resultado = $trazas->en_span('prueba.log', fn () => (new AgregarContextoTraza())($registro));

		$span = $this->spans_que_empiezan_con('prueba.log')[0];
		$this->assertSame($span->getTraceId(), $resultado->extra['trace_id']);
		$this->assertSame($span->getSpanId(), $resultado->extra['span_id']);
	}

	public function test_sin_span_activo_no_agrega_trace_id(): void
	{
		$registro = new LogRecord(new DateTimeImmutable(), 'pruebas', Level::Info, 'mensaje');

		$resultado = (new AgregarContextoTraza())($registro);

		$this->assertArrayNotHasKey('trace_id', $resultado->extra);
	}

	// Prueba de integracion: verifica que el canal 'json' real (no solo los
	// processors de forma aislada) escriba lineas redactadas y con contexto de traza
	public function test_canal_json_escribe_linea_redactada_con_contexto_de_traza(): void
	{
		$archivo = storage_path('logs/prueba-canal-json-'.uniqid().'.json');

		try {
			config(['logging.channels.json.handler_with.stream' => $archivo]);
			Log::forgetChannel('json');

			$this->app->make(Trazas::class)->en_span(
				'prueba.canal',
				fn () => Log::channel('json')->info('prueba', ['password' => 'x', 'email' => 'a@b.c'])
			);

			$lineas = file($archivo);
			$ultima = json_decode(end($lineas), true);

			$span = $this->spans_que_empiezan_con('prueba.canal')[0];
			$this->assertSame('[redactado]', $ultima['context']['password']);
			$this->assertSame('[redactado]', $ultima['context']['email']);
			$this->assertSame($span->getTraceId(), $ultima['extra']['trace_id']);
		} finally {
			if (file_exists($archivo)) {
				unlink($archivo);
			}
		}
	}

	// La excepcion que Laravel pone en context['exception'] se serializa sin
	// PII: solo clase, mensaje redactado, archivo y linea (sin trace)
	public function test_canal_json_redacta_la_excepcion_del_contexto(): void
	{
		$archivo = storage_path('logs/prueba-canal-json-'.uniqid().'.json');

		try {
			config(['logging.channels.json.handler_with.stream' => $archivo]);
			Log::forgetChannel('json');

			Log::channel('json')->error('fallo', [
				'exception' => new RuntimeException('fallo para ana@example.com con alergia a penicilina'),
			]);

			$lineas = file($archivo);
			$linea = end($lineas);
			$ultima = json_decode($linea, true);

			$this->assertStringNotContainsString('ana@example.com', $linea);
			$this->assertSame(RuntimeException::class, $ultima['context']['exception']['class']);
			$this->assertSame('fallo para [redactado] con alergia a penicilina', $ultima['context']['exception']['message']);
			$this->assertSame(__FILE__, $ultima['context']['exception']['file']);
			$this->assertIsInt($ultima['context']['exception']['line']);
			$this->assertArrayNotHasKey('trace', $ultima['context']['exception']);
		} finally {
			if (file_exists($archivo)) {
				unlink($archivo);
			}
		}
	}
}
```

- [ ] **Paso 2: Correr las pruebas y verificar que fallan**

Run: `pruebas --filter="RedactarDatosSensiblesTest|ContextoLogTest"`
Expected: FAIL (clases inexistentes).

- [ ] **Paso 3: Implementar los processors**

`app/Logging/RedactarDatosSensibles.php`:

```php
<?php

namespace App\Logging;

use App\Support\MensajeSeguro;
use Illuminate\Database\QueryException;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Throwable;

// Enmascara credenciales y PII medica antes de escribir cualquier log
//
// Limite conocido: una contrasena escrita en texto libre (p.ej. "clave: abc123"
// dentro de un mensaje interpolado) no tiene un patron detectable de forma
// confiable y NO se enmascara por regex. La regla del equipo es no interpolar
// secretos en el mensaje: siempre deben pasarse en $context con su propia
// clave (password, token, etc.), que SI se enmascara por nombre de clave
// (ver self::CLAVES), sin importar el patron del valor.
//
// Las excepciones (Laravel las pone en context['exception']) se reducen a
// clase, mensaje redactado, archivo y linea: nunca el trace con argumentos.
class RedactarDatosSensibles implements ProcessorInterface
{
	public const MASCARA = '[redactado]';

	// Subcadenas en minusculas: una clave se enmascara si CONTIENE cualquiera
	// (asi 'remember_token', 'access_token', 'api_secret' o
	// 'password_confirmation' quedan cubiertas sin listarlas una por una)
	// 'key' y 'hash' sueltos NO se agregan como subcadena: romperian claves
	// inocentes como 'cache_key' (debe quedar intacta). Por eso se listan
	// formas mas especificas: 'api_key' y 'apikey'.
	public const CLAVES = [
		'token', 'password', 'secret', 'authorization', 'cookie',
		'curp', 'email',
		'allergies', 'chronic_conditions', 'blood_type', 'emergency_contact',
		'api_key', 'apikey', 'credential', 'phone', 'telefono', 'signature',
	];

	// Patrones para detectar PII/credenciales dentro de texto libre: el mensaje
	// del log y los valores string de context/extra cuya clave no es sensible
	// (p.ej. un correo interpolado dentro de 'mensaje' => "reset para a@b.c").
	// La coincidencia completa se reemplaza por MASCARA, incluida la palabra
	// Bearer/Basic en el segundo patron: asi no se filtra ni el esquema de auth.
	public const PATRONES = [
		// correos electronicos
		'/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/',
		// credenciales Bearer/Basic en texto (se enmascara la coincidencia completa)
		'/\b(Bearer|Basic)\s+[A-Za-z0-9._~+\/=-]+/i',
		// CURP mexicana (18 caracteres)
		'/\b[A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z0-9]\d\b/',
	];

	public function __invoke(LogRecord $registro): LogRecord
	{
		// Primero se sustituyen los mensajes de QueryException encontrados en
		// el contexto (getMessage() trae los bindings interpolados, ver
		// resumir_excepcion): esto debe pasar ANTES de convertir los Throwable
		// del contexto a su resumen seguro, porque usa los objetos originales.
		$mensaje = $this->redactar_mensaje_de_query_exceptions($registro->message, $registro->context);

		return $registro->with(
			message: $this->redactar_texto($mensaje),
			context: $this->redactar($registro->context),
			extra: $this->redactar($registro->extra),
		);
	}

	// Illuminate\Foundation\Exceptions\Handler::reportThrowable usa
	// $e->getMessage() como mensaje principal del log; en una QueryException
	// ese mensaje trae los valores de la consulta incrustados (ver
	// QueryException::formatMessage). Se busca, a cualquier profundidad del
	// contexto, cada QueryException (directa o como causa encadenada de otra
	// excepcion via getPrevious()) y se reemplaza toda ocurrencia de su
	// getMessage() por el texto seguro de MensajeSeguro.
	private function redactar_mensaje_de_query_exceptions(string $mensaje, array $contexto): string
	{
		foreach ($this->query_exceptions_en_contexto($contexto) as $consulta) {
			$mensaje = str_replace($consulta->getMessage(), MensajeSeguro::de_excepcion($consulta), $mensaje);
		}

		return $mensaje;
	}

	private function query_exceptions_en_contexto(array $contexto): array
	{
		$encontradas = [];
		foreach ($contexto as $valor) {
			if ($valor instanceof Throwable) {
				$encontradas = array_merge($encontradas, $this->query_exceptions_en_cadena($valor));
			} elseif (is_array($valor)) {
				$encontradas = array_merge($encontradas, $this->query_exceptions_en_contexto($valor));
			}
		}

		return $encontradas;
	}

	// Recorre getPrevious() de una excepcion buscando QueryException: cubre
	// tanto la excepcion directa como una QueryException encadenada como causa
	private function query_exceptions_en_cadena(Throwable $excepcion): array
	{
		$encontradas = [];
		do {
			if ($excepcion instanceof QueryException) {
				$encontradas[] = $excepcion;
			}
			$excepcion = $excepcion->getPrevious();
		} while ($excepcion !== null);

		return $encontradas;
	}

	private function redactar(array $datos): array
	{
		foreach ($datos as $clave => $valor) {
			if (is_string($clave) && $this->es_clave_sensible($clave)) {
				$datos[$clave] = self::MASCARA;
			} elseif ($valor instanceof Throwable) {
				$datos[$clave] = $this->resumir_excepcion($valor);
			} elseif (is_array($valor)) {
				$datos[$clave] = $this->redactar($valor);
			} elseif (is_string($valor)) {
				$datos[$clave] = $this->redactar_texto($valor);
			}
		}

		return $datos;
	}

	private function es_clave_sensible(string $clave): bool
	{
		$clave = strtolower($clave);
		foreach (self::CLAVES as $subcadena) {
			if (str_contains($clave, $subcadena)) {
				return true;
			}
		}

		return false;
	}

	// Reduce una excepcion a datos sin PII: mensaje seguro (MensajeSeguro),
	// clase, archivo y linea. Nunca el trace con argumentos.
	private function resumir_excepcion(Throwable $excepcion): array
	{
		return [
			'class' => $excepcion::class,
			'message' => MensajeSeguro::de_excepcion($excepcion),
			'file' => $excepcion->getFile(),
			'line' => $excepcion->getLine(),
		];
	}

	// Enmascara, dentro de un texto libre, cualquier coincidencia de los PATRONES
	private function redactar_texto(string $texto): string
	{
		return preg_replace(self::PATRONES, self::MASCARA, $texto);
	}
}
```

`app/Support/MensajeSeguro.php`:

```php
<?php

namespace App\Support;

use App\Logging\RedactarDatosSensibles;
use Illuminate\Database\QueryException;
use Throwable;

// Helper unico para obtener el mensaje de una excepcion sin PII: lo mismo lo
// usa RedactarDatosSensibles (mensaje del log y resumen del contexto) que los
// puntos que exportan trazas a Tempo (evento 'exception' del span), asi ambos
// caminos quedan seguros de la misma forma y con una sola definicion.
class MensajeSeguro
{
	public static function de_excepcion(Throwable $excepcion): string
	{
		if ($excepcion instanceof QueryException) {
			return self::de_query_exception($excepcion);
		}

		// Reutiliza los PATRONES/MASCARA de RedactarDatosSensibles: no se
		// duplica la lista de expresiones regulares en dos sitios.
		return preg_replace(
			RedactarDatosSensibles::PATRONES,
			RedactarDatosSensibles::MASCARA,
			$excepcion->getMessage()
		);
	}

	// getMessage() de una QueryException interpola los bindings dentro del SQL
	// (ver Illuminate\Database\QueryException::formatMessage): por eso el texto
	// seguro usa getSql(), la sentencia parametrizada sin bindings.
	private static function de_query_exception(QueryException $excepcion): string
	{
		$texto = 'QueryException: ' . $excepcion->getSql();
		$sqlstate = $excepcion->getCode();

		if (!empty($sqlstate)) {
			$texto .= ' [SQLSTATE ' . $sqlstate . ']';
		}

		return $texto;
	}
}
```

`app/Logging/AgregarContextoTraza.php`:

```php
<?php

namespace App\Logging;

use App\Observability\Trazas\Trazas;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Throwable;

// Agrega a cada linea de log los identificadores que permiten saltar a su traza
class AgregarContextoTraza implements ProcessorInterface
{
	public function __invoke(LogRecord $registro): LogRecord
	{
		try {
			$trazas = app(Trazas::class);
			$peticion = app()->bound('request') ? request() : null;
			$guardia = auth()->guard();

			$extra = [
				'trace_id' => $trazas->trace_id_actual(),
				'span_id' => $trazas->span_id_actual(),
				'request_id' => $peticion?->attributes->get('request_id'),
				// hasUser no dispara consultas: evita recursion con el log de SQL
				'user_id' => $guardia->hasUser() ? $guardia->id() : null,
				'route' => $peticion?->route()?->getName(),
				'method' => $peticion?->method(),
			];
		} catch (Throwable $error) {
			// Si no se puede calcular el contexto, la linea se escribe igual y se marca
			$extra = ['contexto_no_disponible' => $error::class];
		}

		return $registro->with(extra: array_merge(
			$registro->extra,
			array_filter($extra, fn ($valor) => $valor !== null)
		));
	}
}
```

- [ ] **Paso 4: Canal `json`**

En `config/logging.php`, dentro de `'channels' => [`, después de `'single'`:

```php
        // Logs estructurados para Loki (unidad 3): una linea JSON por registro,
        // con contexto de traza y PII enmascarada
        'json' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => storage_path('logs/medschedule.json'),
            ],
            'formatter' => Monolog\Formatter\JsonFormatter::class,
            'processors' => [
                App\Logging\AgregarContextoTraza::class,
                App\Logging\RedactarDatosSensibles::class,
            ],
        ],
```

Driver `monolog` con `StreamHandler` (ya importado arriba en `config/logging.php`) y no `single`: en Laravel 12.53 el driver `single` ignora la clave `processors`, así que los processors nunca corrían. Por eso `ContextoLogTest` incluye una prueba de integración que escribe por el canal real (redirigiendo `handler_with.stream` a un archivo temporal) y no solo prueba los processors aislados. (`config/logging.php` usa espacios: respetar su indentación.) En `.env.example` cambiar `LOG_STACK=single` por:

```dotenv
LOG_STACK=single,json
```

- [ ] **Paso 5: Correr las pruebas y verificar que pasan; probar el canal real**

Run: `pruebas --filter="RedactarDatosSensiblesTest|ContextoLogTest|SpanExcepcionSeguraTest"`
Expected: PASS (18 pruebas). La redacción cubre: claves sensibles por **subcadena** sin distinguir mayúsculas (`token`, `password`, `secret`, `authorization`, `cookie`, `curp`, `email`, campos clínicos, `emergency_contact`, `api_key`, `apikey`, `credential`, `phone`, `telefono`, `signature`: así `remember_token`, `access_token`, `api_secret` o `cache_key` — esta última NO se enmascara, no contiene ninguna subcadena sensible — quedan resueltas correctamente); `PATRONES` dentro del mensaje y de valores de texto (correo, `Bearer`/`Basic`, CURP); y excepciones en el contexto, reducidas a clase, mensaje seguro (`MensajeSeguro::de_excepcion`), archivo y línea, sin trace. `MensajeSeguro` centraliza el mensaje seguro de una excepción: para una `QueryException` es `'QueryException: ' . getSql()` (+ SQLSTATE si `getCode()` no está vacío), y para cualquier otra excepción su mensaje pasado por los mismos `PATRONES`. Se usa en `RedactarDatosSensibles` (contexto y, ahora también, el `message` principal del registro — `Handler::reportThrowable` escribe `$e->getMessage()` como mensaje, y en una `QueryException` esa cadena trae los bindings interpolados) y en los `catch` de `Trazas::en_span` e `IniciarTraza::handle`, que ya no usan `$span->recordException()` (guarda el stacktrace con argumentos) sino `$span->addEvent('exception', ['exception.type' => ..., 'exception.message' => MensajeSeguro::de_excepcion($error)])`. Límite documentado en la clase: una contraseña escrita en texto libre sin clave propia no se detecta.

```bash
php artisan tinker --execute="Log::channel('json')->info('prueba de canal', ['password' => 'x', 'email' => 'a@b.c']);"
tail -1 storage/logs/medschedule.json | python3 -m json.tool
```

Expected: JSON válido con `"password": "[redactado]"` y `"email": "[redactado]"`.

- [ ] **Paso 6: Commit**

```bash
git add app/Logging config/logging.php .env.example tests/Unit/Logging tests/Feature/Trazas/ContextoLogTest.php
git diff --staged
git commit -m "feat(logs): logs JSON con contexto de traza y enmascarado de PII"
```

### T013 [US3] Folio en la página de error 500

**Files:**
- Create: `resources/views/errors/500.blade.php`
- Test: `tests/Feature/Trazas/PaginaErrorTest.php`, `tests/Feature/Trazas/PaginaErrorSinTrazasTest.php`

**Interfaces:**
- Consumes: atributos `trace_id` y, como respaldo con las trazas apagadas, `request_id` de la petición (T010).

- [ ] **Paso 1: Escribir la prueba que falla**

`tests/Feature/Trazas/PaginaErrorTest.php`:

```php
<?php

namespace Tests\Feature\Trazas;

use Illuminate\Support\Facades\Route;
use RuntimeException;

class PaginaErrorTest extends TrazasTestCase
{
	public function test_error_500_muestra_folio_sin_detalle_interno(): void
	{
		config(['app.debug' => false]);
		Route::middleware('web')->get('/_prueba/error', fn () => throw new RuntimeException('detalle interno de la base'));

		$respuesta = $this->get('/_prueba/error');

		$respuesta->assertStatus(500);
		$trace_id = $respuesta->headers->get('X-Trace-Id');
		$this->assertNotNull($trace_id);
		$respuesta->assertSee("Folio: {$trace_id}");
		$respuesta->assertDontSee('detalle interno de la base');
	}
}
```

`tests/Feature/Trazas/PaginaErrorSinTrazasTest.php` (con el proveedor Noop, sin `TrazasTestCase`):

```php
<?php

namespace Tests\Feature\Trazas;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

// Con las trazas apagadas (proveedor Noop) el folio usa el request_id de respaldo
class PaginaErrorSinTrazasTest extends TestCase
{
	public function test_error_500_sin_trazas_muestra_request_id_como_folio(): void
	{
		config(['app.debug' => false]);
		Route::middleware('web')->get('/_prueba/error-sin-trazas', fn () => throw new RuntimeException('detalle interno'));

		$respuesta = $this->get('/_prueba/error-sin-trazas');

		$respuesta->assertStatus(500);
		$this->assertMatchesRegularExpression(
			'/Folio: [0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/',
			$respuesta->getContent()
		);
		$respuesta->assertDontSee('detalle interno');
	}
}
```

- [ ] **Paso 2: Correr la prueba y verificar que falla**

Run: `pruebas --filter=PaginaError`
Expected: FAIL (la vista por defecto de Laravel no muestra el folio).

- [ ] **Paso 3: Vista de error**

`resources/views/errors/500.blade.php`:

```blade
<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>MedSchedule - Error</title>
	<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet" />
</head>

<body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">
	{{-- Sin detalle interno: solo el folio para que soporte localice la traza --}}
	<div class="card border-0 shadow-sm p-4 text-center" style="max-width: 480px;">
		<h1 class="h4 mb-3">Ocurrió un error inesperado</h1>
		<p class="text-muted mb-3">Ya quedó registrado. Si necesitas ayuda, comparte este folio con soporte.</p>
		{{-- Con trazas apagadas no hay trace_id: se usa el request_id de respaldo --}}
		@php($folio = request()->attributes->get('trace_id') ?? request()->attributes->get('request_id'))
		<p class="fw-semibold mb-4">Folio: {{ $folio ?? 'no disponible' }}</p>
		<a class="btn btn-primary" href="{{ url('/') }}">Volver al inicio</a>
	</div>
</body>

</html>
```

- [ ] **Paso 4: Correr la prueba y verificar que pasa**

Run: `pruebas --filter=PaginaError`
Expected: PASS (2 pruebas). Con trazas el folio es el `trace_id`; sin trazas, el `request_id` (UUID).

- [ ] **Paso 5: Commit**

```bash
git add resources/views/errors/500.blade.php tests/Feature/Trazas/PaginaErrorTest.php tests/Feature/Trazas/PaginaErrorSinTrazasTest.php
git commit -m "feat(trazas): mostrar un folio de seguimiento en la pagina de error 500"
```

### T014 [US3] Loki, Tempo y Alloy con correlación en Grafana

**Files:**
- Modify: `infra/monitoreo/docker-compose.yml` (servicios `loki`, `tempo`, `alloy`)
- Create: `infra/monitoreo/loki/loki.yml`, `infra/monitoreo/tempo/tempo.yml`, `infra/monitoreo/alloy/config.alloy`
- Create: `infra/monitoreo/grafana/provisioning/datasources/trazabilidad.yml`
- Create: `infra/monitoreo/grafana/dashboards/trazabilidad.json`

**Interfaces:**
- Consumes: `storage/logs/medschedule.json` (T012); OTLP/HTTP desde la app hacia `127.0.0.1:4318` (T010).
- Produce: datasources de uid `loki` y `tempo` enlazados por `trace_id`; dashboard `medschedule-trazabilidad`.

- [ ] **Paso 1: Servicios en el compose**

Agregar a `services:` de `infra/monitoreo/docker-compose.yml`:

```yaml
    loki:
        image: grafana/loki:3.7.8
        command: ['-config.file=/etc/loki/loki.yml']
        # Solo local: el volumen nombrado nace con duenio root y Loki corre como 10001
        user: root
        volumes:
            - ./loki/loki.yml:/etc/loki/loki.yml:ro
            - datos_loki:/loki
        restart: unless-stopped

    # Tempo 2.9.x: la rama 3.x cambio el formato de configuracion; se fija la 2.9
    # por estabilidad de la configuracion de un solo binario
    tempo:
        image: grafana/tempo:2.9.5
        command: ['-config.file=/etc/tempo/tempo.yml']
        user: root
        volumes:
            - ./tempo/tempo.yml:/etc/tempo/tempo.yml:ro
            - datos_tempo:/var/tempo
        ports:
            # La app, fuera de Docker, envia las trazas aqui
            - '127.0.0.1:4318:4318'
        restart: unless-stopped

    alloy:
        image: grafana/alloy:v1.19.2
        command:
            - run
            - --server.http.listen-addr=0.0.0.0:12345
            - --storage.path=/var/lib/alloy/data
            - /etc/alloy/config.alloy
        volumes:
            - ./alloy/config.alloy:/etc/alloy/config.alloy:ro
            - ../../storage/logs:/logs:ro
        restart: unless-stopped
```

Y en `volumes:` agregar `datos_loki:` y `datos_tempo:`.

- [ ] **Paso 2: Configuración de Loki, Tempo y Alloy**

`infra/monitoreo/loki/loki.yml`:

```yaml
auth_enabled: false

server:
    http_listen_port: 3100

common:
    path_prefix: /loki
    replication_factor: 1
    ring:
        kvstore:
            store: inmemory
    storage:
        filesystem:
            chunks_directory: /loki/chunks
            rules_directory: /loki/rules

schema_config:
    configs:
        - from: 2026-01-01
          store: tsdb
          object_store: filesystem
          schema: v13
          index:
              prefix: index_
              period: 24h

limits_config:
    retention_period: 168h
    allow_structured_metadata: true

compactor:
    working_directory: /loki/compactor
    retention_enabled: true
    delete_request_store: filesystem
```

`infra/monitoreo/tempo/tempo.yml`:

```yaml
server:
    http_listen_port: 3200

distributor:
    receivers:
        otlp:
            protocols:
                http:
                    endpoint: 0.0.0.0:4318

ingester:
    max_block_duration: 5m

compactor:
    compaction:
        block_retention: 48h

storage:
    trace:
        backend: local
        local:
            path: /var/tempo/traces
        wal:
            path: /var/tempo/wal
```

`infra/monitoreo/alloy/config.alloy`:

```alloy
// Lee el log JSON de Laravel y lo envia a Loki
local.file_match "medschedule" {
	path_targets = [{"__path__" = "/logs/medschedule.json", "job" = "medschedule"}]
}

loki.source.file "medschedule" {
	targets    = local.file_match.medschedule.targets
	forward_to = [loki.process.medschedule.receiver]
}

loki.process "medschedule" {
	// Nivel y ruta como etiquetas (baja cardinalidad); trace_id se queda en la linea
	stage.json {
		expressions = { level = "level_name", route = "extra.route" }
	}

	stage.labels {
		values = { level = "", route = "" }
	}

	forward_to = [loki.write.local.receiver]
}

loki.write "local" {
	endpoint {
		url = "http://loki:3100/loki/api/v1/push"
	}
}
```

- [ ] **Paso 3: Datasources enlazados**

`infra/monitoreo/grafana/provisioning/datasources/trazabilidad.yml` (en provisioning `$$` escapa el `$` de las variables de Grafana):

```yaml
apiVersion: 1
datasources:
    - name: Loki
      type: loki
      uid: loki
      url: http://loki:3100
      jsonData:
          derivedFields:
              - name: trace_id
                matcherRegex: '"trace_id":"([0-9a-f]{32})"'
                url: '$${__value.raw}'
                datasourceUid: tempo
    - name: Tempo
      type: tempo
      uid: tempo
      url: http://tempo:3200
      jsonData:
          tracesToLogsV2:
              datasourceUid: loki
              spanStartTimeShift: '-5m'
              spanEndTimeShift: '5m'
              customQuery: true
              query: '{job="medschedule"} |= "$${__span.traceId}"'
          serviceMap:
              datasourceUid: prometheus
```

- [ ] **Paso 4: Dashboard de trazabilidad**

`infra/monitoreo/grafana/dashboards/trazabilidad.json`:

```json
{
	"uid": "medschedule-trazabilidad",
	"title": "MedSchedule - Trazabilidad",
	"schemaVersion": 39,
	"refresh": "30s",
	"time": { "from": "now-1h", "to": "now" },
	"templating": {
		"list": [
			{ "name": "nivel", "type": "custom", "query": "DEBUG,INFO,WARNING,ERROR,CRITICAL", "includeAll": true, "multi": true, "current": { "text": "All", "value": "$__all" }, "allValue": ".*" }
		]
	},
	"panels": [
		{
			"type": "logs", "title": "Logs de la aplicacion",
			"gridPos": { "h": 12, "w": 24, "x": 0, "y": 0 },
			"datasource": { "type": "loki", "uid": "loki" },
			"targets": [{ "refId": "A", "expr": "{job=\"medschedule\", level=~\"$nivel\"}" }]
		},
		{
			"type": "table", "title": "Trazas mas lentas (> 100 ms)",
			"gridPos": { "h": 10, "w": 12, "x": 0, "y": 12 },
			"datasource": { "type": "tempo", "uid": "tempo" },
			"targets": [{ "refId": "A", "queryType": "traceql", "query": "{ resource.service.name = \"medschedule\" && duration > 100ms }", "limit": 20 }]
		},
		{
			"type": "table", "title": "Peticiones con error",
			"gridPos": { "h": 10, "w": 12, "x": 12, "y": 12 },
			"datasource": { "type": "tempo", "uid": "tempo" },
			"targets": [{ "refId": "A", "queryType": "traceql", "query": "{ resource.service.name = \"medschedule\" && status = error }", "limit": 20 }]
		}
	]
}
```

- [ ] **Paso 5: Validar y levantar**

```bash
python3 -m json.tool infra/monitoreo/grafana/dashboards/trazabilidad.json > /dev/null && echo "json ok"
docker compose -f infra/monitoreo/docker-compose.yml --env-file infra/monitoreo/.env config -q && echo "compose ok"
bash scripts/monitoreo-local.sh
docker compose -f infra/monitoreo/docker-compose.yml ps
curl -sf http://127.0.0.1:4318/v1/traces -X POST -H 'Content-Type: application/json' -d '{}' -o /dev/null -w '%{http_code}\n'
```

Expected: `json ok`, `compose ok`, los 10 servicios `running`, y el POST vacío a Tempo responde `200`. Si Loki o Tempo reinician en bucle, leer `docker compose logs loki tempo` y corregir la clave que marque el error. Después, con `OTEL_ENABLED=true` y `LOG_STACK=single,json` en el `.env` local, navegar por la app y comprobar en Grafana → Explore que Loki muestra líneas y Tempo muestra trazas.

- [ ] **Paso 6: Commit**

```bash
git add infra/monitoreo
git diff --staged --stat
git commit -m "feat(trazas): Loki, Tempo y Alloy con logs y trazas enlazados en Grafana"
```

### T015 [US3] Evidencia antes/después y costo de la instrumentación

**Files:**
- Create: `docs/entrega-u3/evidencia/trazas-01-antes-laravel-log.png`, `trazas-02-cascada.png`, `trazas-03-log-a-traza.png`, `trazas-04-traza-a-logs.png`, `trazas-05-folio-500.png`, `trazas-06-folio-en-grafana.png`
- Create: `docs/entrega-u3/evidencia/k6-sin-instrumentacion.txt`, `k6-con-instrumentacion.txt`

- [ ] **Paso 1: "Antes"**

Con `OTEL_ENABLED=false` y `LOG_STACK=single`, llamar al endpoint más lento según la U2 (`/patient/dashboard/data`, métrica `duracion_panel` en `docs/entrega-u2/07-resultados-k6.md`) y mostrar que `storage/logs/laravel.log` no permite saber qué parte tarda. Captura `trazas-01-antes-laravel-log.png`.

- [ ] **Paso 2: "Después"**

Con `OTEL_ENABLED=true` y `LOG_STACK=single,json`, repetir la llamada. En Grafana → Explore → Tempo abrir su traza (buscar por el `X-Trace-Id` de la respuesta: `curl -si ... | grep -i x-trace-id`). Capturas: cascada con las consultas (`trazas-02`), salto de la línea de log a la traza (`trazas-03`) y de la traza a sus logs (`trazas-04`). Registrar en la entrega qué consulta o patrón N+1 domina el tiempo, con su duración.

- [ ] **Paso 3: Folio de un error real**

Provocar un 500 controlado en local (por ejemplo, detener MySQL y abrir una pantalla que consulte la base), capturar la pantalla con el folio (`trazas-05`) y localizar ese folio en Tempo (`trazas-06`). Volver a levantar MySQL.

- [ ] **Paso 4: Costo de la instrumentación (meta del plan: p95 no sube más de 10 %)**

Correr la misma prueba de k6 de la U2 en la misma máquina, dos veces:

```bash
# sin instrumentacion: METRICAS_ALMACEN=memoria, OTEL_ENABLED=false en .env; reiniciar php artisan serve
K6_PASSWORD="$K6_PASSWORD" k6 run tests/carga/jri-prueba.js 2>&1 | tee docs/entrega-u3/evidencia/k6-sin-instrumentacion.txt
# con instrumentacion: METRICAS_ALMACEN=redis, OTEL_ENABLED=true; reiniciar php artisan serve
K6_PASSWORD="$K6_PASSWORD" k6 run tests/carga/jri-prueba.js 2>&1 | tee docs/entrega-u3/evidencia/k6-con-instrumentacion.txt
grep -E 'http_req_duration' docs/entrega-u3/evidencia/k6-*-instrumentacion.txt
```

Reportar ambos p95 y la diferencia porcentual tal como salgan, aunque supere el 10 %; en ese caso, documentarlo con su causa probable en lugar de ocultarlo. Revisar que los `.txt` no contengan contraseñas.

- [ ] **Paso 5: Commit**

```bash
git add docs/entrega-u3/evidencia/trazas-* docs/entrega-u3/evidencia/k6-*-instrumentacion.txt
git commit -m "docs(entrega): evidencia del visor de trazabilidad y costo de la instrumentacion"
```

**Checkpoint**: US3 completa; PR del punto 2 listo para abrir (T022).

---

## Phase 5: User Story 4 — Visor de auditoría (Priority: P4) — rama `feat/<R20>-auditoria` (sale de `feat/<R17>-u3-sdd`)

```bash
git switch -c feat/<R20>-auditoria feat/<R17>-u3-sdd
```

### T016 [US4] Cadena HMAC y registros de solo agregado

**Files:**
- Create: `config/auditoria.php`
- Create: `database/migrations/2026_09_23_000000_add_integridad_to_activity_logs_table.php`
- Create: `database/migrations/2026_09_23_000001_drop_user_fk_from_activity_logs_table.php`
- Create: `app/Exceptions/RegistroAuditoriaInmutable.php`
- Create: `app/Services/Auditoria/SelladorAuditoria.php`
- Create: `app/Providers/AuditoriaServiceProvider.php`
- Modify: `app/Models/ActivityLog.php`, `bootstrap/providers.php`, `phpunit.xml`, `.env.example`
- Test: `tests/Feature/Auditoria/SelladoAuditoriaTest.php`

**Interfaces:**
- Produce: `SelladorAuditoria::sellar(ActivityLog $log): void`, `SelladorAuditoria::calcular(?string $hash_anterior, ActivityLog $log): string`, `SelladorAuditoria::verificar(): array` con claves `estado` (`integra`|`rota`), `id_roto` (?int), `fecha_rota` (?string), `revisados` (int). No hay filas "históricas sin sello": toda fila con `hash` nulo cuenta como rota.
- Produce: columnas `activity_logs.trace_id`, `hash_anterior`, `hash`; excepción `RegistroAuditoriaInmutable` en `update`/`delete` de `ActivityLog`.

- [ ] **Paso 1: Configuración y migración**

`config/auditoria.php`:

```php
<?php

return [
	// Llave del sello HMAC de la auditoria. Obligatoria en produccion; nunca en el repositorio.
	'llave_hmac' => env('AUDIT_HMAC_KEY'),

	// Grafana, para el enlace "ver traza" del visor
	'grafana_url' => env('GRAFANA_URL', 'http://localhost:3000'),
];
```

`phpunit.xml`: `<env name="AUDIT_HMAC_KEY" value="llave-solo-para-pruebas"/>`.

`.env.example`, al final:

```dotenv
# Auditoria (unidad 3). Generar con: php -r "echo bin2hex(random_bytes(32));"
AUDIT_HMAC_KEY=
GRAFANA_URL=http://localhost:3000
```

`database/migrations/2026_09_23_000000_add_integridad_to_activity_logs_table.php`:

```php
<?php

use App\Models\ActivityLog;
use App\Services\Auditoria\SelladorAuditoria;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Sello encadenado e identificador de traza para el visor de auditoria.
// El indice (model_type, model_id) ya existe (idx_model, 2026_08_15_043158).
return new class extends Migration
{
	public function up(): void
	{
		Schema::table('activity_logs', function (Blueprint $table) {
			$table->string('trace_id', 32)->nullable()->after('new_values');
			$table->char('hash_anterior', 64)->nullable()->after('trace_id');
			// Nullable porque hash se rellena para TODAS las filas (incluidas
			// las preexistentes) al final de este mismo up(); una fila con
			// hash nulo despues de esta migracion se considera rota.
			$table->char('hash', 64)->nullable()->after('hash_anterior');
			$table->index('trace_id', 'idx_trace_id');
		});

		$this->sellar_filas_existentes();
	}

	public function down(): void
	{
		Schema::table('activity_logs', function (Blueprint $table) {
			$table->dropIndex('idx_trace_id');
			$table->dropColumn(['trace_id', 'hash_anterior', 'hash']);
		});
	}

	// Unico lugar legitimo de escritura directa de hash/hash_anterior fuera
	// del sellador: no hay fila "historica sin sello" que verificar() deba
	// perdonar, asi que las filas creadas antes de esta migracion se sellan
	// aqui mismo, encadenadas en orden de id, con DB::table (nunca Eloquent,
	// para no disparar los guardas de inmutabilidad de ActivityLog).
	private function sellar_filas_existentes(): void
	{
		$sellador = app(SelladorAuditoria::class);
		$anterior = null;

		ActivityLog::query()->orderBy('id')->each(function (ActivityLog $log) use ($sellador, &$anterior) {
			$hash = $sellador->calcular($anterior, $log);

			DB::table('activity_logs')->where('id', $log->id)->update([
				'hash_anterior' => $anterior,
				'hash' => $hash,
			]);

			$anterior = $hash;
		});
	}
};
```

`hash` es nullable solo para poder agregar la columna, pero la misma migración sella todas las filas existentes en orden de id al final de `up()`; después de migrar, cualquier fila con `hash` nulo es una ruptura. `AUDIT_HMAC_KEY` debe estar definida **antes** de migrar.

`database/migrations/2026_09_23_000001_drop_user_fk_from_activity_logs_table.php` (la FK `ON DELETE SET NULL` cambiaba el contenido sellado al borrar un usuario; se quita la FK y se conserva el índice):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// La auditoria debe sobrevivir al usuario auditado. La FK original
// (activity_logs_user_id_foreign, ON DELETE SET NULL, 2026_02_24_045227)
// pone user_id a NULL cuando se borra el usuario, lo que cambia el
// contenido sellado de filas ya firmadas y hace que verificar() reporte
// un falso "rota". Se quita la FK; el indice activity_logs_user_id_index
// no se toca (sigue sirviendo a los filtros del visor).
return new class extends Migration
{
	public function up(): void
	{
		Schema::table('activity_logs', function (Blueprint $table) {
			$table->dropForeign('activity_logs_user_id_foreign');
		});
	}

	// Advertencia: restaurar la FK falla (MySQL 1452) si ya existen user_id
	// huerfanos de usuarios borrados despues de up(). En ese caso el rollback
	// exige decidir antes que hacer con esas filas selladas: ponerlas a NULL
	// rompe su sello, y borrarlas elimina evidencia de auditoria.
	public function down(): void
	{
		Schema::table('activity_logs', function (Blueprint $table) {
			$table->foreign('user_id', 'activity_logs_user_id_foreign')
				->references('id')->on('users')
				->onDelete('set null');
		});
	}
};
```

- [ ] **Paso 2: Escribir la prueba que falla**

`tests/Feature/Auditoria/SelladoAuditoriaTest.php`:

```php
<?php

namespace Tests\Feature\Auditoria;

use App\Exceptions\RegistroAuditoriaInmutable;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Auditoria\SelladorAuditoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class SelladoAuditoriaTest extends TestCase
{
	use RefreshDatabase;

	private function crear_registro(string $accion): ActivityLog
	{
		return ActivityLog::create(['action' => $accion, 'description' => "Registro {$accion}"]);
	}

	public function test_registro_nuevo_queda_sellado_y_encadenado(): void
	{
		$primero = $this->crear_registro('uno');
		$segundo = $this->crear_registro('dos');

		$this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $primero->hash);
		$this->assertNull($primero->hash_anterior);
		$this->assertSame($primero->hash, $segundo->hash_anterior);
	}

	public function test_no_se_puede_modificar(): void
	{
		$registro = $this->crear_registro('uno');

		$this->expectException(RegistroAuditoriaInmutable::class);
		$registro->update(['description' => 'alterado']);
	}

	public function test_no_se_puede_eliminar(): void
	{
		$registro = $this->crear_registro('uno');

		$this->expectException(RegistroAuditoriaInmutable::class);
		$registro->delete();
	}

	public function test_cadena_intacta_es_integra(): void
	{
		$this->crear_registro('uno');
		$this->crear_registro('dos');

		$resultado = app(SelladorAuditoria::class)->verificar();

		$this->assertSame('integra', $resultado['estado']);
		$this->assertSame(2, $resultado['revisados']);
	}

	public function test_detecta_alteracion_manual_en_la_base(): void
	{
		$this->crear_registro('uno');
		$alterado = $this->crear_registro('dos');
		$this->crear_registro('tres');

		DB::table('activity_logs')->where('id', $alterado->id)->update(['description' => 'alterado a mano']);

		$resultado = app(SelladorAuditoria::class)->verificar();

		$this->assertSame('rota', $resultado['estado']);
		$this->assertSame($alterado->id, $resultado['id_roto']);
	}

	public function test_detecta_eliminacion_intermedia(): void
	{
		$this->crear_registro('uno');
		$borrado = $this->crear_registro('dos');
		$siguiente = $this->crear_registro('tres');

		DB::table('activity_logs')->where('id', $borrado->id)->delete();

		$resultado = app(SelladorAuditoria::class)->verificar();

		$this->assertSame('rota', $resultado['estado']);
		$this->assertSame($siguiente->id, $resultado['id_roto']);
	}

	public function test_fila_sin_sello_rompe_la_cadena(): void
	{
		$this->crear_registro('uno');
		$sin_sello_id = DB::table('activity_logs')->insertGetId([
			'action' => 'sin_sello',
			'created_at' => now(),
			'updated_at' => now(),
		]);

		$resultado = app(SelladorAuditoria::class)->verificar();

		$this->assertSame('rota', $resultado['estado']);
		$this->assertSame($sin_sello_id, $resultado['id_roto']);
	}

	public function test_anular_todos_los_hashes_rompe_la_cadena(): void
	{
		$primero = $this->crear_registro('uno');
		$this->crear_registro('dos');
		$this->crear_registro('tres');

		DB::table('activity_logs')->update(['hash' => null]);

		$resultado = app(SelladorAuditoria::class)->verificar();

		$this->assertSame('rota', $resultado['estado']);
		$this->assertSame($primero->id, $resultado['id_roto']);
	}

	public function test_borrar_usuario_conserva_la_auditoria_y_la_cadena_integra(): void
	{
		$usuario = User::factory()->create();
		$registro = ActivityLog::create([
			'action' => 'uno',
			'description' => 'Registro uno',
			'user_id' => $usuario->id,
		]);
		$usuario_id = $usuario->id;

		$usuario->delete();

		// Sin la FK con ON DELETE SET NULL, la fila conserva el user_id
		// aunque el usuario ya no exista.
		$this->assertSame($usuario_id, $registro->fresh()->user_id);

		$resultado = app(SelladorAuditoria::class)->verificar();
		$this->assertSame('integra', $resultado['estado']);
	}

	// La migracion 2026_09_23_000000 sella, en su propio up(), las filas que
	// ya existian antes de agregar las columnas. RefreshDatabase migra sobre
	// una base vacia, asi que no hay forma de ver ese paso actuar sobre filas
	// reales dentro de una prueba normal: se invoca el metodo privado que usa
	// el propio up() (via reflexion), simulando filas insertadas antes de la
	// migracion, para probar la logica de sellado que realmente ejecuta.
	public function test_migracion_sella_las_filas_preexistentes(): void
	{
		DB::table('activity_logs')->insert([
			['action' => 'uno', 'description' => 'uno', 'created_at' => now(), 'updated_at' => now()],
			['action' => 'dos', 'description' => 'dos', 'created_at' => now(), 'updated_at' => now()],
		]);

		$migracion = require database_path('migrations/2026_09_23_000000_add_integridad_to_activity_logs_table.php');
		$metodo = new ReflectionMethod($migracion, 'sellar_filas_existentes');
		$metodo->setAccessible(true);
		$metodo->invoke($migracion);

		$resultado = app(SelladorAuditoria::class)->verificar();

		$this->assertSame('integra', $resultado['estado']);
		$this->assertSame(2, $resultado['revisados']);
	}
}
```

- [ ] **Paso 3: Correr la prueba y verificar que falla**

Run: `pruebas --filter=SelladoAuditoriaTest`
Expected: FAIL (columna `hash` o clases inexistentes).

- [ ] **Paso 4: Implementar la excepción y el sellador**

`app/Exceptions/RegistroAuditoriaInmutable.php`:

```php
<?php

namespace App\Exceptions;

use RuntimeException;

// Se lanza al intentar modificar o eliminar un registro de auditoria
class RegistroAuditoriaInmutable extends RuntimeException
{
}
```

`app/Services/Auditoria/SelladorAuditoria.php`:

```php
<?php

namespace App\Services\Auditoria;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;
use RuntimeException;

// Sella cada registro de auditoria con un HMAC encadenado al registro anterior.
// Detecta alterar o borrar una fila intermedia sin conocer la llave.
//
// Limites conocidos (no detectables por verificar()):
// - Borrar las ultimas N filas de la tabla: la cadena queda intacta hasta
//   donde llega; no hay forma de saber que faltan filas al final.
// - Quien conoce AUDIT_HMAC_KEY puede recalcular y reescribir toda la
//   cadena de forma consistente; el sello protege contra quien NO tiene
//   la llave, no contra quien la tiene.
// - `id` y `updated_at` no forman parte del contenido sellado (canonico());
//   cambiar solo esos campos no se detecta.
// - Rotar AUDIT_HMAC_KEY invalida la cadena existente: hay que volver a
//   sellar todo el historico con la llave nueva antes de rotarla en produccion.
class SelladorAuditoria
{
	private static bool $aviso_llave_emitido = false;

	// Se llama dentro de la transaccion de insercion (ver ActivityLog::save)
	public function sellar(ActivityLog $log): void
	{
		$log->created_at ??= now();
		$log->trace_id ??= $this->trace_id_actual();

		$ultimo = ActivityLog::query()->orderByDesc('id')->lockForUpdate()->first(['id', 'hash']);

		$log->hash_anterior = $ultimo?->hash;
		$log->hash = $this->calcular($log->hash_anterior, $log);
	}

	public function calcular(?string $hash_anterior, ActivityLog $log): string
	{
		return hash_hmac('sha256', ($hash_anterior ?? '') . '|' . $this->canonico($log), $this->llave());
	}

	// Recorre la cadena completa y devuelve el primer eslabon roto.
	// No existe el concepto de fila "historica sin sello": toda fila con
	// hash nulo se considera rota (ver migracion 2026_09_23_000000, que
	// sella las filas preexistentes al agregar estas columnas).
	public function verificar(): array
	{
		$anterior = null;
		$revisados = 0;

		foreach (ActivityLog::query()->orderBy('id')->lazyById(500) as $log) {
			$revisados++;

			$intacto = $log->hash !== null
				&& $log->hash_anterior === $anterior
				&& hash_equals($this->calcular($anterior, $log), $log->hash);

			if (!$intacto) {
				return [
					'estado' => 'rota',
					'id_roto' => $log->id,
					'fecha_rota' => $log->created_at?->toIso8601String(),
					'revisados' => $revisados,
				];
			}

			$anterior = $log->hash;
		}

		return [
			'estado' => 'integra',
			'id_roto' => null,
			'fecha_rota' => null,
			'revisados' => $revisados,
		];
	}

	// Representacion estable de la fila: claves ordenadas, fechas ISO 8601 UTC
	private function canonico(ActivityLog $log): string
	{
		$datos = [
			'action' => $log->action,
			'created_at' => $log->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
			'description' => $log->description,
			'ip_address' => $log->ip_address,
			'model_id' => $log->model_id === null ? null : (int) $log->model_id,
			'model_type' => $log->model_type,
			'new_values' => $this->ordenar($log->new_values),
			'old_values' => $this->ordenar($log->old_values),
			'trace_id' => $log->trace_id,
			'user_agent' => $log->user_agent,
			'user_id' => $log->user_id === null ? null : (int) $log->user_id,
		];

		return json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
	}

	// MySQL reordena las claves de las columnas JSON; se ordenan antes de sellar
	private function ordenar(mixed $valor): mixed
	{
		if (!is_array($valor)) {
			return $valor;
		}
		ksort($valor, SORT_STRING);

		return array_map(fn ($elemento) => $this->ordenar($elemento), $valor);
	}

	private function trace_id_actual(): ?string
	{
		return app()->bound('request') ? request()->attributes->get('trace_id') : null;
	}

	private function llave(): string
	{
		$llave = (string) config('auditoria.llave_hmac');
		if ($llave !== '') {
			return $llave;
		}

		if (app()->environment('production')) {
			throw new RuntimeException('Falta AUDIT_HMAC_KEY: la auditoria no puede sellarse en produccion.');
		}

		// Fuera de produccion se deriva de APP_KEY para no romper los entornos del equipo
		if (!self::$aviso_llave_emitido) {
			self::$aviso_llave_emitido = true;
			Log::warning('AUDIT_HMAC_KEY no configurada; se deriva de APP_KEY (solo desarrollo).');
		}

		return hash_hmac('sha256', 'auditoria', (string) config('app.key'));
	}
}
```

- [ ] **Paso 5: Modelo `ActivityLog` y provider**

En `app/Models/ActivityLog.php`: agregar `'trace_id'` a `$fillable` y estos métodos (con sus `use` de `App\Exceptions\RegistroAuditoriaInmutable`, `App\Services\Auditoria\SelladorAuditoria` e `Illuminate\Support\Facades\DB`):

```php
	protected static function booted(): void
	{
		static::creating(function (ActivityLog $log) {
			app(SelladorAuditoria::class)->sellar($log);
		});

		// Solo agregado: la aplicacion nunca edita ni borra auditoria
		static::updating(function () {
			throw new RegistroAuditoriaInmutable('Los registros de auditoria no se pueden modificar.');
		});
		static::deleting(function () {
			throw new RegistroAuditoriaInmutable('Los registros de auditoria no se pueden eliminar.');
		});
	}

	// La insercion va en transaccion para que el bloqueo de la ultima fila
	// serialice la cadena cuando dos cambios se auditan al mismo tiempo.
	// Reintenta hasta 3 veces si MySQL reporta un deadlock por la contencion
	// del lockForUpdate() en SelladorAuditoria::sellar().
	public function save(array $options = []): bool
	{
		if (!$this->exists) {
			return DB::transaction(fn () => parent::save($options), 3);
		}

		return parent::save($options);
	}
```

`app/Providers/AuditoriaServiceProvider.php`:

```php
<?php

namespace App\Providers;

use App\Services\Auditoria\SelladorAuditoria;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AuditoriaServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->app->singleton(SelladorAuditoria::class);
	}

	public function boot(): void
	{
		// En produccion la aplicacion no arranca sin llave de auditoria
		if ($this->app->environment('production') && (string) config('auditoria.llave_hmac') === '') {
			throw new RuntimeException('Falta AUDIT_HMAC_KEY en produccion.');
		}
	}
}
```

`bootstrap/providers.php`: agregar `App\Providers\AuditoriaServiceProvider::class,`.

- [ ] **Paso 6: Correr las pruebas y verificar que pasan**

Run: `pruebas --filter="SelladoAuditoriaTest|ActivityLogControllerTest"`
Expected: `SelladoAuditoriaTest` PASS (10 pruebas: incluye la fila sin sello y el anulado masivo de hashes, que deben dar `rota`, y la migración que sella las filas preexistentes); `ActivityLogControllerTest` con el mismo resultado que en la línea base de T002.

- [ ] **Paso 7: Commit**

```bash
git add config/auditoria.php database/migrations/2026_09_23_000000_add_integridad_to_activity_logs_table.php database/migrations/2026_09_23_000001_drop_user_fk_from_activity_logs_table.php app/Exceptions/RegistroAuditoriaInmutable.php app/Services/Auditoria app/Providers/AuditoriaServiceProvider.php app/Models/ActivityLog.php bootstrap/providers.php phpunit.xml .env.example tests/Feature/Auditoria/SelladoAuditoriaTest.php
git diff --staged
git commit -m "feat(auditoria): sello HMAC encadenado y registros de solo agregado"
```

### T017 [US4] Trait `Auditable` en los modelos críticos

**Files:**
- Create: `app/Services/Auditoria/RegistradorAuditoria.php`
- Create: `app/Models/Concerns/Auditable.php`
- Modify: `app/Models/Appointment.php`, `User.php`, `Specialty.php`, `Schedule.php`, `DoctorProfile.php`, `PatientProfile.php`
- Modify: `app/Http/Controllers/PatientProfileController.php` (quitar los dos `ActivityLog::create` manuales)
- Modify: `database/seeders/FullDataSeeder.php` (insertar la auditoría con el modelo)
- Test: `tests/Feature/Auditoria/AuditableTest.php`

**Interfaces:**
- Consumes: `ActivityLog` sellado (T016).
- Produce: `RegistradorAuditoria::registrar(string $accion, ?Model $modelo = null, array $anteriores = [], array $nuevos = [], ?string $descripcion = null, ?int $user_id = null): ActivityLog`.
- Produce: acciones `creado`, `actualizado`, `eliminado`; valor `[protegido]` para campos protegidos.

- [ ] **Paso 1: Escribir la prueba que falla**

`tests/Feature/Auditoria/AuditableTest.php`:

```php
<?php

namespace Tests\Feature\Auditoria;

use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\PatientProfile;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditableTest extends TestCase
{
	use RefreshDatabase;

	private function ultimo(string $clase, int $id): ActivityLog
	{
		return ActivityLog::query()
			->where('model_type', $clase)->where('model_id', $id)
			->orderByDesc('id')->firstOrFail();
	}

	public function test_crear_actualizar_y_eliminar_generan_diff(): void
	{
		$admin = User::factory()->create();
		$this->actingAs($admin);

		$especialidad = Specialty::create(['name' => 'Cardiologia', 'description' => 'Corazon']);
		$creado = $this->ultimo(Specialty::class, $especialidad->id);
		$this->assertSame('creado', $creado->action);
		$this->assertSame('Cardiologia', $creado->new_values['name']);
		$this->assertSame($admin->id, $creado->user_id);

		$especialidad->update(['name' => 'Cardiologia clinica']);
		$actualizado = $this->ultimo(Specialty::class, $especialidad->id);
		$this->assertSame('actualizado', $actualizado->action);
		$this->assertSame(['name' => 'Cardiologia'], $actualizado->old_values);
		$this->assertSame(['name' => 'Cardiologia clinica'], $actualizado->new_values);

		$id = $especialidad->id;
		$especialidad->delete();
		$eliminado = $this->ultimo(Specialty::class, $id);
		$this->assertSame('eliminado', $eliminado->action);
		$this->assertSame('Cardiologia clinica', $eliminado->old_values['name']);
	}

	public function test_password_se_registra_como_protegido(): void
	{
		$usuario = User::factory()->create();
		$usuario->update(['password' => bcrypt('nueva-clave-segura')]);

		$registro = $this->ultimo(User::class, $usuario->id);

		$this->assertSame('actualizado', $registro->action);
		$this->assertSame('[protegido]', $registro->new_values['password']);
		$this->assertSame('[protegido]', $registro->old_values['password']);
	}

	public function test_campos_clinicos_se_registran_como_protegidos(): void
	{
		$paciente = User::factory()->create();
		$perfil = PatientProfile::create(['user_id' => $paciente->id, 'allergies' => 'Penicilina', 'blood_type' => 'O+']);

		$perfil->update(['allergies' => 'Penicilina y latex']);

		$registro = $this->ultimo(PatientProfile::class, $perfil->id);
		$this->assertSame('[protegido]', $registro->new_values['allergies']);
		$this->assertStringNotContainsString('latex', json_encode($registro->new_values));
		$this->assertStringNotContainsString('Penicilina', json_encode(ActivityLog::all()->toArray()));
	}

	public function test_cambio_solo_de_campos_excluidos_no_registra(): void
	{
		$usuario = User::factory()->create();
		$antes = ActivityLog::count();

		$usuario->update(['remember_token' => 'otro-token-cualquiera']);

		$this->assertSame($antes, ActivityLog::count());
	}

	public function test_motivo_y_observaciones_de_la_cita_se_registran_como_protegidos(): void
	{
		$especialidad = Specialty::create(['name' => 'Cardiologia']);
		$paciente = User::factory()->create();
		$doctor = User::factory()->create();

		$cita = Appointment::create([
			'patient_id' => $paciente->id,
			'doctor_id' => $doctor->id,
			'specialty_id' => $especialidad->id,
			'appointment_date' => now()->toDateString(),
			'start_time' => '10:00:00',
			'end_time' => '10:30:00',
			'status' => 'pending',
			'reason' => 'dolor de pecho',
		]);
		$creado = $this->ultimo(Appointment::class, $cita->id);
		$this->assertSame('[protegido]', $creado->new_values['reason']);

		// observaciones no es asignable en masa: se llena como lo haria el doctor
		$cita->forceFill(['reason' => 'dolor de pecho intenso', 'observaciones' => 'dolor de pecho con disnea'])->save();
		$actualizado = $this->ultimo(Appointment::class, $cita->id);
		$this->assertSame('[protegido]', $actualizado->old_values['reason']);
		$this->assertSame('[protegido]', $actualizado->new_values['reason']);
		$this->assertSame('[protegido]', $actualizado->new_values['observaciones']);

		$this->assertStringNotContainsString('dolor de pecho', json_encode(ActivityLog::all()->toArray()));
	}
}
```

- [ ] **Paso 2: Correr la prueba y verificar que falla**

Run: `pruebas --filter=AuditableTest`
Expected: FAIL (no se registra nada: `firstOrFail` lanza `ModelNotFoundException`).

- [ ] **Paso 3: Implementar el registrador y el trait**

`app/Services/Auditoria/RegistradorAuditoria.php`:

```php
<?php

namespace App\Services\Auditoria;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

// Punto unico de escritura de auditoria: quien, que, sobre que, desde donde
class RegistradorAuditoria
{
	public function registrar(
		string $accion,
		?Model $modelo = null,
		array $anteriores = [],
		array $nuevos = [],
		?string $descripcion = null,
		?int $user_id = null,
	): ActivityLog {
		$peticion = app()->bound('request') ? request() : null;
		$agente = $peticion?->userAgent();

		return ActivityLog::create([
			'user_id' => $user_id ?? auth()->id(),
			'action' => $accion,
			'model_type' => $modelo?->getMorphClass(),
			'model_id' => $modelo?->getKey(),
			'description' => $descripcion,
			'ip_address' => $peticion?->ip(),
			'user_agent' => $agente === null ? null : mb_substr($agente, 0, 255),
			'old_values' => $anteriores === [] ? null : $anteriores,
			'new_values' => $nuevos === [] ? null : $nuevos,
		]);
	}
}
```

`app/Models/Concerns/Auditable.php`:

```php
<?php

namespace App\Models\Concerns;

use App\Services\Auditoria\RegistradorAuditoria;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

// Audita altas, cambios y bajas del modelo con el diff de los campos cambiados
trait Auditable
{
	// Cambios que no aportan a la auditoria
	private const CAMPOS_EXCLUIDOS_AUDITORIA = ['remember_token', 'created_at', 'updated_at'];

	// Se registra que cambiaron, nunca su valor
	private const CAMPOS_SIEMPRE_PROTEGIDOS = ['password'];

	public const VALOR_PROTEGIDO = '[protegido]';

	public static function bootAuditable(): void
	{
		static::created(function (Model $modelo) {
			$modelo->auditar('creado', [], $modelo->getAttributes());
		});

		static::updated(function (Model $modelo) {
			$cambios = $modelo->getChanges();
			$modelo->auditar('actualizado', array_intersect_key($modelo->getRawOriginal(), $cambios), $cambios);
		});

		static::deleted(function (Model $modelo) {
			$modelo->auditar('eliminado', $modelo->getRawOriginal(), []);
		});
	}

	// Cada modelo con datos sensibles sobreescribe este metodo
	public function campos_protegidos_auditoria(): array
	{
		return [];
	}

	protected function auditar(string $accion, array $anteriores, array $nuevos): void
	{
		$anteriores = $this->limpiar_para_auditoria($anteriores);
		$nuevos = $this->limpiar_para_auditoria($nuevos);

		// Un cambio que solo toco campos excluidos no se registra
		if ($accion === 'actualizado' && $nuevos === []) {
			return;
		}

		app(RegistradorAuditoria::class)->registrar($accion, $this, $anteriores, $nuevos);
	}

	private function limpiar_para_auditoria(array $valores): array
	{
		$protegidos = array_merge(self::CAMPOS_SIEMPRE_PROTEGIDOS, $this->campos_protegidos_auditoria());
		$limpios = [];

		foreach ($valores as $campo => $valor) {
			if (in_array($campo, self::CAMPOS_EXCLUIDOS_AUDITORIA, true)) {
				continue;
			}
			if (in_array($campo, $protegidos, true)) {
				$limpios[$campo] = self::VALOR_PROTEGIDO;
				continue;
			}
			$limpios[$campo] = $valor instanceof DateTimeInterface ? $valor->format('Y-m-d H:i:s') : $valor;
		}

		return $limpios;
	}
}
```

- [ ] **Paso 4: Aplicar el trait a los seis modelos**

En `Appointment`, `User`, `Specialty`, `Schedule`, `DoctorProfile` y `PatientProfile`: agregar `use App\Models\Concerns\Auditable;` al encabezado y `use Auditable;` dentro de la clase (junto a los `use` de traits que ya tenga, por ejemplo `HasFactory, Notifiable, HasRoles` en `User`).

En `PatientProfile` agregar además:

```php
	// PII medica: la auditoria registra que cambio, nunca el valor
	public function campos_protegidos_auditoria(): array
	{
		return [
			'birth_date', 'blood_type', 'allergies', 'chronic_conditions',
			'emergency_contact_name', 'emergency_contact_phone', 'curp',
		];
	}
```

En `Appointment` agregar además (motivo de consulta y observaciones son texto clínico libre):

```php
	// Datos clinicos en texto libre: la auditoria registra que cambio, nunca el valor
	public function campos_protegidos_auditoria(): array
	{
		return ['reason', 'observaciones'];
	}
```

- [ ] **Paso 5: Quitar los registros manuales que guardaban PII en claro**

En `app/Http/Controllers/PatientProfileController.php` eliminar los dos bloques `ActivityLog::create([...])` de `update` y `updatePhoto` (hoy guardan `old_values`/`new_values` con alergias, CURP y tipo de sangre en claro). El trait ya registra ambos cambios con los valores protegidos. Si `$oldValues` queda sin uso, eliminarlo también; si `ActivityLog` queda sin uso, quitar su `use`.

- [ ] **Paso 6: El seeder inserta la auditoría con el modelo**

En `database/seeders/FullDataSeeder.php` (línea ~167) sustituir `DB::table('activity_logs')->insert([...])` por inserciones con el modelo, una por fila, para que queden selladas:

```php
		foreach ($registros_actividad as $registro) {
			ActivityLog::create($registro);
		}
```

donde `$registros_actividad` es el mismo arreglo que hoy recibe `insert()`. Si alguna fila trae `created_at`, se conserva (el sellador solo lo llena cuando falta).

- [ ] **Paso 7: Correr las pruebas y verificar que pasan; comparar con la línea base**

Run: `pruebas --filter="AuditableTest|SelladoAuditoriaTest"`
Expected: PASS (15 pruebas).

```bash
pruebas 2>&1 | tee "$SCRATCH/pruebas-despues-auditable.txt" | tail -5
grep -E '^\s+(FAIL|⨯)|FAILED' "$SCRATCH/pruebas-despues-auditable.txt" > "$SCRATCH/fallos-despues-auditable.txt" || true
diff "$SCRATCH/linea-base-fallos.txt" "$SCRATCH/fallos-despues-auditable.txt" && echo "mismos fallos que la linea base"
```

Expected: `mismos fallos que la linea base`. Si aparece un fallo nuevo (por ejemplo, una prueba que cuenta filas de `activity_logs`), corregir la causa en el código de esta tarea o, si la prueba asumía que no había auditoría, ajustar esa prueba explicando por qué en el commit.

- [ ] **Paso 8: Commit**

```bash
git add app/Services/Auditoria/RegistradorAuditoria.php app/Models app/Http/Controllers/PatientProfileController.php database/seeders/FullDataSeeder.php tests/Feature/Auditoria/AuditableTest.php
git diff --staged
git commit -m "feat(auditoria): auditar altas, cambios y bajas con PII protegida"
```

### T018 [US4] Eventos de sesión, roles y accesos denegados

**Files:**
- Create: `app/Support/Enmascarar.php`
- Create: `app/Listeners/Auditoria/AuditarCierreSesion.php`, `AuditarInicioFallido.php`, `AuditarRestablecimientoPassword.php`, `AuditarCambioRoles.php`, `AuditarCambioPermisos.php`
- Create: `app/Http/Middleware/AuditarAccesoDenegado.php`
- Modify: `config/permission.php` (`events_enabled`), `bootstrap/app.php` (alias), `routes/web.php` (grupo admin, línea ~46)
- Modify: `app/Http/Controllers/Auth/AuthenticatedSessionController.php` (quitar el `ActivityLog::create` de `destroy`)
- Test: `tests/Unit/EnmascararTest.php`, `tests/Feature/Auditoria/EventosAuditadosTest.php`

**Interfaces:**
- Consumes: `RegistradorAuditoria::registrar()` (T017).
- Produce: acciones `logout` (solo desde el listener), `login_fallido`, `password_restablecida`, `rol_asignado`, `rol_retirado`, `permiso_asignado`, `permiso_retirado`, `acceso_denegado`. El inicio de sesión ya lo registra `AuthenticatedSessionController` como `login`; no se duplica.
- Produce: `Enmascarar::correo(string $correo): string`.

- [ ] **Paso 1: Escribir las pruebas que fallan**

`tests/Unit/EnmascararTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Support\Enmascarar;
use PHPUnit\Framework\TestCase;

class EnmascararTest extends TestCase
{
	public function test_enmascara_la_parte_local_del_correo(): void
	{
		$this->assertSame('j***@gmail.com', Enmascarar::correo('juan.perez@gmail.com'));
	}

	public function test_valor_que_no_es_correo_se_oculta_completo(): void
	{
		$this->assertSame('***', Enmascarar::correo('no-es-correo'));
		$this->assertSame('***', Enmascarar::correo(''));
	}
}
```

`tests/Feature/Auditoria/EventosAuditadosTest.php`:

```php
<?php

namespace Tests\Feature\Auditoria;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventosAuditadosTest extends TestCase
{
	use RefreshDatabase;

	public function test_login_fallido_se_audita_con_correo_enmascarado(): void
	{
		User::factory()->create(['email' => 'maria.lopez@example.com']);

		$this->post(route('login'), ['email' => 'maria.lopez@example.com', 'password' => 'incorrecta']);

		$registro = ActivityLog::where('action', 'login_fallido')->firstOrFail();
		$this->assertStringContainsString('m***@example.com', $registro->description);
		$this->assertStringNotContainsString('maria.lopez', $registro->description);
	}

	public function test_logout_se_audita(): void
	{
		$usuario = User::factory()->create();

		$this->actingAs($usuario)->post(route('logout'));

		$this->assertTrue(ActivityLog::where('action', 'logout')->where('user_id', $usuario->id)->exists());
	}

	public function test_asignar_y_retirar_rol_se_audita(): void
	{
		Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'web']);
		$usuario = User::factory()->create();

		$usuario->assignRole('doctor');
		$usuario->removeRole('doctor');

		$asignado = ActivityLog::where('action', 'rol_asignado')->firstOrFail();
		$this->assertSame(['roles' => ['doctor']], $asignado->new_values);
		$this->assertTrue(ActivityLog::where('action', 'rol_retirado')->exists());
	}

	public function test_acceso_denegado_a_admin_se_audita(): void
	{
		Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
		$paciente = User::factory()->create();

		$this->actingAs($paciente)->get(route('admin.logs'))->assertForbidden();

		$registro = ActivityLog::where('action', 'acceso_denegado')->firstOrFail();
		$this->assertSame($paciente->id, $registro->user_id);
		$this->assertStringContainsString('/admin/logs', $registro->description);
	}

	// throttle va antes de auditar.denegado: pasado el limite, los intentos
	// responden 429 sin escribir mas filas selladas en la auditoria
	public function test_accesos_denegados_repetidos_se_limitan_antes_de_auditarse(): void
	{
		Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
		$paciente = User::factory()->create();
		$this->actingAs($paciente);

		for ($i = 0; $i < 60; $i++) {
			$this->get(route('admin.logs'))->assertForbidden();
		}
		$this->get(route('admin.logs'))->assertStatus(429);

		$this->assertSame(60, ActivityLog::where('action', 'acceso_denegado')->count());
	}
}
```

- [ ] **Paso 2: Correr las pruebas y verificar que fallan**

Run: `pruebas --filter="EnmascararTest|EventosAuditadosTest"`
Expected: FAIL (clases y registros inexistentes). Si `route('logout')` o `route('login')` tienen otro nombre, revisar `php artisan route:list --path=log` y ajustar la prueba.

- [ ] **Paso 3: Implementar `Enmascarar` y los listeners**

`app/Support/Enmascarar.php`:

```php
<?php

namespace App\Support;

// Enmascarado de datos personales para auditoria y logs
final class Enmascarar
{
	public static function correo(string $correo): string
	{
		if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
			return '***';
		}

		[$local, $dominio] = explode('@', $correo, 2);

		return mb_substr($local, 0, 1) . '***@' . $dominio;
	}
}
```

`app/Listeners/Auditoria/AuditarCierreSesion.php`:

```php
<?php

namespace App\Listeners\Auditoria;

use App\Services\Auditoria\RegistradorAuditoria;
use Illuminate\Auth\Events\Logout;

class AuditarCierreSesion
{
	public function __construct(private RegistradorAuditoria $registrador)
	{
	}

	public function handle(Logout $evento): void
	{
		if ($evento->user === null) {
			return;
		}

		$this->registrador->registrar(
			'logout',
			$evento->user,
			descripcion: 'El usuario cerró sesión',
			user_id: $evento->user->getAuthIdentifier(),
		);
	}
}
```

`app/Listeners/Auditoria/AuditarInicioFallido.php`:

```php
<?php

namespace App\Listeners\Auditoria;

use App\Services\Auditoria\RegistradorAuditoria;
use App\Support\Enmascarar;
use Illuminate\Auth\Events\Failed;

class AuditarInicioFallido
{
	public function __construct(private RegistradorAuditoria $registrador)
	{
	}

	public function handle(Failed $evento): void
	{
		$correo = (string) ($evento->credentials['email'] ?? '');

		$this->registrador->registrar(
			'login_fallido',
			descripcion: 'Intento de inicio de sesión fallido para ' . Enmascarar::correo($correo),
			user_id: $evento->user?->getAuthIdentifier(),
		);
	}
}
```

`app/Listeners/Auditoria/AuditarRestablecimientoPassword.php`:

```php
<?php

namespace App\Listeners\Auditoria;

use App\Services\Auditoria\RegistradorAuditoria;
use Illuminate\Auth\Events\PasswordReset;

class AuditarRestablecimientoPassword
{
	public function __construct(private RegistradorAuditoria $registrador)
	{
	}

	public function handle(PasswordReset $evento): void
	{
		$this->registrador->registrar(
			'password_restablecida',
			$evento->user,
			descripcion: 'El usuario restableció su contraseña',
			user_id: $evento->user->getAuthIdentifier(),
		);
	}
}
```

`app/Listeners/Auditoria/AuditarCambioRoles.php`:

```php
<?php

namespace App\Listeners\Auditoria;

use App\Services\Auditoria\RegistradorAuditoria;
use Illuminate\Support\Collection;
use Spatie\Permission\Events\RoleAttached;
use Spatie\Permission\Events\RoleDetached;
use Spatie\Permission\Models\Role;

class AuditarCambioRoles
{
	public function __construct(private RegistradorAuditoria $registrador)
	{
	}

	public function handle(RoleAttached|RoleDetached $evento): void
	{
		$asignado = $evento instanceof RoleAttached;
		$nombres = ['roles' => $this->nombres($evento->rolesOrIds)];

		$this->registrador->registrar(
			$asignado ? 'rol_asignado' : 'rol_retirado',
			$evento->model,
			$asignado ? [] : $nombres,
			$asignado ? $nombres : [],
			$asignado ? 'Se asignaron roles' : 'Se retiraron roles',
		);
	}

	// Spatie entrega modelos, ids o colecciones segun la llamada
	private function nombres(mixed $roles): array
	{
		$lista = $roles instanceof Collection ? $roles->all() : (is_array($roles) ? $roles : [$roles]);

		return array_values(array_map(
			fn ($rol) => $rol instanceof Role ? $rol->name : (Role::find($rol)?->name ?? (string) $rol),
			$lista
		));
	}
}
```

`app/Listeners/Auditoria/AuditarCambioPermisos.php`: misma estructura que `AuditarCambioRoles`, con `PermissionAttached|PermissionDetached`, `Spatie\Permission\Models\Permission`, `$evento->permissionsOrIds`, acciones `permiso_asignado` / `permiso_retirado` y clave `permisos`. (Verificar el nombre de la propiedad en `vendor/spatie/laravel-permission/src/Events/PermissionAttached.php`.)

Laravel 12 descubre automáticamente los listeners de `app/Listeners` por el tipo de `handle`; no se registran a mano. Comprobar con `php artisan event:list | grep -i auditar`.

En `app/Http/Controllers/Auth/AuthenticatedSessionController.php`, método `destroy`: quitar el bloque `$user = Auth::user(); if ($user) { ActivityLog::create([... 'action' => 'logout' ...]); }`. El listener `AuditarCierreSesion` ya registra el evento `Logout` que dispara `Auth::guard('web')->logout()`; dejar ambos duplicaba la fila. En su lugar queda:

```php
		// El cierre de sesion se audita via el listener AuditarCierreSesion
		// (evento Illuminate\Auth\Events\Logout), que Auth::guard('web')->logout()
		// dispara siempre; registrarlo aqui tambien duplicaria la fila.
		Auth::guard('web')->logout();
```

- [ ] **Paso 4: Activar eventos de Spatie**

En `config/permission.php` (línea ~122): `'events_enabled' => true,` con el comentario `// La auditoria (unidad 3) escucha la asignacion de roles y permisos`.

- [ ] **Paso 5: Middleware de acceso denegado**

`app/Http/Middleware/AuditarAccesoDenegado.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Services\Auditoria\RegistradorAuditoria;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

// Registra los 403 de las rutas de administracion. Va antes de role:admin:
// la excepcion del rol se convierte en respuesta 403 antes de llegar aqui.
class AuditarAccesoDenegado
{
	public function __construct(private RegistradorAuditoria $registrador)
	{
	}

	public function handle(Request $request, Closure $next): Response
	{
		$respuesta = $next($request);

		if ($respuesta->getStatusCode() === Response::HTTP_FORBIDDEN) {
			try {
				$this->registrador->registrar(
					'acceso_denegado',
					descripcion: 'Acceso denegado a ' . $request->method() . ' /' . ltrim($request->path(), '/'),
				);
			} catch (Throwable $error) {
				// El usuario ya recibe su 403; se deja constancia del fallo de auditoria
				Log::error('No se pudo auditar un acceso denegado', ['error' => $error->getMessage()]);
			}
		}

		return $respuesta;
	}
}
```

En `bootstrap/app.php`, dentro de `$middleware->alias([...])`: `'auditar.denegado' => \App\Http\Middleware\AuditarAccesoDenegado::class,`.

En `routes/web.php` (línea ~46) cambiar el grupo admin a:

```php
// throttle antes de auditar.denegado: los intentos repetidos de un no admin
// reciben 429 sin llenar la auditoria de filas selladas
Route::middleware(['auth', 'throttle:60,1', 'auditar.denegado', 'role:admin'])->group(function () {
```

Orden: `auth` → `throttle` → `auditar.denegado` → `role:admin`. `auditar.denegado` va antes de `role:admin` para ver el 403 ya convertido en respuesta.

- [ ] **Paso 6: Correr las pruebas y verificar que pasan**

Run: `pruebas --filter="EnmascararTest|EventosAuditadosTest|AdminRbacAccessTest|ActivityLogControllerTest"`
Expected: las 7 nuevas PASS (incluye la de límite de tasa: tras 60 intentos denegados el siguiente responde 429 sin nueva fila); `AdminRbacAccessTest` y `ActivityLogControllerTest` igual que en la línea base.

- [ ] **Paso 7: Commit**

```bash
git add app/Support app/Listeners app/Http/Middleware/AuditarAccesoDenegado.php app/Http/Controllers/Auth/AuthenticatedSessionController.php config/permission.php bootstrap/app.php routes/web.php tests/Unit/EnmascararTest.php tests/Feature/Auditoria/EventosAuditadosTest.php
git diff --staged
git commit -m "feat(auditoria): auditar sesion, roles, permisos y accesos denegados"
```

### T019 [US4] Comando de verificación diaria de la integridad

**Files:**
- Create: `app/Console/Commands/VerificarAuditoria.php`
- Modify: `routes/console.php` (programación diaria)
- Test: `tests/Feature/Auditoria/ComandosAuditoriaTest.php`

**Interfaces:**
- Consumes: `SelladorAuditoria::verificar()` (T016).
- Produce: `php artisan auditoria:verificar` (código 0 íntegra, 1 rota) y la clave de caché `auditoria.integridad` = resultado de `verificar()` + `verificado_en` (ISO 8601), que lee el visor (T020).

- [ ] **Paso 1: Escribir la prueba que falla**

`tests/Feature/Auditoria/ComandosAuditoriaTest.php`:

```php
<?php

namespace Tests\Feature\Auditoria;

use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ComandosAuditoriaTest extends TestCase
{
	use RefreshDatabase;

	public function test_verificar_cadena_integra_termina_en_cero_y_guarda_resultado(): void
	{
		ActivityLog::create(['action' => 'uno']);

		$this->artisan('auditoria:verificar')->assertExitCode(0);

		$this->assertSame('integra', Cache::get('auditoria.integridad')['estado']);
	}

	public function test_verificar_cadena_rota_termina_en_uno_y_senala_el_registro(): void
	{
		$registro = ActivityLog::create(['action' => 'uno']);
		DB::table('activity_logs')->where('id', $registro->id)->update(['action' => 'alterado']);

		$this->artisan('auditoria:verificar')
			->expectsOutputToContain("Registro roto: {$registro->id}")
			->assertExitCode(1);

		$this->assertSame($registro->id, Cache::get('auditoria.integridad')['id_roto']);
	}
}
```

- [ ] **Paso 2: Correr la prueba y verificar que falla**

Run: `pruebas --filter=ComandosAuditoriaTest`
Expected: FAIL (`The command "auditoria:verificar" does not exist`).

- [ ] **Paso 3: Implementar el comando**

`app/Console/Commands/VerificarAuditoria.php`:

```php
<?php

namespace App\Console\Commands;

use App\Services\Auditoria\SelladorAuditoria;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class VerificarAuditoria extends Command
{
	protected $signature = 'auditoria:verificar';

	protected $description = 'Verifica la cadena de sellos de la auditoria y reporta el primer registro alterado';

	public function handle(SelladorAuditoria $sellador): int
	{
		$resultado = $sellador->verificar();

		// El visor muestra el ultimo resultado sin recorrer la tabla en cada visita
		Cache::forever('auditoria.integridad', $resultado + ['verificado_en' => now()->toIso8601String()]);

		$this->info("Registros revisados: {$resultado['revisados']}");

		if ($resultado['estado'] === 'rota') {
			$this->error("Cadena rota. Registro roto: {$resultado['id_roto']} ({$resultado['fecha_rota']})");

			return self::FAILURE;
		}

		$this->info('Cadena íntegra.');

		return self::SUCCESS;
	}
}
```

En `routes/console.php` agregar:

```php
use Illuminate\Support\Facades\Schedule;

// Verificacion diaria de la integridad de la auditoria (unidad 3)
Schedule::command('auditoria:verificar')->dailyAt('03:00');
```

- [ ] **Paso 4: Correr las pruebas y verificar que pasan**

Run: `pruebas --filter="ComandosAuditoriaTest|SelladoAuditoriaTest"`
Expected: PASS (12 pruebas). `php artisan schedule:list` muestra `auditoria:verificar`; la salida dice "Registros revisados: N". No hay comando de sellado histórico: la migración de T016 sella las filas existentes.

- [ ] **Paso 5: Commit**

```bash
git add app/Console/Commands routes/console.php tests/Feature/Auditoria/ComandosAuditoriaTest.php
git diff --staged
git commit -m "feat(auditoria): comando de verificacion diaria de la integridad"
```

### T020 [US4] Visor `/admin/auditoria` con diff, línea de tiempo y exportación CSV

**Files:**
- Create: `app/Http/Controllers/AuditoriaController.php`
- Create: `app/Http/Requests/FiltrarAuditoriaRequest.php`
- Create: `app/Support/CsvSeguro.php`
- Create: `resources/views/admin/auditoria/index.blade.php`, `show.blade.php`, `linea-tiempo.blade.php`
- Modify: `routes/web.php` (grupo admin), `resources/views/components/sidebar.blade.php` (entrada "Auditoría" tras "Logs", línea ~134)
- Test: `tests/Unit/CsvSeguroTest.php`, `tests/Feature/Auditoria/VisorAuditoriaTest.php`

**Interfaces:**
- Consumes: `RegistradorAuditoria` (T017), caché `auditoria.integridad` (T019), `config('auditoria.grafana_url')` (T016).
- Produce: rutas `admin.auditoria`, `admin.auditoria.show`, `admin.auditoria.entidad`, `admin.auditoria.exportar`; `AuditoriaController::ENTIDADES` (alias de URL → clase).

- [ ] **Paso 1: Escribir las pruebas que fallan**

`tests/Unit/CsvSeguroTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Support\CsvSeguro;
use PHPUnit\Framework\TestCase;

class CsvSeguroTest extends TestCase
{
	public function test_neutraliza_celdas_que_parecen_formula(): void
	{
		foreach (['=CMD()', '+1', '-1', '@SUMA(A1)', "\tx", "\rx"] as $peligrosa) {
			$this->assertSame("'" . $peligrosa, CsvSeguro::celda($peligrosa));
		}
	}

	public function test_deja_igual_el_texto_normal_y_serializa_arreglos(): void
	{
		$this->assertSame('Cardiologia', CsvSeguro::celda('Cardiologia'));
		$this->assertSame('', CsvSeguro::celda(null));
		$this->assertSame('{"name":"Cardiologia"}', CsvSeguro::celda(['name' => 'Cardiologia']));
	}
}
```

`tests/Feature/Auditoria/VisorAuditoriaTest.php`:

```php
<?php

namespace Tests\Feature\Auditoria;

use App\Models\ActivityLog;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VisorAuditoriaTest extends TestCase
{
	use RefreshDatabase;

	private function admin(): User
	{
		$rol = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
		$admin = User::factory()->create(['name' => 'Admin Auditor']);
		$admin->assignRole($rol);

		return $admin;
	}

	public function test_admin_ve_el_listado(): void
	{
		$admin = $this->admin();
		Specialty::create(['name' => 'Pediatria']);

		$this->actingAs($admin)->get(route('admin.auditoria'))
			->assertOk()
			->assertSee('Auditoría')
			->assertSee('creado');
	}

	public function test_no_admin_recibe_403(): void
	{
		Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

		$this->actingAs(User::factory()->create())->get(route('admin.auditoria'))->assertForbidden();
	}

	public function test_filtra_por_entidad(): void
	{
		$admin = $this->admin();
		Specialty::create(['name' => 'Pediatria']);

		$respuesta = $this->actingAs($admin)->get(route('admin.auditoria', ['entidad' => 'especialidad']));

		$respuesta->assertOk();
		$this->assertTrue($respuesta->viewData('registros')->every(fn ($r) => $r->model_type === Specialty::class));
	}

	public function test_filtro_invalido_se_rechaza(): void
	{
		$admin = $this->admin();

		$this->actingAs($admin)->get(route('admin.auditoria', ['ip' => 'no-es-ip', 'entidad' => 'tabla_inventada']))
			->assertSessionHasErrors(['ip', 'entidad']);
	}

	public function test_detalle_muestra_antes_y_despues(): void
	{
		$admin = $this->admin();
		$this->actingAs($admin);
		$especialidad = Specialty::create(['name' => 'Pediatria']);
		$especialidad->update(['name' => 'Pediatria general']);
		$registro = ActivityLog::where('action', 'actualizado')->where('model_id', $especialidad->id)->firstOrFail();

		$this->get(route('admin.auditoria.show', $registro))
			->assertOk()
			->assertSeeInOrder(['name', 'Pediatria', 'Pediatria general']);
	}

	public function test_linea_de_tiempo_de_una_entidad(): void
	{
		$admin = $this->admin();
		$this->actingAs($admin);
		$especialidad = Specialty::create(['name' => 'Pediatria']);
		$especialidad->update(['name' => 'Pediatria general']);

		$this->get(route('admin.auditoria.entidad', ['entidad' => 'especialidad', 'id' => $especialidad->id]))
			->assertOk()
			->assertSeeInOrder(['actualizado', 'creado']);
	}

	public function test_exportar_csv_escapa_formulas_y_queda_auditado(): void
	{
		$admin = $this->admin();
		$this->actingAs($admin);
		// La descripcion es una celda propia del CSV: ahi es donde una formula seria peligrosa
		ActivityLog::create(['action' => 'prueba', 'description' => '=HYPERLINK("http://malicioso")']);

		$respuesta = $this->get(route('admin.auditoria.exportar'));

		$respuesta->assertOk();
		$contenido = $respuesta->streamedContent();
		$this->assertStringContainsString("'=HYPERLINK", $contenido);
		$this->assertDoesNotMatchRegularExpression('/(^|,)"?=HYPERLINK/m', $contenido);
		$this->assertTrue(ActivityLog::where('action', 'auditoria_exportada')->where('user_id', $admin->id)->exists());
	}

	// Regresion: el escape por defecto de fputcsv ('\') invierte la comilla de
	// enclosure en vez de duplicarla (RFC 4180). Un lector estricto (Excel,
	// LibreOffice, csv de Python) corta ese campo distinto y puede dejar una
	// celda de formula sin la comilla protectora de CsvSeguro.
	public function test_exportar_csv_no_invierte_comillas_por_el_escape_de_fputcsv(): void
	{
		$admin = $this->admin();
		$this->actingAs($admin);
		ActivityLog::create(['action' => 'prueba', 'description' => 'x\",=1+1,\"']);
		ActivityLog::create(['action' => 'prueba', 'description' => '=CMD()']);

		$respuesta = $this->get(route('admin.auditoria.exportar'));

		$respuesta->assertOk();
		$contenido = $respuesta->streamedContent();
		$lineas = array_values(array_filter(preg_split('/\r\n|\n|\r/', $contenido), fn ($linea) => $linea !== ''));

		$encabezado = str_getcsv($lineas[0], ',', '"', '');
		$columnas_encabezado = count($encabezado);
		$this->assertSame(11, $columnas_encabezado);

		foreach (array_slice($lineas, 1) as $linea) {
			$celdas = str_getcsv($linea, ',', '"', '');
			$this->assertSame($columnas_encabezado, count($celdas), "Fila con numero de columnas distinto al encabezado: {$linea}");

			foreach ($celdas as $celda) {
				$this->assertFalse(str_starts_with((string) $celda, '='), "Celda de formula sin escapar: {$celda}");
			}
		}
	}

	public function test_linea_tiempo_con_id_desbordado_responde_404(): void
	{
		$admin = $this->admin();
		$this->actingAs($admin);

		$this->get(route('admin.auditoria.entidad', ['entidad' => 'especialidad', 'id' => '99999999999999999999']))
			->assertNotFound();
	}

	public function test_linea_tiempo_con_entidad_inexistente_responde_404(): void
	{
		$admin = $this->admin();
		$this->actingAs($admin);

		$this->get(route('admin.auditoria.entidad', ['entidad' => 'tabla_inventada', 'id' => 1]))
			->assertNotFound();
	}

	public function test_show_con_id_inexistente_responde_404(): void
	{
		$admin = $this->admin();
		$this->actingAs($admin);

		$this->get(route('admin.auditoria.show', 999999))->assertNotFound();
	}

	public function test_no_admin_recibe_403_en_show(): void
	{
		Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
		$especialidad = Specialty::create(['name' => 'Pediatria']);
		$registro = ActivityLog::where('action', 'creado')->where('model_id', $especialidad->id)->firstOrFail();

		$this->actingAs(User::factory()->create())->get(route('admin.auditoria.show', $registro))->assertForbidden();
	}

	public function test_no_admin_recibe_403_en_linea_tiempo(): void
	{
		Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
		$especialidad = Specialty::create(['name' => 'Pediatria']);

		$this->actingAs(User::factory()->create())
			->get(route('admin.auditoria.entidad', ['entidad' => 'especialidad', 'id' => $especialidad->id]))
			->assertForbidden();
	}

	public function test_no_admin_recibe_403_en_exportar(): void
	{
		Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

		$this->actingAs(User::factory()->create())->get(route('admin.auditoria.exportar'))->assertForbidden();
	}
}
```

- [ ] **Paso 2: Correr las pruebas y verificar que fallan**

Run: `pruebas --filter="CsvSeguroTest|VisorAuditoriaTest"`
Expected: FAIL (clase y rutas inexistentes).

- [ ] **Paso 3: `CsvSeguro` y el FormRequest**

`app/Support/CsvSeguro.php`:

```php
<?php

namespace App\Support;

// Celdas de CSV que una hoja de calculo no interpreta como formula
final class CsvSeguro
{
	private const INICIOS_PELIGROSOS = ['=', '+', '-', '@', "\t", "\r"];

	public static function celda(mixed $valor): string
	{
		if (is_array($valor)) {
			$valor = json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}
		$texto = (string) ($valor ?? '');

		if ($texto !== '' && in_array($texto[0], self::INICIOS_PELIGROSOS, true)) {
			return "'" . $texto;
		}

		return $texto;
	}
}
```

`app/Http/Requests/FiltrarAuditoriaRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Http\Controllers\AuditoriaController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltrarAuditoriaRequest extends FormRequest
{
	// La ruta ya exige role:admin
	public function authorize(): bool
	{
		return true;
	}

	public function rules(): array
	{
		return [
			'user_id' => ['nullable', 'integer', 'exists:users,id'],
			'action' => ['nullable', 'string', 'max:100'],
			'entidad' => ['nullable', Rule::in(array_keys(AuditoriaController::ENTIDADES))],
			'desde' => ['nullable', 'date'],
			'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
			'ip' => ['nullable', 'ip'],
		];
	}
}
```

- [ ] **Paso 4: Controlador y rutas**

`app/Http/Controllers/AuditoriaController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\FiltrarAuditoriaRequest;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\PatientProfile;
use App\Models\Schedule;
use App\Models\Specialty;
use App\Models\User;
use App\Services\Auditoria\RegistradorAuditoria;
use App\Support\CsvSeguro;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AuditoriaController extends Controller
{
	// Alias de URL a clase: la URL nunca lleva nombres de clase
	public const ENTIDADES = [
		'cita' => Appointment::class,
		'usuario' => User::class,
		'especialidad' => Specialty::class,
		'horario' => Schedule::class,
		'perfil_doctor' => DoctorProfile::class,
		'perfil_paciente' => PatientProfile::class,
	];

	public function index(FiltrarAuditoriaRequest $request): View
	{
		$filtros = $request->validated();

		return view('admin.auditoria.index', [
			'registros' => $this->consulta($filtros)->paginate(25)->withQueryString(),
			'filtros' => $filtros,
			'entidades' => self::ENTIDADES,
			'acciones' => ActivityLog::query()->distinct()->orderBy('action')->pluck('action'),
			'usuarios' => User::query()->orderBy('name')->get(['id', 'name']),
			'integridad' => Cache::get('auditoria.integridad'),
		]);
	}

	public function show(ActivityLog $registro): View
	{
		$anteriores = $registro->old_values ?? [];
		$nuevos = $registro->new_values ?? [];

		return view('admin.auditoria.show', [
			'registro' => $registro->load('user:id,name'),
			'campos' => array_values(array_unique(array_merge(array_keys($anteriores), array_keys($nuevos)))),
			'anteriores' => $anteriores,
			'nuevos' => $nuevos,
			'entidad' => array_search($registro->model_type, self::ENTIDADES, true) ?: null,
			'url_traza' => $this->url_traza($registro->trace_id),
		]);
	}

	public function linea_tiempo(string $entidad, int $id): View
	{
		abort_unless(isset(self::ENTIDADES[$entidad]), 404);

		return view('admin.auditoria.linea-tiempo', [
			'entidad' => $entidad,
			'id' => $id,
			'registros' => ActivityLog::query()
				->with('user:id,name')
				->where('model_type', self::ENTIDADES[$entidad])
				->where('model_id', $id)
				->orderByDesc('id')
				->get(),
		]);
	}

	public function exportar(FiltrarAuditoriaRequest $request, RegistradorAuditoria $registrador): StreamedResponse
	{
		$filtros = $request->validated();
		$registrador->registrar('auditoria_exportada', nuevos: $filtros, descripcion: 'Exportación CSV de la auditoría');

		return response()->streamDownload(function () use ($filtros) {
			// $escape = '' desactiva el escape con backslash de fputcsv (invierte comillas
			// dentro de la celda y no es RFC 4180): sin el, un valor con `",` controlado
			// por el usuario podria correr una celda hacia una formula sin escapar.
			$salida = fopen('php://output', 'w');

			try {
				fputcsv($salida, ['id', 'fecha', 'usuario', 'accion', 'entidad', 'id_entidad', 'ip', 'descripcion', 'antes', 'despues', 'trace_id'], ',', '"', '');

				$this->consulta($filtros)->reorder()->lazyByIdDesc(500)->each(function (ActivityLog $registro) use ($salida) {
					fputcsv($salida, array_map([CsvSeguro::class, 'celda'], [
						$registro->id,
						$registro->created_at?->toIso8601String(),
						$registro->user?->name,
						$registro->action,
						$registro->model_type,
						$registro->model_id,
						$registro->ip_address,
						$registro->description,
						$registro->old_values,
						$registro->new_values,
						$registro->trace_id,
					]), ',', '"', '');
				});
			} catch (Throwable $error) {
				// No se expone el mensaje de la excepcion en el CSV: solo queda en el log
				Log::error('Falló la exportación de auditoría', ['error' => $error->getMessage()]);
				fputcsv($salida, ['ERROR: exportación incompleta'], ',', '"', '');
			} finally {
				fclose($salida);
			}
		}, 'auditoria-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
	}

	private function consulta(array $filtros): Builder
	{
		return ActivityLog::query()
			->with('user:id,name')
			->when($filtros['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
			->when($filtros['action'] ?? null, fn ($q, $v) => $q->where('action', $v))
			->when($filtros['entidad'] ?? null, fn ($q, $v) => $q->where('model_type', self::ENTIDADES[$v]))
			->when($filtros['desde'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', Carbon::parse($v)->startOfDay()))
			->when($filtros['hasta'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', Carbon::parse($v)->endOfDay()))
			->when($filtros['ip'] ?? null, fn ($q, $v) => $q->where('ip_address', $v))
			->orderByDesc('id');
	}

	// Enlace a la traza en Grafana Explore (Tempo), si el registro tiene trace_id
	private function url_traza(?string $trace_id): ?string
	{
		if ($trace_id === null) {
			return null;
		}

		$panel = [
			'datasource' => 'tempo',
			'queries' => [['refId' => 'A', 'queryType' => 'traceql', 'query' => $trace_id]],
			'range' => ['from' => 'now-2d', 'to' => 'now'],
		];

		return rtrim((string) config('auditoria.grafana_url'), '/') . '/explore?left=' . rawurlencode(json_encode($panel));
	}
}
```

En `routes/web.php`, dentro del grupo admin (después de las rutas de `/admin/logs`), con su `use App\Http\Controllers\AuditoriaController;` arriba:

```php
	// Visor de auditoria (unidad 3)
	Route::get('/admin/auditoria', [AuditoriaController::class, 'index'])->name('admin.auditoria');
	Route::get('/admin/auditoria/exportar', [AuditoriaController::class, 'exportar'])->name('admin.auditoria.exportar');
	// [0-9]{1,18} en vez de whereNumber(): un entero de 19+ digitos pasa la regex de
	// whereNumber pero desborda el `int $id` del controlador y responde 500
	Route::get('/admin/auditoria/entidad/{entidad}/{id}', [AuditoriaController::class, 'linea_tiempo'])->where('id', '[0-9]{1,18}')->name('admin.auditoria.entidad');
	Route::get('/admin/auditoria/{registro}', [AuditoriaController::class, 'show'])->whereNumber('registro')->name('admin.auditoria.show');
```

- [ ] **Paso 5: Vistas**

`resources/views/admin/auditoria/index.blade.php` (misma estructura que `admin/logs/index.blade.php`: overlay, `mobile-toggle`, `<x-sidebar active="admin-auditoria" variant="admin" />`, `<x-topbar ...>`; `@vite(['resources/css/app.css', 'resources/js/topbar-date.js'])`):

```blade
<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>MedSchedule - Auditoría</title>
	<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet" />
	<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
	@vite(['resources/css/app.css', 'resources/js/topbar-date.js'])
</head>

<body>
	<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>
	<button class="mobile-toggle" onclick="toggleSidebar()"><i class="bi bi-list" style="font-size: 24px;"></i></button>

	<div class="app-wrapper">
		<x-sidebar active="admin-auditoria" variant="admin" />

		<div class="content-wrapper">
			<x-topbar title="Auditoría" icon="bi bi-shield-check" subtitle="Admin / Auditoría"
				:show-avatar-menu="true" badge-text="admin" badge-tone="danger" avatar-text="AD" avatar-color="#1976d2" />

			<div class="dashboard-content p-4">
				{{-- Estado de integridad: ultimo resultado de auditoria:verificar --}}
				@if ($integridad === null)
					<div class="alert alert-secondary" data-testid="integridad">Integridad aún no verificada. Ejecutar <code>php artisan auditoria:verificar</code>.</div>
				@elseif ($integridad['estado'] === 'integra')
					<div class="alert alert-success" data-testid="integridad"><i class="bi bi-shield-check"></i> Cadena íntegra · {{ $integridad['revisados'] }} registros · verificada {{ $integridad['verificado_en'] }}</div>
				@else
					<div class="alert alert-danger" data-testid="integridad"><i class="bi bi-shield-exclamation"></i> Cadena comprometida en el registro <a href="{{ route('admin.auditoria.show', $integridad['id_roto']) }}">#{{ $integridad['id_roto'] }}</a> ({{ $integridad['fecha_rota'] }}) · verificada {{ $integridad['verificado_en'] }}</div>
				@endif

				<form method="GET" action="{{ route('admin.auditoria') }}" class="card border-0 shadow-sm mb-4">
					<div class="card-body row g-3 align-items-end">
						<div class="col-lg-2 col-md-4">
							<label class="form-label" for="user_id">Usuario</label>
							<select class="form-select" id="user_id" name="user_id">
								<option value="">Todos</option>
								@foreach ($usuarios as $usuario)
									<option value="{{ $usuario->id }}" @selected(($filtros['user_id'] ?? null) == $usuario->id)>{{ $usuario->name }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-lg-2 col-md-4">
							<label class="form-label" for="action">Acción</label>
							<select class="form-select" id="action" name="action">
								<option value="">Todas</option>
								@foreach ($acciones as $accion)
									<option value="{{ $accion }}" @selected(($filtros['action'] ?? null) === $accion)>{{ $accion }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-lg-2 col-md-4">
							<label class="form-label" for="entidad">Entidad</label>
							<select class="form-select" id="entidad" name="entidad">
								<option value="">Todas</option>
								@foreach (array_keys($entidades) as $alias)
									<option value="{{ $alias }}" @selected(($filtros['entidad'] ?? null) === $alias)>{{ $alias }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-lg-2 col-md-4">
							<label class="form-label" for="desde">Desde</label>
							<input class="form-control" id="desde" name="desde" type="date" value="{{ $filtros['desde'] ?? '' }}" />
						</div>
						<div class="col-lg-2 col-md-4">
							<label class="form-label" for="hasta">Hasta</label>
							<input class="form-control" id="hasta" name="hasta" type="date" value="{{ $filtros['hasta'] ?? '' }}" />
						</div>
						<div class="col-lg-2 col-md-4">
							<label class="form-label" for="ip">IP</label>
							<input class="form-control" id="ip" name="ip" type="text" value="{{ $filtros['ip'] ?? '' }}" />
						</div>
						<div class="col-12 d-flex gap-2">
							<button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
							<a class="btn btn-outline-secondary" href="{{ route('admin.auditoria') }}">Limpiar</a>
							<a class="btn btn-outline-success ms-auto" href="{{ route('admin.auditoria.exportar', request()->query()) }}"><i class="bi bi-filetype-csv"></i> Exportar CSV</a>
						</div>
						@if ($errors->any())
							<div class="col-12"><div class="alert alert-warning mb-0">{{ implode(' ', $errors->all()) }}</div></div>
						@endif
					</div>
				</form>

				<div class="card border-0 shadow-sm">
					<div class="table-responsive">
						<table class="table table-hover align-middle mb-0">
							<thead>
								<tr><th>#</th><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Entidad</th><th>IP</th><th>Descripción</th><th></th></tr>
							</thead>
							<tbody>
								@forelse ($registros as $registro)
									<tr>
										<td>{{ $registro->id }}</td>
										<td>{{ $registro->created_at?->format('Y-m-d H:i:s') }}</td>
										<td>{{ $registro->user?->name ?? 'Sistema' }}</td>
										<td><span class="badge bg-secondary">{{ $registro->action }}</span></td>
										<td>{{ class_basename((string) $registro->model_type) }} {{ $registro->model_id ? '#' . $registro->model_id : '' }}</td>
										<td>{{ $registro->ip_address }}</td>
										<td>{{ $registro->description }}</td>
										<td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.auditoria.show', $registro) }}">Detalle</a></td>
									</tr>
								@empty
									<tr><td colspan="8" class="text-center text-muted py-4">Sin registros para estos filtros.</td></tr>
								@endforelse
							</tbody>
						</table>
					</div>
					<div class="p-3">{{ $registros->links('pagination::bootstrap-5') }}</div>
				</div>
			</div>
		</div>
	</div>
</body>

</html>
```

`resources/views/admin/auditoria/show.blade.php` (mismo esqueleto `head`/`sidebar`/`topbar`, título "Detalle de auditoría"; contenido):

```blade
				<div class="card border-0 shadow-sm mb-4">
					<div class="card-body">
						<h2 class="h5">Registro #{{ $registro->id }} · <span class="badge bg-secondary">{{ $registro->action }}</span></h2>
						<p class="mb-1"><strong>Quién:</strong> {{ $registro->user?->name ?? 'Sistema' }}</p>
						<p class="mb-1"><strong>Cuándo:</strong> {{ $registro->created_at?->format('Y-m-d H:i:s') }} UTC</p>
						<p class="mb-1"><strong>Desde:</strong> {{ $registro->ip_address }} · {{ $registro->user_agent }}</p>
						<p class="mb-1"><strong>Qué:</strong> {{ $registro->description }}</p>
						@if ($entidad && $registro->model_id)
							<p class="mb-1"><a href="{{ route('admin.auditoria.entidad', ['entidad' => $entidad, 'id' => $registro->model_id]) }}">Ver línea de tiempo de {{ $entidad }} #{{ $registro->model_id }}</a></p>
						@endif
						@if ($url_traza)
							<p class="mb-0"><a href="{{ $url_traza }}" target="_blank" rel="noopener">Ver traza de la petición</a></p>
						@endif
					</div>
				</div>

				<div class="card border-0 shadow-sm">
					<table class="table mb-0">
						<thead><tr><th>Campo</th><th>Antes</th><th>Después</th></tr></thead>
						<tbody>
							@forelse ($campos as $campo)
								<tr>
									<td><code>{{ $campo }}</code></td>
									<td class="text-danger">{{ is_array($anteriores[$campo] ?? null) ? json_encode($anteriores[$campo]) : ($anteriores[$campo] ?? '—') }}</td>
									<td class="text-success">{{ is_array($nuevos[$campo] ?? null) ? json_encode($nuevos[$campo]) : ($nuevos[$campo] ?? '—') }}</td>
								</tr>
							@empty
								<tr><td colspan="3" class="text-muted">Este evento no registra cambios de campos.</td></tr>
							@endforelse
						</tbody>
					</table>
				</div>
```

`resources/views/admin/auditoria/linea-tiempo.blade.php` (mismo esqueleto, título "Línea de tiempo"; contenido):

```blade
				<h2 class="h5 mb-3">{{ $entidad }} #{{ $id }}</h2>
				<ul class="list-group">
					@forelse ($registros as $registro)
						<li class="list-group-item d-flex justify-content-between align-items-center">
							<span>
								<span class="badge bg-secondary">{{ $registro->action }}</span>
								{{ $registro->created_at?->format('Y-m-d H:i:s') }} · {{ $registro->user?->name ?? 'Sistema' }}
							</span>
							<a class="btn btn-sm btn-outline-primary" href="{{ route('admin.auditoria.show', $registro) }}">Detalle</a>
						</li>
					@empty
						<li class="list-group-item text-muted">Sin eventos para esta entidad.</li>
					@endforelse
				</ul>
```

En `resources/views/components/sidebar.blade.php`, después del bloque de "Logs" del menú admin:

```blade
            <div class="menu-item">
                <a class="menu-link {{ $active === 'admin-auditoria' ? 'active' : '' }}" href="{{ route('admin.auditoria') }}">
                    <div class="menu-link-content">
                        <i class="bi bi-shield-check"></i>
                        <span>Auditoría</span>
                    </div>
                </a>
            </div>
```

- [ ] **Paso 6: Correr las pruebas y verificar que pasan**

Run: `pruebas --filter="CsvSeguroTest|VisorAuditoriaTest|Auditoria"`
Expected: PASS en todas las de `tests/Feature/Auditoria` y las dos unitarias. El grupo admin corre `auth`, `throttle:60,1`, `auditar.denegado`, `role:admin` (T018). La exportación usa `fputcsv` con escape `''` (sin el escape con barra invertida, que corrompe campos que contienen `\"`), envuelve el stream en `try/catch` con registro en log, y `VisorAuditoriaTest` cubre 404 para ids inválidos o fuera de rango y 403 para no admin.

- [ ] **Paso 7: Commit**

```bash
git add app/Http/Controllers/AuditoriaController.php app/Http/Requests/FiltrarAuditoriaRequest.php app/Support/CsvSeguro.php resources/views/admin/auditoria resources/views/components/sidebar.blade.php routes/web.php tests/Unit/CsvSeguroTest.php tests/Feature/Auditoria/VisorAuditoriaTest.php
git diff --staged
git commit -m "feat(auditoria): visor con filtros, diff, linea de tiempo y exportacion CSV segura"
```

### T021 [US4] Evidencia antes/después de la auditoría

**Files:**
- Create: `docs/entrega-u3/evidencia/auditoria-01-antes-sin-rastro.png`, `auditoria-02-antes-alteracion.png`, `auditoria-03-listado.png`, `auditoria-04-diff.png`, `auditoria-05-linea-tiempo.png`, `auditoria-06-alteracion-detectada.png`, `auditoria-verificar-integra.txt`, `auditoria-verificar-rota.txt`

- [ ] **Paso 1: "Antes" en la rama de la U2**

En un worktree o checkout temporal de `feat/100-sonarqube`: como admin, cambiar fecha y doctor de una cita; consultar `activity_logs` y mostrar que no hay registro del cambio (`auditoria-01`). Alterar a mano una fila de `activity_logs` en MySQL; nada lo detecta (`auditoria-02`).

- [ ] **Paso 2: "Después" en la rama de auditoría**

Antes de migrar: fijar `AUDIT_HMAC_KEY` en `.env` (sin ella la migración no puede sellar) y poner la app en modo mantenimiento para que nadie escriba auditoría mientras se sella la cadena. No alternar ramas sobre la misma base de datos durante la evidencia (la rama de la U2 escribe filas sin sello que rompen la cadena).

```bash
php artisan down
php artisan migrate
php artisan up
```

`migrate` sella **todas** las filas existentes, incluida la que se alteró a mano en el "antes": esa alteración queda sellada como válida, así que para la evidencia de detección hay que alterar una fila **otra vez después** de migrar.

Repetir el cambio de la cita; capturar el listado (`auditoria-03`), el diff (`auditoria-04`) y la línea de tiempo de la cita (`auditoria-05`).

```bash
php artisan auditoria:verificar | tee docs/entrega-u3/evidencia/auditoria-verificar-integra.txt
# alterar a mano otra vez la misma fila que en el "antes" (la migracion ya la sello), con el cliente mysql de MAMP
php artisan auditoria:verificar | tee docs/entrega-u3/evidencia/auditoria-verificar-rota.txt; echo "codigo: ${PIPESTATUS[0]}" | tee -a docs/entrega-u3/evidencia/auditoria-verificar-rota.txt
```

Expected: la segunda corrida señala el id alterado y termina con código 1. Capturar el visor con el indicador rojo (`auditoria-06`). Revisar que ninguna captura muestre PII real (usar datos de prueba del seeder).

- [ ] **Paso 3: Commit**

```bash
git add docs/entrega-u3/evidencia/auditoria-*
git commit -m "docs(entrega): evidencia antes y despues del visor de auditoria"
```

**Checkpoint**: US4 completa; PR del punto 3 listo para abrir.

---

## Phase 6: Polish — documento de entrega y PRs (rama `feat/<R17>-u3-sdd`)

### T022 Documento de entrega

**Files:**
- Create: `docs/entrega-u3/00-indice.md` … `08-integracion-cicd.md` (tabla de `plan.md` §5)
- Create (local, gitignored): `docs/entrega-u3/MedSchedule_Entrega_U3.docx`

**Interfaces:**
- Consumes: toda la evidencia de `docs/entrega-u3/evidencia/`; ligas de los PRs de T023.

- [ ] **Paso 1: Traer la evidencia de las ramas de los puntos**

```bash
git switch feat/<R17>-u3-sdd
git checkout feat/<R19>-trazabilidad -- docs/entrega-u3/evidencia/monitoreo-* docs/entrega-u3/evidencia/trazas-* docs/entrega-u3/evidencia/k6-*-instrumentacion.txt
git checkout feat/<R20>-auditoria -- docs/entrega-u3/evidencia/auditoria-*
```

- [ ] **Paso 2: Escribir los nueve documentos**

Contenido obligatorio por documento (formato y tono de `docs/entrega-u2/`):

| Doc | Debe contener |
|---|---|
| 00 | Datos de la entrega (autor `ramonibr`, materia, grupo, profesor, fecha, repo). **Primer apartado: "Dashboards de SonarQube (observación de la unidad 2)"** con las seis capturas `sonar-0*.png` incrustadas y la explicación de la puerta de calidad (salida de `sonar-puerta-pasa.txt` y `sonar-puerta-falla.txt`). Después: método de verificación (tabla dato → archivo de evidencia) e índice de documentos. |
| 01 | Caso de estudio y justificación del pipeline: etapas integración → calidad → pruebas → liberación → despliegue, qué agrega la U3 (puerta de calidad, observabilidad). |
| 02 | Entorno requerido: Codespaces/devcontainer, Docker local, puertos del stack, variables de entorno (nombres, nunca valores). Docker Desktop necesita **File sharing** de `/Applications/MAMP/htdocs/MedSchedule-` (Alloy monta `storage/logs`); `LOG_STACK=single,json` para que exista `medschedule.json`. |
| 03 | Niveles de servicio: tabla SLO → alerta de `alertas.yml` → evidencia de disparo. |
| 04 | Punto 1: métricas expuestas (nombre, tipo, etiquetas), dashboards, alertas; antes/después con `monitoreo-*`; tiempo de notificación medido (SC-003); liga al PR del punto 1. |
| 05 | Parámetros de configuración de cada herramienta (Prometheus, Alertmanager, Grafana, Loki, Tempo, Alloy, exporters, SonarQube, k6), con versión fijada y archivo donde vive. |
| 06 | Punto 2: logs estructurados, redacción de PII, trazas, correlación; antes/después con `trazas-*`; costo de la instrumentación (k6 con y sin); liga al PR del punto 2. |
| 07 | Punto 3: qué se audita, sello HMAC encadenado y sus límites completos (borrado de las últimas filas; quien posee `AUDIT_HMAC_KEY` puede resellar la cadena; `id` y `updated_at` fuera del contenido canónico; rotar la llave obliga a resellar; migrar en modo mantenimiento), FK de `activity_logs.user_id` quitada para que borrar un usuario no rompa la cadena, verificación diaria que requiere el cron `schedule:run`, visor; antes/después con `auditoria-*`; hallazgo de PII en claro en `PatientProfileController` y su corrección; liga al PR del punto 3. |
| 08 | Integración CI/CD: jobs de `ci.yml` y `release.yml`, puerta de calidad, dónde corre cada compuerta. Los jobs de Sonar corren solo si `vars.SONAR_HABILITADO == 'true'`; las pruebas de `ci.yml` heredan un `\|\| true` (issue #86), así que la compuerta de pruebas no es total y se dice explícitamente. |

Cada cifra debe salir de un archivo de `evidencia/`. Donde algo no se midió, decirlo.

- [ ] **Paso 3: Generar el DOCX**

```bash
cd docs/entrega-u3
pandoc 00-indice.md 01-*.md 02-*.md 03-*.md 04-*.md 05-*.md 06-*.md 07-*.md 08-*.md \
  --resource-path=. --toc -o MedSchedule_Entrega_U3.docx
cd -
git check-ignore -v docs/entrega-u3/MedSchedule_Entrega_U3.docx
```

Expected: el DOCX existe y `check-ignore` muestra la regla que lo excluye. Abrirlo y comprobar que las capturas de SonarQube aparecen en la primera sección.

- [ ] **Paso 4: Commit**

```bash
git add docs/entrega-u3/*.md docs/entrega-u3/evidencia
git diff --staged --stat
git commit -m "docs(entrega): documento de la unidad 3 con dashboards de SonarQube al inicio"
```

### T023 Pull requests y tablero

**Files:** ninguno. Solo GitHub.

- [ ] **Paso 1: Confirmar con el usuario antes de publicar**

Mostrar ramas, bases y títulos. Esperar aprobación explícita para `git push` y `gh pr create`.

| Rama | Base | Título |
|---|---|---|
| `feat/<R17>-u3-sdd` | `feat/100-sonarqube` | `docs(sdd): SDD y documento de entrega de la unidad 3 (#<R17>)` |
| `feat/<R18>-monitoreo` | `feat/<R17>-u3-sdd` | `feat(monitoreo): Prometheus, Grafana y alertas (#<R18>)` |
| `feat/<R19>-trazabilidad` | `feat/<R18>-monitoreo` | `feat(trazas): visor de trazabilidad con Loki y Tempo (#<R19>)` |
| `feat/<R20>-auditoria` | `feat/<R17>-u3-sdd` | `feat(auditoria): visor de auditoria con deteccion de manipulacion (#<R20>)` |

- [ ] **Paso 2: Cuerpos de PR con imágenes renderizadas**

Cuerpo en archivo temporal por PR: resumen, antes/después **de comportamiento** con imágenes como `![descripcion](https://raw.githubusercontent.com/JoseOrtega8/MedSchedule-/refs/heads/<rama>/docs/entrega-u3/evidencia/<archivo>.png)`, cómo probar, `Closes #<n>`. Sin trailers de atribución. El PR general muestra las seis capturas de SonarQube al inicio.

- [ ] **Paso 3: Push y PRs**

```bash
git push -u origin feat/<R17>-u3-sdd feat/<R18>-monitoreo feat/<R19>-trazabilidad feat/<R20>-auditoria
gh pr create --repo JoseOrtega8/MedSchedule- --base feat/100-sonarqube --head feat/<R17>-u3-sdd --title "..." --body-file "$SCRATCH/pr-r17.md"
# igual para los otros tres con su base
```

Abrir cada PR en el navegador y comprobar que las imágenes se ven (no rutas de texto).

- [ ] **Paso 4: Tablero y comentarios**

Mover R17–R20 a "In review" (mismos comandos de T001 paso 4). Comentar en cada issue un resumen con el hash del último commit y la liga del PR.

---

## Dependencies & Execution Order

- T001 → T002 → T003 → T004 (rama general).
- T005 → T006 → T007 → T008 → T009 (rama de monitoreo; T007 puede ir en paralelo a T005–T006 [P], archivos distintos).
- T010 → T011 → T012 → T013 → T014 → T015 (rama de trazabilidad; necesita T008 terminado).
- T016 → T017 → T018 → T019 → T020 → T021 (rama de auditoría; independiente de US2/US3, puede ir en paralelo a ellas).
- T022 necesita la evidencia de T004, T009, T015 y T021. T023 al final.

## Cobertura del spec

| Requisito | Tareas |
|---|---|
| FR-001, FR-002 | T004, T022, T023 |
| FR-003 | T003, T004 |
| FR-004, FR-005 | T005, T006 |
| FR-006 | T008 |
| FR-007, FR-008 | T007, T008, T009 |
| FR-009, FR-010 | T012 |
| FR-011 | T010, T011 |
| FR-012 | T014 |
| FR-013 | T013 |
| FR-014, FR-016 | T017 |
| FR-015 | T018 |
| FR-017, FR-018 | T016, T019 |
| FR-019, FR-020, FR-021, FR-022 | T020 |
| SC-001 … SC-008 | T004, T009, T015, T017, T021, T022 |
