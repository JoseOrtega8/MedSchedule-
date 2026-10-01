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
