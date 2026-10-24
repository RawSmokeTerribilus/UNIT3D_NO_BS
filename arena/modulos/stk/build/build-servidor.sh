#!/bin/sh
set -e
cd /w/stk-code
cmake -S . -B build-srv -DCMAKE_BUILD_TYPE=Release -DSERVER_ONLY=ON -DNO_SHADERC=ON -DUSE_WIIUSE=OFF -DUSE_SQLITE3=OFF -DCHECK_ASSETS=OFF > /w/cmake-srv.log 2>&1
cmake --build build-srv -j6 > /w/build-srv.log 2>&1
ls -la build-srv/bin/
