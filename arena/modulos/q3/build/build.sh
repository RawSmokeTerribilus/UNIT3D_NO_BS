#!/usr/bin/env bash
# Arena NOBS · Quake 3 — compila el cliente web (emsdk 4.0.10) y el servidor dedicado nativo del
# mismo commit. Usa la imagen de build de WZ (trae CMake 3.31; ioq3 pide ≥ 3.25 y la de emsdk trae 3.22).
# Antes: preparar_fuente.sh. Salida: $Q3_TRABAJO/ioq3/build-web/Release y build-ded/Release.
set -euo pipefail
Q=${Q3_TRABAJO:-$HOME/tmp-trabajo/q3}
IMAGEN=arena/wz2100-webbuild:4.7.0
docker image inspect "$IMAGEN" >/dev/null 2>&1 || { echo "falta $IMAGEN (arena/modulos/wz2100/build/build_web.sh la crea)" >&2; exit 1; }
docker run --rm --user "$(id -u):$(id -g)" -e HOME=/w/home -e EM_CACHE=/w/emcache -v "$Q":/w:z -w /w/ioq3 "$IMAGEN" bash -c '
  set -e
  [ -f build-web/CMakeCache.txt ] || emcmake cmake -S . -B build-web -DCMAKE_BUILD_TYPE=Release
  cmake --build build-web -j6
  [ -f build-ded/CMakeCache.txt ] || cmake -S . -B build-ded -DCMAKE_BUILD_TYPE=Release -DBUILD_CLIENT=OFF \
      -DBUILD_SERVER=ON -DBUILD_GAME_LIBRARIES=OFF -DBUILD_GAME_QVMS=OFF
  cmake --build build-ded -j6'
ls -la "$Q/ioq3/build-web/Release/ioquake3.wasm" "$Q/ioq3/build-ded/Release/ioq3ded"
