#!/usr/bin/env bash
# Analiza composer.lock y package-lock.json con Snyk como compuerta del
# pipeline de liberacion. El CLI queda fijado por version (snyk@1.1307.4) y se
# ejecuta con "npx --yes", sin instalarlo de forma global ni en package.json.
#
# Uso: SNYK_TOKEN='...' bash scripts/snyk-escanear.sh [--monitor]
#
# El token de Snyk se lee unicamente de la variable de entorno SNYK_TOKEN
# (la usa Snyk internamente); este script nunca la imprime ni la escribe en
# ningun archivo.
set -euo pipefail

if [ -z "${SNYK_TOKEN:-}" ]; then
    echo "Falta SNYK_TOKEN." >&2
    echo "Exportarlo en la sesion actual; nunca escribirlo en ningun archivo." >&2
    exit 1
fi

monitorear=false
if [ "${1:-}" = "--monitor" ]; then
    monitorear=true
fi

salida="${SNYK_SALIDA:-docs/entrega-u3/evidencia/snyk-resultado.json}"
mkdir -p "$(dirname "${salida}")"

# Se captura el codigo de salida del CLI sin que "set -e" aborte el script.
codigo=0
npx --yes snyk@1.1307.4 test --all-projects --severity-threshold=high --json-file-output="${salida}" || codigo=$?

case "${codigo}" in
    0)
        echo "Sin vulnerabilidades altas o criticas."
        codigo_final=0
        ;;
    1)
        echo "Se encontraron vulnerabilidades de severidad alta o critica. Detalle en ${salida}."
        codigo_final=1
        ;;
    2)
        echo "El analisis de Snyk fallo (error de ejecucion o de autenticacion)." >&2
        codigo_final=2
        ;;
    3)
        echo "Snyk no encontro proyectos soportados." >&2
        codigo_final=2
        ;;
    *)
        echo "Snyk termino con un codigo de salida no documentado: ${codigo}." >&2
        codigo_final=2
        ;;
esac

# El monitor sube una instantanea al dashboard de Snyk; solo tiene sentido si
# el analisis pudo correr y no encontro un error de ejecucion o de proyectos
# no soportados. Su fallo se reporta pero nunca cambia el codigo del test.
if [ "${monitorear}" = true ] && { [ "${codigo}" -eq 0 ] || [ "${codigo}" -eq 1 ]; }; then
    if ! npx --yes snyk@1.1307.4 monitor --all-projects; then
        echo "El envio de la instantanea a Snyk (monitor) fallo; no afecta el resultado del analisis." >&2
    fi
fi

exit "${codigo_final}"
