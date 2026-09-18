#!/usr/bin/env bash
# Revisa el formato con Prettier solo de los archivos JavaScript y CSS que
# cambiaron respecto a una referencia. Sirve tanto al hook de pre-commit como al
# pipeline, para que ambos apliquen exactamente el mismo criterio.
#
# Uso:
#   bash scripts/verificar-formato.sh <referencia>   # compara contra esa referencia
#   bash scripts/verificar-formato.sh --staged       # revisa lo que esta en el indice
set -euo pipefail

referencia="${1:-HEAD}"

if [ "${referencia}" = "--staged" ]; then
    archivos="$(git diff --cached --name-only --diff-filter=ACM || true)"
else
    archivos="$(git diff --name-only --diff-filter=ACM "${referencia}"...HEAD || true)"
fi

archivos_formateables="$(echo "${archivos}" | grep -E '\.(js|css)$' || true)"

if [ -z "${archivos_formateables}" ]; then
    echo "Sin archivos JavaScript o CSS que revisar."
    exit 0
fi

# Un archivo listado puede haberse borrado en el mismo cambio.
archivos_existentes=""
while IFS= read -r archivo; do
    [ -n "${archivo}" ] && [ -f "${archivo}" ] && archivos_existentes="${archivos_existentes} ${archivo}"
done <<< "${archivos_formateables}"

if [ -z "${archivos_existentes}" ]; then
    echo "Sin archivos JavaScript o CSS que revisar."
    exit 0
fi

echo "==> Revisando el formato de:${archivos_existentes}"
# shellcheck disable=SC2086
npx prettier --check ${archivos_existentes}
