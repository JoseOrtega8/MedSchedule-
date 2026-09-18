# Imagen base del entorno de liberacion.
#
# Se construye en lugar de usarse tal cual por un defecto de la imagen oficial:
# trae configurado el repositorio apt de Yarn con una clave GPG caducada, de modo
# que "apt-get update" termina con error y cualquier feature del devcontainer que
# instale paquetes falla con codigo 100. El sintoma en Codespaces es que la
# creacion del contenedor falla y se cae a un contenedor de recuperacion sin PHP.
FROM mcr.microsoft.com/devcontainers/php:1-8.2-bookworm

# Se retira el repositorio problematico y se comprueba que los indices se
# actualizan. Si esta linea fallara, conviene enterarse al construir y no al
# instalar una feature.
RUN rm -f /etc/apt/sources.list.d/*yarn* \
    && apt-get update \
    && rm -rf /var/lib/apt/lists/*

# La imagen trae Xdebug activo en modo "debug", que intercepta cada peticion e
# intenta conectarse a un cliente de depuracion inexistente. Ademas de llenar la
# salida de avisos, multiplica el tiempo de ejecucion de PHP: medir rendimiento
# con Xdebug activo produce numeros que no describen a la aplicacion.
#
# Se apaga con un archivo que se carga despues del de la imagen. Quien necesite
# depurar puede reactivarlo cambiando este valor a "debug".
RUN echo "xdebug.mode=off" > /usr/local/etc/php/conf.d/zz-xdebug-apagado.ini

# La imagen oficial no compila el controlador pdo_mysql. Sin el, cualquier orden
# de artisan contra la base del compose termina en
# "PDOException: could not find driver", aunque MySQL este levantado y accesible.
RUN docker-php-ext-install pdo_mysql
