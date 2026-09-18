#!/usr/bin/env bash
# Genera el entorno de liberacion: base de datos migrada y sembrada, assets
# compilados y servidor HTTP escuchando. Es el script que el pipeline invoca
# antes de ejecutar cualquier prueba.
#
# Uso:
#   bash scripts/entorno-liberacion.sh                 # deja el servidor corriendo
#   bash scripts/entorno-liberacion.sh --sin-servidor  # solo prepara datos y assets
set -euo pipefail

arrancar_servidor=1
if [ "${1:-}" = "--sin-servidor" ]; then
    arrancar_servidor=0
fi

url_base="${URL_BASE:-http://127.0.0.1:8000}"
intentos_maximos=30

echo "==> Esperando a que la base de datos acepte conexiones"
for intento in $(seq 1 "${intentos_maximos}"); do
    if php -r '
        $host = getenv("DB_HOST") ?: "127.0.0.1";
        $puerto = (int) (getenv("DB_PORT") ?: 3306);
        $conexion = @fsockopen($host, $puerto, $codigo_error, $mensaje_error, 2);
        if ($conexion === false) {
            exit(1);
        }
        fclose($conexion);
        exit(0);
    '; then
        echo "Base de datos disponible."
        break
    fi

    if [ "${intento}" -eq "${intentos_maximos}" ]; then
        echo "La base de datos no respondio tras ${intentos_maximos} intentos." >&2
        exit 1
    fi
    sleep 2
done

echo "==> Aplicando migraciones"
php artisan migrate --force

echo "==> Sembrando datos de prueba"
php artisan db:seed --force

echo "==> Compilando assets de Vite"
npm run build

if [ "${arrancar_servidor}" -eq 0 ]; then
    echo "Entorno preparado. Servidor no arrancado (--sin-servidor)."
    exit 0
fi

echo "==> Arrancando el servidor de la aplicacion"
php artisan serve --host=0.0.0.0 --port=8000 &
pid_servidor=$!
echo "${pid_servidor}" > storage/servidor-liberacion.pid

for intento in $(seq 1 "${intentos_maximos}"); do
    if curl -sf "${url_base}" >/dev/null 2>&1; then
        echo "Servidor respondiendo en ${url_base} (pid ${pid_servidor})."
        exit 0
    fi
    sleep 2
done

echo "El servidor no respondio en ${url_base} tras ${intentos_maximos} intentos." >&2
kill "${pid_servidor}" 2>/dev/null || true
exit 1
