#!/usr/bin/env bash
# Levanta el stack local de SonarQube y espera a que quede operativo.
set -euo pipefail

archivo_compose="infra/sonarqube/docker-compose.yml"
url_sonarqube="${SONAR_HOST_URL:-http://localhost:9000}"
intentos_maximos=40

echo "==> Levantando SonarQube y PostgreSQL"
docker compose -f "${archivo_compose}" up -d

echo "==> Esperando a que SonarQube quede operativo (puede tardar un par de minutos)"
for intento in $(seq 1 "${intentos_maximos}"); do
    estado="$(curl -s "${url_sonarqube}/api/system/status" 2>/dev/null | grep -o '"status":"[A-Z]*"' | cut -d'"' -f4 || true)"

    if [ "${estado}" = "UP" ]; then
        echo ""
        echo "SonarQube operativo en ${url_sonarqube}"
        echo "Primer acceso: usuario admin, contrasena admin. La interfaz obliga a cambiarla."
        echo "Despues generar un token en Mi cuenta > Security y exportarlo como SONAR_TOKEN."
        exit 0
    fi

    printf '.'
    sleep 15
done

echo "" >&2
echo "SonarQube no quedo operativo tras ${intentos_maximos} intentos." >&2
echo "Revisar los registros: docker compose -f ${archivo_compose} logs sonarqube" >&2
exit 1
