#!/usr/bin/env bash
# Ejecuta el analisis estatico con sonar-scanner-cli en un contenedor, de modo
# que no haga falta instalar Java en la maquina anfitriona.
#
# Uso: SONAR_TOKEN='...' bash scripts/sonarqube-escanear.sh [rama]
set -euo pipefail

if [ -z "${SONAR_TOKEN:-}" ]; then
    echo "Falta SONAR_TOKEN." >&2
    echo "Generarlo en la interfaz de SonarQube y exportarlo en la sesion actual." >&2
    echo "No escribirlo en ningun archivo del repositorio." >&2
    exit 1
fi

rama="${1:-$(git rev-parse --abbrev-ref HEAD)}"
url_sonarqube="${SONAR_HOST_URL:-http://localhost:9000}"

# La edicion Community de SonarQube no analiza ramas: sonar.branch.name solo
# existe a partir de la edicion Developer. Para poder comparar el analisis de
# varias ramas se usa una clave de proyecto distinta por rama, derivada de su
# nombre. Cada rama aparece entonces como su propio proyecto en la interfaz.
clave_rama="$(echo "${rama}" | tr '/' '-' | tr -cd '[:alnum:]-_.')"
clave_proyecto="medschedule-${clave_rama}"

# El publicador SCM recorre el historico de cada archivo con jgit para atribuir
# autoria linea a linea. En este repositorio esa etapa se queda colgada varios
# minutos sin avanzar, y no aporta nada a las reglas de analisis: solo alimenta
# la atribucion de autor y el calculo de "codigo nuevo" por fecha. Se desactiva
# por defecto y se puede reactivar con SONAR_SCM=false.
echo "==> Analizando la rama ${rama} como proyecto ${clave_proyecto}"

# --network host permite al contenedor del escaner alcanzar el SonarQube que
# corre en la maquina anfitriona. En Docker Desktop se usa host.docker.internal.
argumentos_red=(--network host)
if [ "$(uname -s)" = "Darwin" ]; then
    argumentos_red=(--add-host host.docker.internal:host-gateway)
    url_sonarqube="${SONAR_HOST_URL:-http://host.docker.internal:9000}"
fi

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
