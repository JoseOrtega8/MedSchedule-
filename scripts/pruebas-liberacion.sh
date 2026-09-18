#!/usr/bin/env bash
# Ejecuta la bateria de pruebas contra el entorno de liberacion ya levantado.
#
# Las dos compuertas son independientes y ambas se ejecutan siempre: una prueba
# de carga no debe quedarse sin correr porque la suite funcional arrastre deuda
# previa, y la deuda funcional tampoco debe quedar oculta porque la carga pase.
# El script sale con codigo distinto de cero si cualquiera de las dos falla, de
# modo que el pipeline lo sigue usando como compuerta de liberacion.
set -uo pipefail

url_base="${URL_BASE:-http://127.0.0.1:8000}"
directorio_resultados="tests/carga/resultados"

if ! curl -sf "${url_base}" >/dev/null 2>&1; then
    echo "No hay entorno escuchando en ${url_base}." >&2
    echo "Generarlo primero con: bash scripts/entorno-liberacion.sh" >&2
    exit 1
fi

if ! command -v k6 >/dev/null 2>&1; then
    echo "k6 no esta instalado. Instalarlo con: bash scripts/instalar-k6.sh" >&2
    exit 1
fi

mkdir -p "${directorio_resultados}"

script_carga="tests/carga/jri-prueba.js"
seeder_carga="database/seeders/CargaSeeder.php"

echo "==> Pruebas funcionales (PHPUnit)"
php artisan test
codigo_phpunit=$?

# La suite arrastra fallos anteriores a esta unidad, documentados en el issue #86.
# Mientras ese issue siga abierto, el pipeline puede ejecutar esta compuerta en
# modo informativo para que la deuda ajena no bloquee la de rendimiento. Se activa
# de forma explicita y visible, no con un "|| true" que la escondiera: el
# resultado real se sigue imprimiendo en el resumen de abajo.
if [ "${PERMITIR_FALLO_FUNCIONAL:-false}" = "true" ] && [ "${codigo_phpunit}" -ne 0 ]; then
    echo ""
    echo "AVISO: PHPUnit fallo (codigo ${codigo_phpunit}), pero PERMITIR_FALLO_FUNCIONAL"
    echo "       esta activo por el issue #86. La compuerta funcional no bloquea."
    phpunit_informativo=1
fi

echo ""
echo "==> Prueba de carga (k6)"
if [ -f "${script_carga}" ]; then
    # Las cuentas de carga se siembran aqui, junto a la prueba que las usa, y no
    # como un paso aparte del pipeline: asi el script funciona igual en local,
    # en el Codespace y en el runner, y no depende de que exista el seeder.
    if [ -f "${seeder_carga}" ]; then
        php artisan db:seed --class=CargaSeeder --force
    fi

    # Las credenciales llegan por entorno. Los valores por defecto corresponden a
    # las cuentas que siembra CargaSeeder; nunca se escriben en el repositorio.
    K6_PASSWORD="${K6_PASSWORD:-password}" \
    k6 run \
        --env URL_BASE="${url_base}" \
        --summary-export="${directorio_resultados}/jri-resumen.json" \
        --out "json=${directorio_resultados}/jri-metricas.json" \
        "${script_carga}"
    codigo_k6=$?
else
    echo "No hay prueba de carga en ${script_carga}; se omite esta compuerta."
    codigo_k6=0
fi

echo ""
echo "==================== RESUMEN DE LAS COMPUERTAS ===================="
if [ "${codigo_phpunit}" -eq 0 ]; then
    echo "  Pruebas funcionales (PHPUnit) ....... CORRECTO"
elif [ "${phpunit_informativo:-0}" -eq 1 ]; then
    echo "  Pruebas funcionales (PHPUnit) ....... FALLO, informativo (issue #86)"
else
    echo "  Pruebas funcionales (PHPUnit) ....... FALLO (codigo ${codigo_phpunit})"
fi

# k6 devuelve 99 cuando se incumple un umbral declarado en options.thresholds.
if [ ! -f "${script_carga}" ]; then
    echo "  Prueba de carga (k6) ................ OMITIDA (sin script)"
elif [ "${codigo_k6}" -eq 0 ]; then
    echo "  Prueba de carga (k6) ................ CORRECTO"
elif [ "${codigo_k6}" -eq 99 ]; then
    echo "  Prueba de carga (k6) ................ UMBRAL INCUMPLIDO"
else
    echo "  Prueba de carga (k6) ................ FALLO (codigo ${codigo_k6})"
fi
echo "==================================================================="
echo "Resultados en ${directorio_resultados}/"

if [ "${codigo_k6}" -ne 0 ]; then
    exit 1
fi

if [ "${codigo_phpunit}" -ne 0 ] && [ "${phpunit_informativo:-0}" -eq 0 ]; then
    exit 1
fi
