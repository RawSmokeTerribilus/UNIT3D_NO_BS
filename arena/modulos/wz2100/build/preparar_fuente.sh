#!/usr/bin/env bash
# Arena NOBS · Warzone 2100 — baja el tag de WZ y le aplica el parche de la Arena.
#   preparar_fuente.sh            → $WZ_TRABAJO/warzone2100 (por defecto ~/tmp-trabajo/wz2100)
# Después: build_web.sh y build_native.sh (ambos del MISMO árbol: la red exige la misma versión).
set -euo pipefail
WZ_TAG=${WZ_TAG:-4.7.0}
WZ_TRABAJO=${WZ_TRABAJO:-$HOME/tmp-trabajo/wz2100}
PARCHE=$(cd "$(dirname "$0")/../parche" && pwd)/0001-arena-web-join.patch

mkdir -p "$WZ_TRABAJO"
if [[ ! -d $WZ_TRABAJO/warzone2100/.git ]]; then
    git clone --depth 1 --branch "$WZ_TAG" --recurse-submodules --shallow-submodules \
        https://github.com/Warzone2100/warzone2100.git "$WZ_TRABAJO/warzone2100"
fi
cd "$WZ_TRABAJO/warzone2100"
if git apply --reverse --check -p1 "$PARCHE" 2>/dev/null; then
    echo "✅ El parche ya estaba aplicado"
else
    git apply -p1 "$PARCHE"
    echo "✅ Parche aplicado sobre $WZ_TAG"
fi
# Fuera la baliza de Cloudflare Web Analytics de wz2100.net del shell (reportaría a nuestros socios a un
# tercero). Con sed y no en el parche: así el token público de wz2100.net no vive en nuestro repo
# (GitGuardian lo marcaba como «apikey»).
if grep -q 'cloudflareinsights' platforms/emscripten/shell.html; then
    sed -i "s|^\s*<!-- Cloudflare Web Analytics -->.*$|  <!-- Arena NOBS: baliza de Cloudflare Web Analytics de wz2100.net quitada (reportaría a los socios a un tercero) -->|" platforms/emscripten/shell.html
    echo "✅ Baliza de Cloudflare Web Analytics quitada del shell"
fi
