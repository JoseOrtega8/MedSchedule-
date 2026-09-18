#!/usr/bin/env bash
# Ejecuta la bateria de pruebas contra el entorno de liberacion ya levantado.
# Sale con codigo distinto de cero si alguna prueba o umbral falla, de modo que
# el pipeline lo pueda usar como compuerta de liberacion.
set -euo pipefail

url_base="${URL_BASE:-http://127.0.0.1:8000}"
directorio_resultados="tests/carga/resultados"

if ! curl -sf "${url_base}" >/dev/null 2>&1; then
    echo "No hay entorno escuchando en ${url_base}." >&2
    echo "Generarlo primero con: bash scripts/entorno-liberacion.sh" >&2
    exit 1
fi

mkdir -p "${directorio_resultados}"

echo "==> Pruebas funcionales (PHPUnit)"
php artisan test

echo ""
echo "==> Prueba de carga (k6)"
if ! command -v k6 >/dev/null 2>&1; then
    echo "k6 no esta instalado. Instalarlo con: bash scripts/instalar-k6.sh" >&2
    exit 1
fi

# Las credenciales llegan por entorno. Los valores por defecto corresponden al
# usuario de prueba que siembra DatabaseSeeder; nunca se escriben en el repositorio.
K6_USUARIO="${K6_USUARIO:-patient@test.com}" \
K6_PASSWORD="${K6_PASSWORD:-password}" \
k6 run \
    --env URL_BASE="${url_base}" \
    --summary-export="${directorio_resultados}/jri-resumen.json" \
    --out "json=${directorio_resultados}/jri-metricas.json" \
    tests/carga/jri-prueba.js

echo ""
echo "Resultados en ${directorio_resultados}/"
