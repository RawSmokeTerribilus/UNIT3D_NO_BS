#!/usr/bin/env bash
# Arena NOBS · Warzone 2100 — build de la Web Edition PARCHEADA en contenedor (emsdk 4.0.10, como el CI de WZ).
# Antes: preparar_fuente.sh. Resultado: $WZ_TRABAJO/installed-web (lo empaqueta arena/bin/publicar-imagenes.sh).
#   build_web.sh [nombre]   (por defecto "web": build-web/ e installed-web/; incremental si ya existe)
set -euo pipefail
W=${WZ_TRABAJO:-$HOME/tmp-trabajo/wz2100}
NOMBRE=${1:-web}
AQUI=$(cd "$(dirname "$0")" && pwd)
IMAGEN=arena/wz2100-webbuild:4.7.0

docker image inspect "$IMAGEN" >/dev/null 2>&1 || docker build -f "$AQUI/Dockerfile.web" -t "$IMAGEN" "$AQUI"
mkdir -p "$W/build-$NOMBRE" "$W/installed-$NOMBRE" "$W/emcache" "$W/home"
# :z y no :Z — si otro contenedor usa el mismo árbol a la vez (build nativo), :Z le quitaría la etiqueta SELinux
docker run --rm --user "$(id -u):$(id -g)" \
  -e HOME=/work/home -e EM_CACHE=/work/emcache -e CMAKE_BUILD_PARALLEL_LEVEL=6 -e VCPKG_MAX_CONCURRENCY=6 \
  -v "$W":/work:z -w "/work/build-$NOMBRE" "$IMAGEN" bash -c "
    set -e
    [ -d vcpkg ] || git clone -q https://github.com/microsoft/vcpkg.git vcpkg
    [ -f CMakeCache.txt ] || cmake -DCMAKE_BUILD_TYPE=Release -DWZ_DISTRIBUTOR:STRING=NOBS-Arena \
      -DCMAKE_TOOLCHAIN_FILE=vcpkg/scripts/buildsystems/vcpkg.cmake -DVCPKG_TARGET_TRIPLET=wasm32-emscripten \
      -DCMAKE_INSTALL_PREFIX:PATH=/work/installed-$NOMBRE /work/warzone2100
    cmake --build . --config Release --target install
  "
