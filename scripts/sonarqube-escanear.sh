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

echo "==> Analizando la rama ${rama} como proyecto ${clave_proyecto}"

# --network host permite al contenedor del escaner alcanzar el SonarQube que
# corre en la maquina anfitriona. En Docker Desktop se usa host.docker.internal.
argumentos_red=(--network host)
if [ "$(uname -s)" = "Darwin" ]; then
    argumentos_red=(--add-host host.docker.internal:host-gateway)
    url_sonarqube="${SONAR_HOST_URL:-http://host.docker.internal:9000}"
fi

docker run --rm \
    "${argumentos_red[@]}" \
    -e SONAR_HOST_URL="${url_sonarqube}" \
    -e SONAR_TOKEN="${SONAR_TOKEN}" \
    -v "$(pwd):/usr/src" \
    sonarsource/sonar-scanner-cli \
    -Dsonar.projectKey="${clave_proyecto}" \
    -Dsonar.projectName="MedSchedule (${rama})"

echo ""
echo "Analisis terminado."
echo "Resultados en http://localhost:9000/dashboard?id=${clave_proyecto}"
