#!/bin/bash
# Cliente web de STK optimizado (rama wasm de ading2210): Release de verdad, sin ASSERTIONS, más hilos precreados.
set -e
apt-get update -qq >/dev/null && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq autoconf automake libtool pkg-config gettext python3 xz-utils ragel >/dev/null 2>&1
cd /w/stk-code
ln -sfn /emsdk wasm/emsdk
sed -i 's/ -sASSERTIONS=1 / -sASSERTIONS=0 -sPTHREAD_POOL_SIZE=8 /' CMakeLists.txt
grep -c "PTHREAD_POOL_SIZE=8" CMakeLists.txt
[ -f wasm/prefix/lib/libharfbuzz.a ] || bash wasm/build_deps.sh > /w/build-deps.log 2>&1
echo "deps: $(ls wasm/prefix/lib | wc -l) librerías"
# Su CMakeLists avisa: «cmake fails the first few times you run it then works fine» (caché): reintentos
for i in 1 2 3; do
  bash wasm/build.sh Release > /w/build-web.log 2>&1 && break
  echo "build.sh intento $i falló"
done
ls -la wasm/web/game/
