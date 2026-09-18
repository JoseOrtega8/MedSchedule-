#!/usr/bin/env bash
# Instala k6 en Linux (Codespaces, GitHub Actions) o macOS (Homebrew).
# Es idempotente: si k6 ya esta disponible, no hace nada.
set -euo pipefail

version_referencia="2.2.0"

if command -v k6 >/dev/null 2>&1; then
    echo "k6 ya instalado: $(k6 version)"
    exit 0
fi

sistema="$(uname -s)"

case "${sistema}" in
    Linux)
        echo "==> Instalando k6 desde el repositorio oficial de Grafana"
        # La clave se descarga por HTTPS en vez de pedirla a un servidor de claves.
        # El metodo con --keyserver falla dentro de los contenedores de devcontainer:
        # no traen dirmngr ni el directorio /root/.gnupg, y gpg aborta con
        # "keyserver receive failed: No dirmngr".
        if curl -fsSL https://dl.k6.io/key.gpg \
            | sudo gpg --dearmor -o /usr/share/keyrings/k6-archive-keyring.gpg; then
            echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" \
                | sudo tee /etc/apt/sources.list.d/k6.list >/dev/null
            sudo apt-get update
            sudo apt-get install -y k6
        else
            # Reserva: binario oficial publicado en GitHub. Evita depender de apt
            # y de gpg por completo.
            echo "==> La instalacion por apt fallo. Descargando el binario oficial"
            arquitectura="$(dpkg --print-architecture 2>/dev/null || echo amd64)"
            url_binario="https://github.com/grafana/k6/releases/download/v${version_referencia}/k6-v${version_referencia}-linux-${arquitectura}.tar.gz"
            directorio_temporal="$(mktemp -d)"
            curl -fsSL "${url_binario}" | tar -xz -C "${directorio_temporal}" --strip-components=1
            sudo install -m 0755 "${directorio_temporal}/k6" /usr/local/bin/k6
            rm -rf "${directorio_temporal}"
        fi
        ;;
    Darwin)
        echo "==> Instalando k6 con Homebrew"
        if ! command -v brew >/dev/null 2>&1; then
            echo "Homebrew no esta instalado. Instalarlo desde https://brew.sh" >&2
            exit 1
        fi
        brew install k6
        ;;
    *)
        echo "Sistema operativo no soportado por este script: ${sistema}" >&2
        echo "Instalacion manual: https://grafana.com/docs/k6/latest/set-up/install-k6/" >&2
        exit 1
        ;;
esac

echo "k6 instalado: $(k6 version)"
echo "Version de referencia del plan de la unidad: ${version_referencia}"
