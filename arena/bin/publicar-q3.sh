#!/usr/bin/env bash
# Arena NOBS — construye, prueba y (con --subir) publica las imágenes de Quake 3 (OpenArena).
#
#   arena/bin/publicar-q3.sh <versión> [--subir]       p. ej.  publicar-q3.sh oa088-arena1 --subir
#
# Entradas (arena/modulos/q3/build/{preparar_fuente,build}.sh):
#   $Q3_TRABAJO/ioq3/build-web/Release   ioquake3.js/.wasm parcheados
#   $Q3_TRABAJO/ioq3/build-ded/Release   ioq3ded nativo del MISMO commit
#   $Q3_TRABAJO/oa/openarena-0.8.8/baseoa   pk3 de OpenArena
# Mismos cinturones que publicar-imagenes.sh (WZ): contexto mínimo, escaneo de secretos, smoke test,
# solo el tag de versión (nunca «latest»).
set -euo pipefail

VERSION=${1:?uso: publicar-q3.sh <versión> [--subir]}
SUBIR=${2:-}
Q=${Q3_TRABAJO:-$HOME/tmp-trabajo/q3}
REPO_ARENA=$(cd "$(dirname "$0")/.." && pwd)
MOD=$REPO_ARENA/modulos/q3
CTX=$(mktemp -d "$HOME/tmp-trabajo/arena-ctx-q3.XXXXXX")
RED=arena-prueba-q3-$$
IMG_WEB=rawsmoke/nobs-arena-q3-web:$VERSION
IMG_SALAS=rawsmoke/nobs-arena-q3-salas:$VERSION
# pk3 que se sirven y que carga el servidor (sin pak2-players-mature: modelos «adultos», opcionales)
PK3=(pak0 pak1-maps pak2-players pak4-textures pak5-TA pak6-misc pak6-patch085 pak6-patch088)

limpiar() {
    docker rm -f "$RED-salas" "$RED-web" >/dev/null 2>&1 || true
    docker network rm "$RED" >/dev/null 2>&1 || true
    rm -rf "$CTX"
}
trap limpiar EXIT
fallo() { echo "❌ $*" >&2; exit 1; }

[[ -f $Q/ioq3/build-web/Release/ioquake3.wasm ]] || fallo "falta el cliente web (build.sh)"
[[ -x $Q/ioq3/build-ded/Release/ioq3ded ]] || fallo "falta ioq3ded (build.sh)"
[[ -f $Q/oa/openarena-0.8.8/baseoa/pak0.pk3 ]] || fallo "falta OpenArena (preparar_fuente.sh)"

echo "📦 Armando contextos en $CTX"
mkdir -p "$CTX/web/cliente/baseoa" "$CTX/salas/q3/baseoa"
cp "$MOD/imagen-web/Dockerfile" "$MOD/imagen-web/web.conf" "$CTX/web/"
cp -r "$MOD/imagen-web/lobby" "$CTX/web/lobby"
cp "$MOD/imagen-web/cliente/"* "$CTX/web/cliente/"
cp "$Q/ioq3/build-web/Release/ioquake3.js" "$Q/ioq3/build-web/Release/ioquake3.wasm" "$CTX/web/cliente/"
for p in "${PK3[@]}"; do
    # Enlace duro si se puede (mismo sistema de ficheros): 366 MB por imagen, no copiarlos dos veces
    ln "$Q/oa/openarena-0.8.8/baseoa/$p.pk3" "$CTX/web/cliente/baseoa/" 2>/dev/null || cp "$Q/oa/openarena-0.8.8/baseoa/$p.pk3" "$CTX/web/cliente/baseoa/"
    ln "$Q/oa/openarena-0.8.8/baseoa/$p.pk3" "$CTX/salas/q3/baseoa/" 2>/dev/null || cp "$Q/oa/openarena-0.8.8/baseoa/$p.pk3" "$CTX/salas/q3/baseoa/"
done
python3 - "$CTX/web/cliente" "$VERSION" <<'PY'
import json, os, sys
d, version = sys.argv[1], sys.argv[2]
fs = sorted(os.listdir(os.path.join(d, 'baseoa')))
json.dump({'version': version, 'baseoa': {'files': [
    {'src': 'baseoa/' + f, 'dst': '/baseoa', 'size': os.path.getsize(os.path.join(d, 'baseoa', f))} for f in fs]}},
    open(os.path.join(d, 'ioquake3-config.json'), 'w'), indent=1)
PY
for f in "$CTX/web/cliente/"*.{js,wasm,html,css,json} "$CTX/web/lobby/"*; do gzip -9 -k "$f"; done
cp "$MOD/imagen-salas/Dockerfile" "$MOD/imagen-salas/agente.py" "$REPO_ARENA/comun/relay.py" "$CTX/salas/"
cp -r "$MOD/imagen-salas/presets" "$CTX/salas/presets"
cp "$Q/ioq3/build-ded/Release/ioq3ded" "$CTX/salas/q3/"

echo "🔍 Escaneando el contexto en busca de secretos"
PATRON='(APP_KEY|DB_PASSWORD|MYSQL_|REDIS_PASSWORD|ARENA_CONTROL_TOKEN=|ARENA_PASE_SECRETO=|TRACKER_KEY|api[_-]?key|[a-z_]*secret[a-z_]*["'\'']?[[:space:]]*[=:]|passw(or)?d|BEGIN [A-Z ]*PRIVATE KEY)'
if find "$CTX" -name '.env*' -o -name '*.pem' -o -name '*.key' -o -name 'id_rsa*' -o -name 'id_ed25519*' | grep -q .; then
    fallo "ficheros sospechosos en el contexto"
fi
# g_password es el cvar de Q3 para la contraseña de sala (lo pone el agente), no un secreto
sospechas=$(grep -rInE "$PATRON" "$CTX" --include='*.json' --include='*.conf' --include='*.html' --include='*.css' --include='*.py' --include='Dockerfile' \
    | grep -vE '/cliente/ioquake3|/presets/' | grep -v "'g_password'" || true)
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
docker run -d --name "$RED-salas" --network "$RED" --network-alias arena-q3-salas \
    -e ARENA_CONTROL_TOKEN=prueba-local -e ARENA_SALAS_INICIALES=todos-contra-todos "$IMG_SALAS" >/dev/null
docker run -d --name "$RED-web" --network "$RED" "$IMG_WEB" >/dev/null
CURL=(docker run --rm --network "$RED" curlimages/curl:8.11.1 -s --max-time 5)
sleep 4
for c in "$RED-salas" "$RED-web"; do
    [[ $(docker inspect -f '{{.State.Running}}' "$c" 2>/dev/null) == true ]] || { docker logs "$c" 2>&1 | tail -20 >&2; fallo "el contenedor $c no sigue en marcha"; }
done
for _ in $(seq 1 30); do "${CURL[@]}" -o /dev/null http://arena-q3-salas:8081/salud && break; sleep 1; done
"${CURL[@]}" http://arena-q3-salas:8081/publico | grep -q '"sala1"' || fallo "el agente no publica sala1"
"${CURL[@]}" -o /dev/null -w '%{http_code}' http://arena-q3-salas:8081/estado | grep -q 401 || fallo "la API privada no pide token"
for _ in $(seq 1 20); do
    docker exec "$RED-salas" sh -c 'grep -q "AAS initialized" /datos/salas/sala1/agente.log 2>/dev/null' && break
    sleep 1
done
docker exec "$RED-salas" sh -c 'grep -q "AAS initialized" /datos/salas/sala1/agente.log' || { docker exec "$RED-salas" tail -20 /datos/salas/sala1/agente.log >&2; fallo "sala1 no cargó el mapa"; }
cabeceras=$("${CURL[@]}" -I "http://$RED-web/cliente/ioquake3.wasm")
grep -qi 'cross-origin-embedder-policy: require-corp' <<<"$cabeceras" || fallo "el web no manda COEP"
grep -qi 'application/wasm' <<<"$cabeceras" || fallo "el web no sirve el wasm"
"${CURL[@]}" -o /dev/null -w '%{http_code}' "http://$RED-web/cliente/baseoa/pak0.pk3" | grep -q 200 || fallo "el web no sirve los pk3"
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
