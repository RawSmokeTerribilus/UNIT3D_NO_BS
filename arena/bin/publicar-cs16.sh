#!/usr/bin/env bash
# Arena NOBS — construye, prueba y (con --subir) publica las imágenes de Counter-Strike 1.6.
#
#   arena/bin/publicar-cs16.sh <versión> [--subir]       p. ej.  publicar-cs16.sh 16-arena1 --subir
#
# Entradas: $CS_TRABAJO (~/tmp-trabajo/cs16) preparado con arena/modulos/cs16/build/preparar.sh.
# Mismos cinturones que publicar-imagenes.sh / publicar-q3.sh: contexto mínimo, escaneo de secretos,
# smoke test, solo el tag de versión (nunca «latest»).
set -euo pipefail

VERSION=${1:?uso: publicar-cs16.sh <versión> [--subir]}
SUBIR=${2:-}
C=${CS_TRABAJO:-$HOME/tmp-trabajo/cs16}
REPO_ARENA=$(cd "$(dirname "$0")/.." && pwd)
MOD=$REPO_ARENA/modulos/cs16
CTX=$(mktemp -d "$HOME/tmp-trabajo/arena-ctx-cs16.XXXXXX")
RED=arena-prueba-cs16-$$
IMG_WEB=rawsmoke/nobs-arena-cs16-web:$VERSION
IMG_SALAS=rawsmoke/nobs-arena-cs16-salas:$VERSION

limpiar() {
    docker rm -f "$RED-salas" "$RED-web" >/dev/null 2>&1 || true
    docker network rm "$RED" >/dev/null 2>&1 || true
    rm -rf "$CTX"
}
trap limpiar EXIT
fallo() { echo "❌ $*" >&2; exit 1; }

[[ -x $C/srv/xash && -f $C/srv/cstrike/dlls/cs_amd64.so ]] || fallo "falta el servidor (build/preparar.sh)"
[[ -f $C/arena-cs-datos.tar.gz ]] || fallo "faltan los datos del cliente (build/preparar.sh)"
[[ -f $C/web/xash/xash.wasm && -f $C/web/cs/client_emscripten_wasm32.wasm ]] || fallo "falta el motor web (build/preparar.sh)"

echo "📦 Armando contextos en $CTX"
mkdir -p "$CTX/web/cliente" "$CTX/salas"
cp "$MOD/imagen-web/Dockerfile" "$MOD/imagen-web/web.conf" "$CTX/web/"
cp -r "$MOD/imagen-web/lobby" "$CTX/web/lobby"
cp "$MOD/imagen-web/cliente/index.html" "$MOD/imagen-web/cliente/arena-cs.css" "$CTX/web/cliente/"
cp -r "$C/web/xash" "$C/web/cs" "$CTX/web/cliente/"
ln "$C/arena-cs-datos.tar.gz" "$CTX/web/cliente/" 2>/dev/null || cp "$C/arena-cs-datos.tar.gz" "$CTX/web/cliente/"
# Shell empaquetado con esbuild (el paquete del motor usa imports sin extensión)
B=$CTX/bundle
mkdir -p "$B/vendor" && cp "$MOD/imagen-web/cliente/arena-cs-main.js" "$B/" && cp -r "$C/web/pkg/package" "$B/vendor/xash3d-fwgs"
# Resolución de render acotada (720 de alto por defecto): el motor pintaba a la pantalla entera
python3 "$REPO_ARENA/comun/ajustar_resolucion.py" "$B/vendor/xash3d-fwgs/dist/generated/xash.js" || fallo "ajustar_resolucion.py"
docker run --rm -u "$(id -u):$(id -g)" -e HOME=/tmp -v "$B":/w:z -w /w node:22-alpine sh -c \
    'mkdir -p /tmp/eb && cd /tmp/eb && npm i -s esbuild@0.24.2 >/dev/null 2>&1 && cd /w && /tmp/eb/node_modules/.bin/esbuild arena-cs-main.js --bundle --format=esm --target=es2022 --alias:xash3d-fwgs=./vendor/xash3d-fwgs/dist/index.js --outfile=arena-cs.js --log-level=error' \
    || fallo "esbuild"
cp "$B/arena-cs.js" "$CTX/web/cliente/" && rm -rf "$B"
python3 - "$CTX/web/cliente" "$VERSION" <<'PY'
import json, os, sys
d, version = sys.argv[1], sys.argv[2]
json.dump({'version': version, 'datos': {'src': 'arena-cs-datos.tar.gz', 'size': os.path.getsize(os.path.join(d, 'arena-cs-datos.tar.gz'))}},
          open(os.path.join(d, 'arena-cs.json'), 'w'))
PY
for f in "$CTX/web/cliente/"*.{js,html,css,json} "$CTX/web/cliente/"{xash,cs}/*.wasm "$CTX/web/lobby/"*; do gzip -9 -k "$f"; done
cp "$MOD/imagen-salas/Dockerfile" "$MOD/imagen-salas/agente.py" "$REPO_ARENA/comun/relay.py" "$CTX/salas/"
cp -r "$MOD/imagen-salas/presets" "$CTX/salas/presets"
cp -al "$C/srv" "$CTX/salas/cs16" 2>/dev/null || cp -r "$C/srv" "$CTX/salas/cs16"

echo "🔍 Escaneando el contexto en busca de secretos"
PATRON='(APP_KEY|DB_PASSWORD|MYSQL_|REDIS_PASSWORD|ARENA_CONTROL_TOKEN=|ARENA_PASE_SECRETO=|TRACKER_KEY|api[_-]?key|[a-z_]*secret[a-z_]*["'\'']?[[:space:]]*[=:]|passw(or)?d|BEGIN [A-Z ]*PRIVATE KEY)'
if find "$CTX" -name '.env*' -o -name '*.pem' -o -name '*.key' -o -name 'id_rsa*' -o -name 'id_ed25519*' | grep -q .; then
    fallo "ficheros sospechosos en el contexto"
fi
# Solo lo nuestro: los datos del juego (cfg de CS) y el motor traen «password» de sobra (sv_password…)
sospechas=$(grep -rInE "$PATRON" "$CTX/web/web.conf" "$CTX/web/Dockerfile" "$CTX/web/lobby" "$CTX/web/cliente/index.html" \
    "$CTX/salas/agente.py" "$CTX/salas/Dockerfile" "$CTX/salas/presets" | grep -v "sv_password" || true)
[[ -z $sospechas ]] || { echo "$sospechas" >&2; fallo "posibles secretos en el contexto (arriba)"; }

echo "🔨 Construyendo $IMG_WEB y $IMG_SALAS"
docker build -q -t "$IMG_WEB" "$CTX/web" >/dev/null
docker build -q -t "$IMG_SALAS" "$CTX/salas" >/dev/null

echo "🔍 Escaneando las imágenes"
for img in "$IMG_WEB" "$IMG_SALAS"; do
    if docker history --no-trunc --format '{{.CreatedBy}}' "$img" | grep -iE "$PATRON" | grep -q .; then
        fallo "$img: el historial menciona algo con pinta de secreto"
    fi
    if docker inspect --format '{{range .Config.Env}}{{println .}}{{end}}' "$img" | grep -iE 'TOKEN|KEY|PASS|SECRET' | grep -q .; then
        fallo "$img: variables de entorno sospechosas horneadas"
    fi
done

echo "🧪 Smoke test"
docker network create "$RED" >/dev/null
docker run -d --name "$RED-salas" --network "$RED" --network-alias arena-cs16-salas \
    -e ARENA_CONTROL_TOKEN=prueba-local -e ARENA_SALAS_INICIALES=dust2 "$IMG_SALAS" >/dev/null
docker run -d --name "$RED-web" --network "$RED" "$IMG_WEB" >/dev/null
CURL=(docker run --rm --network "$RED" curlimages/curl:8.11.1 -s --max-time 5)
sleep 4
for c in "$RED-salas" "$RED-web"; do
    [[ $(docker inspect -f '{{.State.Running}}' "$c" 2>/dev/null) == true ]] || { docker logs "$c" 2>&1 | tail -20 >&2; fallo "el contenedor $c no sigue en marcha"; }
done
for _ in $(seq 1 30); do "${CURL[@]}" -o /dev/null http://arena-cs16-salas:8081/salud && break; sleep 1; done
"${CURL[@]}" http://arena-cs16-salas:8081/publico | grep -q '"sala1"' || fallo "el agente no publica sala1"
"${CURL[@]}" -o /dev/null -w '%{http_code}' http://arena-cs16-salas:8081/estado | grep -q 401 || fallo "la API privada no pide token"
for _ in $(seq 1 25); do
    docker exec "$RED-salas" sh -c 'grep -q "player server started" /datos/salas/sala1/agente.log 2>/dev/null' && break
    sleep 1
done
docker exec "$RED-salas" sh -c 'grep -q "player server started" /datos/salas/sala1/agente.log' || { docker exec "$RED-salas" tail -20 /datos/salas/sala1/agente.log >&2; fallo "sala1 no arrancó"; }
docker exec "$RED-salas" sh -c 'grep -q "YaPB" /datos/salas/sala1/agente.log' || fallo "yapb no cargó"
cabeceras=$("${CURL[@]}" -I "http://$RED-web/cliente/xash/xash.wasm")
grep -qi 'cross-origin-embedder-policy: require-corp' <<<"$cabeceras" || fallo "el web no manda COEP"
grep -qi 'application/wasm' <<<"$cabeceras" || fallo "el web no sirve el wasm"
"${CURL[@]}" -o /dev/null -w '%{http_code}' -I "http://$RED-web/cliente/arena-cs-datos.tar.gz" | grep -q 200 || fallo "el web no sirve los datos"
"${CURL[@]}" "http://$RED-web/salas.json" | grep -q '"sala1"' || fallo "el web no llega a la lista de salas"
echo "✅ Smoke test superado"
docker images --format '{{.Repository}}:{{.Tag}} {{.Size}}' | grep -F ":$VERSION"

if [[ $SUBIR == --subir ]]; then
    echo "🚀 Subiendo a Docker Hub (solo el tag $VERSION)"
    docker push -q "$IMG_WEB"
    docker push -q "$IMG_SALAS"
    echo "✅ Publicadas: $IMG_WEB  $IMG_SALAS"
else
    echo "ℹ️  Sin --subir: imágenes solo en local."
fi
