#!/usr/bin/env bash
# Arena NOBS — construye, prueba y (con --subir) publica las imágenes de SuperTuxKart.
#
#   arena/bin/publicar-stk.sh <versión> [--subir]       p. ej.  publicar-stk.sh git-arena1 --subir
#
# Entradas: $STK_TRABAJO (~/tmp-trabajo/stk) preparado con arena/modulos/stk/build/preparar.sh.
# Mismos cinturones que los otros publicar-*: contexto mínimo, escaneo de secretos, smoke test, nunca «latest».
set -euo pipefail

VERSION=${1:?uso: publicar-stk.sh <versión> [--subir]}
SUBIR=${2:-}
S=${STK_TRABAJO:-$HOME/tmp-trabajo/stk}
REPO_ARENA=$(cd "$(dirname "$0")/.." && pwd)
MOD=$REPO_ARENA/modulos/stk
CTX=$(mktemp -d "$HOME/tmp-trabajo/arena-ctx-stk.XXXXXX")
RED=arena-prueba-stk-$$
IMG_WEB=rawsmoke/nobs-arena-stk-web:$VERSION
IMG_SALAS=rawsmoke/nobs-arena-stk-salas:$VERSION

limpiar() {
    docker rm -f "$RED-salas" "$RED-web" >/dev/null 2>&1 || true
    docker network rm "$RED" >/dev/null 2>&1 || true
    rm -rf "$CTX"
}
trap limpiar EXIT
fallo() { echo "❌ $*" >&2; exit 1; }

[[ -x $S/srv/supertuxkart && -d $S/srv/data/tracks ]] || fallo "falta el servidor (build/preparar.sh)"
[[ -f $S/demo/game/supertuxkart.wasm && -f $S/demo/data_low.tar.gz ]] || fallo "falta el cliente (build/preparar.sh)"

echo "📦 Armando contextos en $CTX"
mkdir -p "$CTX/web/cliente/game" "$CTX/salas"
cp "$MOD/imagen-web/Dockerfile" "$MOD/imagen-web/web.conf" "$CTX/web/"
cp -r "$MOD/imagen-web/lobby" "$CTX/web/lobby"
cp "$MOD/imagen-web/cliente/"* "$CTX/web/cliente/"
# Cliente: nuestra build optimizada (build/build-web.sh) si existe; si no, la de la demo (de depuración)
CLI=$S/stk-code/wasm/web/game
[[ -f $CLI/supertuxkart.wasm ]] || CLI=$S/demo/game
echo "   cliente web: $CLI"
cp "$CLI/supertuxkart.js" "$CLI/supertuxkart.wasm" "$CTX/web/cliente/game/"
[[ -f $CLI/supertuxkart.worker.js ]] && cp "$CLI/supertuxkart.worker.js" "$CTX/web/cliente/game/"
python3 "$REPO_ARENA/comun/ajustar_resolucion.py" "$CTX/web/cliente/game/supertuxkart.js" || fallo "ajustar_resolucion.py"
ln "$S/demo/data_low.tar.gz" "$CTX/web/cliente/stk-datos.tar.gz" 2>/dev/null || cp "$S/demo/data_low.tar.gz" "$CTX/web/cliente/stk-datos.tar.gz"
python3 - "$CTX/web/cliente" "$VERSION" <<'PY'
import json, os, sys
d, version = sys.argv[1], sys.argv[2]
json.dump({'version': version, 'datos': {'src': 'stk-datos.tar.gz', 'size': os.path.getsize(os.path.join(d, 'stk-datos.tar.gz'))}},
          open(os.path.join(d, 'arena-stk.json'), 'w'))
PY
for f in "$CTX/web/cliente/"*.{js,html,css,json} "$CTX/web/cliente/game/"* "$CTX/web/lobby/"*; do gzip -9 -k "$f"; done
cp "$MOD/imagen-salas/Dockerfile" "$MOD/imagen-salas/agente.py" "$REPO_ARENA/comun/relay.py" "$CTX/salas/"
cp -r "$MOD/imagen-salas/presets" "$CTX/salas/presets"
cp -al "$S/srv" "$CTX/salas/stk" 2>/dev/null || cp -r "$S/srv" "$CTX/salas/stk"

echo "🔍 Escaneando el contexto en busca de secretos"
PATRON='(APP_KEY|DB_PASSWORD|MYSQL_|REDIS_PASSWORD|ARENA_CONTROL_TOKEN=|ARENA_PASE_SECRETO=|TRACKER_KEY|api[_-]?key|[a-z_]*secret[a-z_]*["'\'']?[[:space:]]*[=:]|passw(or)?d|BEGIN [A-Z ]*PRIVATE KEY)'
if find "$CTX" -name '.env*' -o -name '*.pem' -o -name '*.key' -o -name 'id_rsa*' -o -name 'id_ed25519*' | grep -v '/data/cacert.pem' | grep -q .; then
    fallo "ficheros sospechosos en el contexto"
fi
# Solo lo nuestro (los datos del juego y el motor traen «password» de sobra: server-password…)
sospechas=$(grep -rInE "$PATRON" "$CTX/web/web.conf" "$CTX/web/Dockerfile" "$CTX/web/lobby" "$CTX/web/cliente/index.html" \
    "$CTX/web/cliente/pre.js" "$CTX/web/cliente/arena-stk.js" "$CTX/salas/agente.py" "$CTX/salas/Dockerfile" "$CTX/salas/presets" \
    | grep -v "server-password" | grep -v "remember-password" || true)
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
docker run -d --name "$RED-salas" --network "$RED" --network-alias arena-stk-salas \
    -e ARENA_CONTROL_TOKEN=prueba-local -e ARENA_SALAS_INICIALES=carrera "$IMG_SALAS" >/dev/null
docker run -d --name "$RED-web" --network "$RED" "$IMG_WEB" >/dev/null
CURL=(docker run --rm --network "$RED" curlimages/curl:8.11.1 -s --max-time 5)
sleep 4
for c in "$RED-salas" "$RED-web"; do
    [[ $(docker inspect -f '{{.State.Running}}' "$c" 2>/dev/null) == true ]] || { docker logs "$c" 2>&1 | tail -20 >&2; fallo "el contenedor $c no sigue en marcha"; }
done
for _ in $(seq 1 30); do "${CURL[@]}" -o /dev/null http://arena-stk-salas:8081/salud && break; sleep 1; done
"${CURL[@]}" http://arena-stk-salas:8081/publico | grep -q '"sala1"' || fallo "el agente no publica sala1"
"${CURL[@]}" -o /dev/null -w '%{http_code}' http://arena-stk-salas:8081/estado | grep -q 401 || fallo "la API privada no pide token"
for _ in $(seq 1 25); do
    docker exec "$RED-salas" sh -c 'grep -q "Listening has been started" /datos/salas/sala1/agente.log 2>/dev/null' && break
    sleep 1
done
docker exec "$RED-salas" sh -c 'grep -q "Listening has been started" /datos/salas/sala1/agente.log' || { docker exec "$RED-salas" tail -20 /datos/salas/sala1/agente.log >&2; fallo "sala1 no arrancó"; }
cabeceras=$("${CURL[@]}" -I "http://$RED-web/cliente/game/supertuxkart.wasm")
grep -qi 'cross-origin-embedder-policy: require-corp' <<<"$cabeceras" || fallo "el web no manda COEP"
grep -qi 'application/wasm' <<<"$cabeceras" || fallo "el web no sirve el wasm"
"${CURL[@]}" -o /dev/null -w '%{http_code}' -I "http://$RED-web/cliente/stk-datos.tar.gz" | grep -q 200 || fallo "el web no sirve los datos"
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
