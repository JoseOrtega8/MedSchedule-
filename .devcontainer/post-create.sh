#!/usr/bin/env bash
# Preparacion idempotente del Codespace: dependencias, entorno y base de datos.
# Se ejecuta una sola vez, al crear el contenedor. Volver a correrlo no rompe nada.
set -euo pipefail

echo "==> Instalando dependencias de PHP"
composer install --no-interaction --prefer-dist

echo "==> Instalando dependencias de Node"
npm ci

if [ ! -f .env ]; then
    echo "==> Creando .env a partir del ejemplo"
    cp .env.example .env
    php artisan key:generate
fi

echo "==> Apuntando la aplicacion a MySQL del compose"
# El .env de ejemplo trae SQLite y las lineas de MySQL comentadas. Se reescriben
# en su sitio en vez de anexar duplicados al final del archivo.
php -r '
$ruta = ".env";
$contenido = file_get_contents($ruta);
$ajustes = [
    "DB_CONNECTION" => "mysql",
    "DB_HOST"       => "mysql",
    "DB_PORT"       => "3306",
    "DB_DATABASE"   => "medschedule",
    "DB_USERNAME"   => "medschedule",
    "DB_PASSWORD"   => "medschedule",
    "APP_URL"       => "http://localhost:8000",
];
foreach ($ajustes as $clave => $valor) {
    $patron = "/^#?\s*" . preg_quote($clave, "/") . "=.*$/m";
    if (preg_match($patron, $contenido)) {
        $contenido = preg_replace($patron, $clave . "=" . $valor, $contenido, 1);
    } else {
        $contenido .= PHP_EOL . $clave . "=" . $valor;
    }
}
file_put_contents($ruta, $contenido);
'

echo "==> Instalando k6"
bash scripts/instalar-k6.sh

echo "==> Generando el entorno de liberacion (sin arrancar el servidor)"
bash scripts/entorno-liberacion.sh --sin-servidor

echo ""
echo "Entorno listo."
echo "Arrancar la aplicacion: php artisan serve --host=0.0.0.0 --port=8000"
echo "Correr la prueba de carga: bash scripts/pruebas-liberacion.sh"
