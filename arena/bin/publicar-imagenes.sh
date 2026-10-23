#!/usr/bin/env bash
# Arena NOBS — construye, prueba y (con --subir) publica las imágenes de Warzone 2100 en Docker Hub.
#
#   arena/bin/publicar-imagenes.sh <versión> [--subir]
#   p. ej.  arena/bin/publicar-imagenes.sh 4.7.0-arena1 --subir
#
# Entradas (compiladas antes con arena/modulos/wz2100/build/build_{web,native}.sh):
#   $WZ_TRABAJO/installed-web     dist de la Web Edition parcheada
#   $WZ_TRABAJO/installed-native  WZ nativo del MISMO tag
#
# Cinturones:
#   - El contexto de cada imagen se arma en una carpeta temporal con SOLO lo necesario:
#     nada del repo, del .env ni de storage puede colarse.
#   - Antes de subir: escaneo de secretos (contexto, historial, entorno y sistema de ficheros
#     de la imagen) y smoke test (agente sano, sala escuchando, web sirviendo el wasm con
#     COOP/COEP y la lista de salas por el proxy). Si algo falla, no se sube nada.
#   - Se sube SOLO el tag de versión. Nunca «latest»: el compose fija la versión en el .env.
set -euo pipefail

VERSION=${1:?uso: publicar-imagenes.sh <versión> [--subir]}
SUBIR=${2:-}
WZ_TRABAJO=${WZ_TRABAJO:-$HOME/tmp-trabajo/wz2100}
REPO_ARENA=$(cd "$(dirname "$0")/.." && pwd)
MOD=$REPO_ARENA/modulos/wz2100
CTX=$(mktemp -d "$HOME/tmp-trabajo/arena-ctx.XXXXXX")
RED=arena-prueba-$$
IMG_WEB=rawsmoke/nobs-arena-wz2100-web:$VERSION
IMG_SALAS=rawsmoke/nobs-arena-wz2100-salas:$VERSION

limpiar() {
    docker rm -f "$RED-salas" "$RED-web" >/dev/null 2>&1 || true
    docker network rm "$RED" >/dev/null 2>&1 || true
    rm -rf "$CTX"
}
trap limpiar EXIT

fallo() { echo "❌ $*" >&2; exit 1; }

[[ -f $WZ_TRABAJO/installed-web/warzone2100.wasm ]] || fallo "falta $WZ_TRABAJO/installed-web (build_web.sh)"
[[ -x $WZ_TRABAJO/installed-native/bin/warzone2100 ]] || fallo "falta $WZ_TRABAJO/installed-native (build_native.sh)"

echo "📦 Armando contextos en $CTX"
mkdir -p "$CTX/web" "$CTX/salas/wz/bin" "$CTX/salas/wz/share/warzone2100"
cp "$MOD/imagen-web/Dockerfile" "$MOD/imagen-web/web.conf" "$CTX/web/"
cp -r "$MOD/imagen-web/lobby" "$CTX/web/lobby"
cp -r "$WZ_TRABAJO/installed-web" "$CTX/web/cliente"
rm -rf "$CTX/web/cliente/share"
cp "$MOD/imagen-salas/Dockerfile" "$MOD/imagen-salas/agente.py" "$MOD/imagen-salas/relay.py" "$CTX/salas/"
cp -r "$MOD/imagen-salas/presets" "$CTX/salas/presets"
cp "$WZ_TRABAJO/installed-native/bin/warzone2100" "$CTX/salas/wz/bin/"
for d in base.wz mp.wz fonts; do
    cp -r "$WZ_TRABAJO/installed-native/share/warzone2100/$d" "$CTX/salas/wz/share/warzone2100/"
done

echo "🔍 Escaneando el contexto en busca de secretos"
# «secret» solo como clave asignada (secret_key=, client_secret:…): el módulo `secrets` de Python no cuenta
PATRON='(APP_KEY|DB_PASSWORD|MYSQL_|REDIS_PASSWORD|ARENA_CONTROL_TOKEN=|TRACKER_KEY|api[_-]?key|[a-z_]*secret[a-z_]*["'\'']?[[:space:]]*[=:]|passw(or)?d|BEGIN [A-Z ]*PRIVATE KEY)'
if find "$CTX" -name '.env*' -o -name '*.pem' -o -name '*.key' -o -name 'id_rsa*' -o -name 'id_ed25519*' | grep -q .; then
    fallo "ficheros sospechosos en el contexto: $(find "$CTX" -name '.env*' -o -name '*.pem' -o -name '*.key' -o -name 'id_rsa*' -o -name 'id_ed25519*')"
fi
# Solo texto: los binarios (wasm/data/wz) llevan cadenas como «password» del propio juego
if grep -rIlE "$PATRON" "$CTX" --include='*.json' --include='*.conf' --include='*.html' --include='*.css' --include='*.py' --include='Dockerfile' \
    | grep -v '/cliente/' | grep -q .; then
    grep -rInE "$PATRON" "$CTX" --include='*.json' --include='*.conf' --include='*.py' --include='Dockerfile' | grep -v '/cliente/' >&2
    fallo "posibles secretos en el contexto (arriba)"
fi

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
    sospechosos=$(docker run --rm --entrypoint sh "$img" -c \
        "find / -xdev \( -name '.env' -o -name '.env.*' -o -name '*.pem' -o -name 'id_rsa*' -o -name 'id_ed25519*' -o -name 'tokens' \) \
         -not -path '/proc/*' -not -path '/usr/lib/*' -not -path '/usr/share/*' -not -path '/etc/ssl*' -not -path '/opt/arena-venv/lib/*' 2>/dev/null" || true)
    [[ -z $sospechosos ]] || fallo "$img contiene: $sospechosos"
done

echo "🧪 Smoke test"
docker network create "$RED" >/dev/null
docker run -d --name "$RED-salas" --network "$RED" --network-alias arena-wz2100-salas \
    -e ARENA_CONTROL_TOKEN=prueba-local -e ARENA_SALAS_INICIALES=contra-ia "$IMG_SALAS" >/dev/null
docker run -d --name "$RED-web" --network "$RED" "$IMG_WEB" >/dev/null
CURL=(docker run --rm --network "$RED" curlimages/curl:8.11.1 -s --max-time 5)
for _ in $(seq 1 30); do
    "${CURL[@]}" -o /dev/null http://arena-wz2100-salas:8081/salud && break
    sleep 1
done
"${CURL[@]}" http://arena-wz2100-salas:8081/publico | grep -q '"sala1"' || fallo "el agente no publica sala1"
"${CURL[@]}" -o /dev/null -w '%{http_code}' http://arena-wz2100-salas:8081/estado | grep -q 401 || fallo "la API privada no pide token"
for _ in $(seq 1 20); do
    docker exec "$RED-salas" sh -c 'grep -q "Opening listening socket" /datos/salas/sala1/agente.log 2>/dev/null' && break
    sleep 1
done
docker exec "$RED-salas" sh -c 'grep -q "Opening listening socket" /datos/salas/sala1/agente.log' || fallo "sala1 no llegó a escuchar"
cabeceras=$("${CURL[@]}" -I "http://$RED-web/cliente/warzone2100.wasm")
grep -qi 'cross-origin-embedder-policy: require-corp' <<<"$cabeceras" || fallo "el web no manda COEP"
grep -qi 'application/wasm' <<<"$cabeceras" || fallo "el web no sirve el wasm"
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
