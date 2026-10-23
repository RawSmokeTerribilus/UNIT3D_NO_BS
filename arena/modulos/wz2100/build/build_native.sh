#!/usr/bin/env bash
# Arena NOBS · Warzone 2100 — build del WZ nativo (host headless) en contenedor, del MISMO árbol que la web.
# Antes: preparar_fuente.sh. Resultado: $WZ_TRABAJO/installed-native (lo empaqueta arena/bin/publicar-imagenes.sh).
set -euo pipefail
W=${WZ_TRABAJO:-$HOME/tmp-trabajo/wz2100}
AQUI=$(cd "$(dirname "$0")" && pwd)
IMAGEN=arena/wz2100-nativebuild:4.7.0

# El contexto de la imagen de build es el árbol de WZ (trae get-dependencies_linux.sh y .ci/sdl3)
docker image inspect "$IMAGEN" >/dev/null 2>&1 || docker build -f "$AQUI/Dockerfile.native" -t "$IMAGEN" "$W/warzone2100"
mkdir -p "$W/build-native" "$W/installed-native"
docker run --rm --user "$(id -u):$(id -g)" -e HOME=/tmp \
  -v "$W":/work:z -w /work/build-native "$IMAGEN" bash -c "
    set -e
    [ -f CMakeCache.txt ] || cmake -G Ninja -DCMAKE_BUILD_TYPE=Release -DWZ_DISTRIBUTOR:STRING=NOBS-Arena \
      -DWZ_ENABLE_BACKEND_VULKAN=OFF -DWZ_ENABLE_WARNINGS_AS_ERRORS=OFF -DCMAKE_INSTALL_PREFIX:PATH=/work/installed-native /work/warzone2100
    cmake --build . -j4 --target install
  "
