#!/usr/bin/env bash
# Pruebas en bash puro (sin dependencias) para scripts/snyk-escanear.sh.
#
# Pone al frente del PATH un "npx" falso que nunca llama a Snyk de verdad:
# registra sus argumentos en un archivo y sale con el codigo que le indique
# NPX_FALSO_CODIGO. Cada caso corre en su propio directorio temporal, asi no
# se contaminan entre si. Termina con codigo distinto de cero si algun caso
# falla e imprime un resumen al final.
set -uo pipefail

raiz="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
script="${raiz}/scripts/snyk-escanear.sh"

total=0
fallos=0

# Token reconocible que nunca debe aparecer en la salida del script.
token_falso="SNYK_TOKEN_DE_PRUEBA_NUNCA_DEBE_IMPRIMIRSE_9c1f"

crear_npx_falso() {
    local dir="$1"
    cat > "${dir}/npx" <<'EOF'
#!/usr/bin/env bash
echo "$@" >> "${NPX_FALSO_LOG}"
exit "${NPX_FALSO_CODIGO:-0}"
EOF
    chmod +x "${dir}/npx"
}

afirmar_igual() {
    local descripcion="$1" esperado="$2" obtenido="$3"
    total=$((total + 1))
    if [ "${obtenido}" = "${esperado}" ]; then
        echo "OK   ${descripcion} (codigo ${obtenido})"
    else
        echo "FAIL ${descripcion} (esperado ${esperado}, obtenido ${obtenido})" >&2
        fallos=$((fallos + 1))
    fi
}

afirmar_contiene() {
    local descripcion="$1" aguja="$2" pajar="$3"
    total=$((total + 1))
    if printf '%s' "${pajar}" | grep -qF -- "${aguja}"; then
        echo "OK   ${descripcion}"
    else
        echo "FAIL ${descripcion} (no se encontro: ${aguja})" >&2
        fallos=$((fallos + 1))
    fi
}

afirmar_no_contiene() {
    local descripcion="$1" aguja="$2" pajar="$3"
    total=$((total + 1))
    if printf '%s' "${pajar}" | grep -qF -- "${aguja}"; then
        echo "FAIL ${descripcion} (se encontro lo que no debia: ${aguja})" >&2
        fallos=$((fallos + 1))
    else
        echo "OK   ${descripcion}"
    fi
}

# Corre el script en un entorno aislado.
# Argumentos: $1 directorio temporal, $2 codigo que debe devolver npx (o
# "sin" para dejar SNYK_TOKEN sin definir), $3 argumentos extra para el script
# (por ejemplo --monitor), $4 ruta de salida a usar (SNYK_SALIDA).
# Deja el resultado en las variables globales RES_SALIDA y RES_CODIGO.
correr_script() {
    local tmp="$1" codigo_npx="$2" args="$3" salida_json="$4"
    crear_npx_falso "${tmp}"

    if [ "${codigo_npx}" = "sin_token" ]; then
        RES_SALIDA="$(cd "${raiz}" && env -u SNYK_TOKEN \
            PATH="${tmp}:${PATH}" \
            NPX_FALSO_LOG="${tmp}/log" \
            NPX_FALSO_CODIGO=0 \
            SNYK_SALIDA="${salida_json}" \
            bash "${script}" ${args} 2>&1)"
    else
        RES_SALIDA="$(cd "${raiz}" && env \
            SNYK_TOKEN="${token_falso}" \
            PATH="${tmp}:${PATH}" \
            NPX_FALSO_LOG="${tmp}/log" \
            NPX_FALSO_CODIGO="${codigo_npx}" \
            SNYK_SALIDA="${salida_json}" \
            bash "${script}" ${args} 2>&1)"
    fi
    RES_CODIGO=$?
}

# --- Caso: sin SNYK_TOKEN ---
tmp="$(mktemp -d)"
correr_script "${tmp}" "sin_token" "" "${tmp}/salida.json"
afirmar_igual "sin SNYK_TOKEN sale con 1" 1 "${RES_CODIGO}"
afirmar_contiene "sin SNYK_TOKEN avisa del problema" "SNYK_TOKEN" "${RES_SALIDA}"
rm -rf "${tmp}"

# --- Casos: traduccion de codigos del CLI ---
tmp="$(mktemp -d)"
correr_script "${tmp}" 0 "" "${tmp}/salida.json"
afirmar_igual "codigo 0 de Snyk termina en 0" 0 "${RES_CODIGO}"
afirmar_contiene "codigo 0 imprime el mensaje esperado" "Sin vulnerabilidades altas o criticas." "${RES_SALIDA}"
rm -rf "${tmp}"

tmp="$(mktemp -d)"
correr_script "${tmp}" 1 "" "${tmp}/salida.json"
afirmar_igual "codigo 1 de Snyk termina en 1" 1 "${RES_CODIGO}"
afirmar_contiene "codigo 1 menciona la severidad" "vulnerabilidades de severidad alta o critica" "${RES_SALIDA}"
afirmar_contiene "codigo 1 senala el archivo de detalle" "${tmp}/salida.json" "${RES_SALIDA}"
rm -rf "${tmp}"

tmp="$(mktemp -d)"
correr_script "${tmp}" 2 "" "${tmp}/salida.json"
afirmar_igual "codigo 2 de Snyk termina en 2" 2 "${RES_CODIGO}"
afirmar_contiene "codigo 2 menciona el fallo de ejecucion" "El analisis de Snyk fallo" "${RES_SALIDA}"
rm -rf "${tmp}"

tmp="$(mktemp -d)"
correr_script "${tmp}" 3 "" "${tmp}/salida.json"
afirmar_igual "codigo 3 de Snyk termina en 2" 2 "${RES_CODIGO}"
afirmar_contiene "codigo 3 menciona proyectos no soportados" "no encontro proyectos soportados" "${RES_SALIDA}"
rm -rf "${tmp}"

tmp="$(mktemp -d)"
correr_script "${tmp}" 7 "" "${tmp}/salida.json"
afirmar_igual "codigo no documentado (7) termina en 2" 2 "${RES_CODIGO}"
afirmar_contiene "codigo no documentado se reporta en el mensaje" "7" "${RES_SALIDA}"
rm -rf "${tmp}"

# --- Argumentos exactos pasados a npx ---
tmp="$(mktemp -d)"
correr_script "${tmp}" 0 "" "${tmp}/salida.json"
registro="$(cat "${tmp}/log" 2>/dev/null || true)"
afirmar_contiene "invoca snyk test con los argumentos documentados" \
    "snyk@1.1307.4 test --all-projects --severity-threshold=high --json-file-output=${tmp}/salida.json" \
    "${registro}"
rm -rf "${tmp}"

# --- Crea la carpeta de salida si falta ---
tmp="$(mktemp -d)"
correr_script "${tmp}" 0 "" "${tmp}/anidado/mas/salida.json"
total=$((total + 1))
if [ -d "${tmp}/anidado/mas" ]; then
    echo "OK   crea la carpeta de la salida si no existe"
else
    echo "FAIL crea la carpeta de la salida si no existe" >&2
    fallos=$((fallos + 1))
fi
rm -rf "${tmp}"

# --- --monitor solo corre tras un test que termina en 0 o 1 ---
tmp="$(mktemp -d)"
correr_script "${tmp}" 0 "--monitor" "${tmp}/salida.json"
registro="$(cat "${tmp}/log" 2>/dev/null || true)"
afirmar_contiene "--monitor corre tras codigo 0" "monitor --all-projects" "${registro}"
rm -rf "${tmp}"

tmp="$(mktemp -d)"
correr_script "${tmp}" 1 "--monitor" "${tmp}/salida.json"
registro="$(cat "${tmp}/log" 2>/dev/null || true)"
afirmar_contiene "--monitor corre tras codigo 1" "monitor --all-projects" "${registro}"
afirmar_igual "--monitor no cambia el codigo de salida del test (1)" 1 "${RES_CODIGO}"
rm -rf "${tmp}"

tmp="$(mktemp -d)"
correr_script "${tmp}" 2 "--monitor" "${tmp}/salida.json"
registro="$(cat "${tmp}/log" 2>/dev/null || true)"
afirmar_no_contiene "--monitor NO corre tras codigo 2" "monitor --all-projects" "${registro}"
rm -rf "${tmp}"

tmp="$(mktemp -d)"
correr_script "${tmp}" 3 "--monitor" "${tmp}/salida.json"
registro="$(cat "${tmp}/log" 2>/dev/null || true)"
afirmar_no_contiene "--monitor NO corre tras codigo 3" "monitor --all-projects" "${registro}"
rm -rf "${tmp}"

# --- El token nunca aparece en stdout/stderr ---
tmp="$(mktemp -d)"
correr_script "${tmp}" 1 "--monitor" "${tmp}/salida.json"
afirmar_no_contiene "el token falso no aparece en la salida del script" "${token_falso}" "${RES_SALIDA}"
rm -rf "${tmp}"

echo ""
echo "Resumen: $((total - fallos))/${total} pruebas OK"

if [ "${fallos}" -gt 0 ]; then
    exit 1
fi
exit 0
