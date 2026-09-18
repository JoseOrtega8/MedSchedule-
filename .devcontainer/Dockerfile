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
