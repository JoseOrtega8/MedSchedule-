# 9. Módulo adicional: compuerta de vulnerabilidades en dependencias con Snyk

**Pull request:** [[PR-109]]

## 9.1 Qué resuelve

MedSchedule depende de paquetes de terceros declarados en `composer.lock` (PHP) y
`package-lock.json` (JavaScript). Antes de este módulo ninguno de los dos se analizaba en ningún
punto del pipeline: una dependencia con una vulnerabilidad conocida llegaba a producción y solo
se sabía por un aviso externo. SonarQube analiza el código propio, no el de las dependencias.

Este módulo no forma parte de los tres puntos del enunciado. Se agrega como historia de usuario
propia (US5) en la especificación, en su propia rama (`feat/109-snyk`), sin modificar los otros
módulos salvo el job nuevo de `release.yml`.

## 9.2 Qué analiza

`scripts/snyk-escanear.sh` ejecuta:

`npx --yes snyk@1.1307.4 test --all-projects --severity-threshold=high --json-file-output=<salida>`

| Parámetro | Por qué |
|---|---|
| `snyk@1.1307.4` vía `npx --yes` | CLI fijado por versión, sin instalación global ni entrada en `package.json` |
| `--all-projects` | Detecta y analiza a la vez `composer.lock` y `package-lock.json` |
| `--severity-threshold=high` | Solo las vulnerabilidades altas y críticas cuentan para la compuerta |
| `--json-file-output` | Deja el detalle en `docs/entrega-u3/evidencia/snyk-resultado.json`, o en la ruta de `SNYK_SALIDA` |

Con `--monitor` el script corre además `snyk monitor --all-projects`, que sube una instantánea
al dashboard de Snyk. Solo lo hace si el análisis terminó en 0 o 1, y su fallo no cambia el
resultado de la compuerta.

## 9.3 La compuerta y sus códigos de salida

El script traduce los códigos del CLI de Snyk a tres resultados:

| Situación | Código de Snyk | Código del script | Efecto en la liberación |
|---|---|---|---|
| Sin vulnerabilidades altas o críticas | 0 | 0 | Continúa |
| Vulnerabilidades altas o críticas | 1 | 1 | Se detiene |
| Error de ejecución o de autenticación | 2 | 2 | Se detiene |
| Ningún proyecto soportado | 3 | 2 | Se detiene |
| Código no documentado | otro | 2 | Se detiene |
| Sin `SNYK_TOKEN` en la sesión | — (no se ejecuta) | 1 | En local, falla con un mensaje en stderr |

El token se lee únicamente de la variable de entorno `SNYK_TOKEN`; el script nunca lo imprime ni
lo escribe en un archivo.

El script tiene pruebas en bash puro, `tests/scripts/snyk-escanear.test.sh`, que ponen un
`npx` falso al frente del `PATH` y comprueban cada código de salida, los argumentos exactos del
CLI, la condición de `--monitor` y que un token falso reconocible no aparezca en la salida. Se
ejecutan con `npm run test:scripts`; la corrida termina con `Resumen: 21/21 pruebas OK`.

## 9.4 Integración en `release.yml`

El job `seguridad` ("Dependencias (Snyk)") corre después de `integracion`, en paralelo con
`calidad`:

| Paso | Qué hace |
|---|---|
| Verificar token | Si el secreto `SNYK_TOKEN` existe expone `hay=true`; si no, `hay=false` y emite un `::notice::` visible en la corrida |
| Analizar dependencias | Solo si `hay == 'true'`: `bash scripts/snyk-escanear.sh` |
| Publicar evidencia | Siempre: sube `snyk-resultado.json` como artefacto por 30 días, si existe |

El job de pruebas exige `needs.seguridad.result == 'success'`. Sin token el job termina en
éxito con el aviso, así que la falta de configuración no bloquea el pipeline; con token, una
vulnerabilidad alta o crítica lo detiene antes de las pruebas y del despliegue.

## 9.5 Política de excepciones `.snyk`

El archivo `.snyk` en la raíz (`version: v1.25.0`, `ignore: {}`) es la única forma de exentar un
hallazgo de la compuerta. Cada excepción debe llevar `reason` (por qué no se corrige ahora) y
`expires` (fecha ISO 8601). Al cumplirse esa fecha, Snyk vuelve a contar el hallazgo como
bloqueante aunque siga en la lista: una excepción no puede quedar olvidada. Hoy la política no
tiene excepciones.

## 9.6 Antes y después

### Antes: nadie revisa las dependencias

Antes de este módulo, el pipeline no tenía ningún paso que leyera `composer.lock` ni
`package-lock.json`; `release.yml` pasaba de la integración a las pruebas.

### Después: la compuerta decide

Corrida con las dependencias actuales del proyecto:

[[PENDIENTE: resultado y código de salida de la corrida limpia, de evidencia/snyk-puerta-pasa.txt]]

Corrida con una dependencia vulnerable agregada a propósito:

[[PENDIENTE: dependencia agregada, vulnerabilidad reportada, severidad y código de salida, de evidencia/snyk-puerta-falla.txt]]

![Reporte del análisis de Snyk sobre composer.lock y package-lock.json](evidencia/snyk-01-reporte.png)

![Dashboard de Snyk con la instantánea del proyecto subida con --monitor](evidencia/snyk-02-dashboard.png)

## 9.7 Límites

- Sin `SNYK_TOKEN` no hay análisis: queda el aviso en la corrida, no una compuerta fallida.
- El análisis se autentica con la cuenta gratuita de Snyk del autor; no hay cuenta de equipo.
- El umbral es alta o crítica: `--severity-threshold=high` deja fuera del reporte y de la
  compuerta las vulnerabilidades de severidad media y baja.
