#!/usr/bin/env bash
# Genera el despliegue de la aplicacion en el entorno de destino: dependencias
# de produccion, migraciones, cacheo de configuracion y verificacion de salud.
set -euo pipefail

url_base="${URL_BASE:-http://127.0.0.1:8000}"

echo "==> Instalando dependencias de produccion"
composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
npm ci
npm run build

echo "==> Aplicando migraciones"
php artisan migrate --force

echo "==> Cacheando configuracion, rutas y vistas"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Verificando la salud del servicio"
intentos_maximos=15
for intento in $(seq 1 "${intentos_maximos}"); do
    if curl -sf "${url_base}" >/dev/null 2>&1; then
        echo "Despliegue verificado en ${url_base}"
        exit 0
    fi
    sleep 2
done

echo "El despliegue no respondio en ${url_base}. Revisar los registros." >&2
exit 1
