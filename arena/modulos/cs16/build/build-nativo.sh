#!/bin/sh
# Dentro del contenedor: dedicado de Xash3D FWGS (64 bits) + servidor de cs16-client (ReGameDLL + yapb)
set -e
cd /w/src/xash3d-fwgs
python3 ./waf configure -T release -d -8 --prefix=/ > /w/src/waf-configure.log 2>&1
python3 ./waf build install --destdir=/w/src/out-xash > /w/src/waf-build.log 2>&1
echo "xash: OK"
cd /w/src/cs16-client
cmake -S . -B build-srv -DCMAKE_BUILD_TYPE=Release -DBUILD_CLIENT=OFF -DBUILD_MAINUI=OFF -DBUILD_SERVER=ON > /w/src/cs-cmake.log 2>&1
cmake --build build-srv -j6 > /w/src/cs-build.log 2>&1
cmake --install build-srv --prefix /w/src/out-cs > /w/src/cs-install.log 2>&1
echo "cs16: OK"
