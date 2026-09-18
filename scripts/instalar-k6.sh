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
        sudo gpg --no-default-keyring \
            --keyring /usr/share/keyrings/k6-archive-keyring.gpg \
            --keyserver hkp://keyserver.ubuntu.com:80 \
            --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69
        echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" \
            | sudo tee /etc/apt/sources.list.d/k6.list >/dev/null
        sudo apt-get update
        sudo apt-get install -y k6
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
