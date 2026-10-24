#!/usr/bin/env bash
# Arena NOBS · Counter-Strike 1.6 — deja en $CS_TRABAJO (~/tmp-trabajo/cs16) todo lo que usa publicar-cs16.sh:
#   srv/                    servidor: xash dedicado + filesystem_stdio.so + valve/ + cstrike/ (datos de HLDS sin
#                           binarios de Valve, dlls de cs16-client: cs_amd64.so + yapb_amd64.so, extras.pk3,
#                           liblist.gam con gamedll_linux = dlls/yapb.so para que yapb cargue a ReGameDLL)
#   arena-cs-datos.tar.gz   datos del cliente recortados (MAPAS)
#   web/                    motor web (xash3d-fwgs 1.2.2) y cliente de CS en wasm, de las copias guardadas
# Pasos idempotentes: lo que ya está, no se rehace.
set -euo pipefail
C=${CS_TRABAJO:-$HOME/tmp-trabajo/cs16}
AQUI=$(cd "$(dirname "$0")" && pwd)
COPIAS=${CS_COPIAS:-$HOME/backups/manual/arena-cs16}
mkdir -p "$C/src"

# 1. Fuentes en los commits de BASE + build nativo (contenedor ubuntu:24.04)
for repo in xash3d-fwgs cs16-client; do
    commit=$(awk -v r="$repo" '$1 == r {print $2}' "$AQUI/BASE")
    if [[ ! -d $C/src/$repo/.git ]]; then
        git clone -q --recursive https://github.com/FWGS/$repo.git "$C/src/$repo"
    fi
    git -C "$C/src/$repo" checkout -q "$commit" && git -C "$C/src/$repo" submodule update -q --init --recursive
done
if [[ ! -x $C/src/out-xash/xash || ! -f $C/src/out-cs/cstrike/dlls/cs_amd64.so ]]; then
    cp "$AQUI/build-nativo.sh" "$C/src/"
    docker run --rm -v "$C":/w:z ubuntu:24.04 sh -c 'apt-get update -qq >/dev/null && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq build-essential python3 cmake git pkg-config >/dev/null 2>&1 && /w/src/build-nativo.sh; chown -R '"$(id -u):$(id -g)"' /w/src'
fi

# 2. HLDS (SteamCMD anónimo, app 90 con mod cstrike)
if [[ ! -f $C/hlds/cstrike/steam.inf ]]; then
    mkdir -p "$C/steamcmd" "$C/hlds" "$C/home"
    curl -sfL https://steamcdn-a.akamaihd.net/client/installer/steamcmd_linux.tar.gz | tar xz -C "$C/steamcmd"
    docker run --rm -e HOME=/w/home -v "$C":/w:z -w /w debian:bookworm-slim bash -c '
      apt-get update -qq >/dev/null && apt-get install -y -qq lib32gcc-s1 ca-certificates >/dev/null 2>&1
      for i in 1 2 3 4; do
        setpriv --reuid='"$(id -u)"' --regid='"$(id -g)"' --clear-groups ./steamcmd/steamcmd.sh +force_install_dir /w/hlds \
          +login anonymous +app_set_config 90 mod cstrike +app_update 90 validate +quit > /w/steamcmd.log 2>&1 || true
        grep -q "Success! App .90. fully installed" /w/steamcmd.log && break
      done'
fi
grep -q 'PatchVersion=1.1.2.7' "$C/hlds/cstrike/steam.inf" || echo "⚠️  HLDS con otra versión: $(grep PatchVersion "$C/hlds/cstrike/steam.inf")"

# Todos los mapas oficiales (filosofía «museo»: si corre en web sin tumbar el servidor, dentro)
mapfile -t MAPAS < <(cd "$C/hlds/cstrike/maps" 2>/dev/null && ls *.bsp | sed "s/\.bsp$//")

# 3. Servidor
EXCL=(--exclude='*.so' --exclude='*.dll' --exclude='*.dylib' --exclude='*.exe' --exclude='dlls/' --exclude='cl_dlls/' --exclude='media/' --exclude='overviews/')
rm -rf "$C/srv" && mkdir -p "$C/srv"
cp "$C/src/out-xash/xash" "$C/src/out-xash/filesystem_stdio.so" "$C/srv/"
rsync -a "${EXCL[@]}" --exclude='maps/' "$C/hlds/valve/" "$C/srv/valve/"
rsync -a "${EXCL[@]}" --exclude='maps/' "$C/hlds/cstrike/" "$C/srv/cstrike/"
mkdir -p "$C/srv/cstrike/maps" "$C/srv/cstrike/dlls"
for m in "${MAPAS[@]}"; do cp "$C/hlds/cstrike/maps/$m".* "$C/srv/cstrike/maps/"; done
cp "$C/src/out-cs/cstrike/dlls/cs_amd64.so" "$C/src/out-cs/cstrike/dlls/yapb_amd64.so" "$C/srv/cstrike/dlls/"
cp "$C/src/out-cs/cstrike/extras.pk3" "$C/srv/cstrike/"
sed -i 's|^gamedll_linux .*|gamedll_linux "dlls/yapb.so"|' "$C/srv/cstrike/liblist.gam"
# Nombres de los bots en castellano: el fichero suelto manda sobre el del extras.pk3
mkdir -p "$C/srv/cstrike/addons/yapb/conf" && cp "$AQUI/../imagen-salas/yapb/names.cfg" "$C/srv/cstrike/addons/yapb/conf/names.cfg"

# 4. Datos del cliente: lo del servidor sin voces ni criaturas de Half-Life
rm -rf "$C/datos-cliente" && mkdir -p "$C/datos-cliente"
cp -al "$C/srv/valve" "$C/srv/cstrike" "$C/datos-cliente/"
rm -rf "$C/datos-cliente/cstrike/dlls"
# El liblist.gam del servidor apunta a yapb (bots); el cliente necesita el original (si no, el motor
# web intenta cargar yapb_emscripten_wasm32.wasm y lanza un alert() que bloquea la página)
rm -f "$C/datos-cliente/cstrike/liblist.gam" && cp "$C/hlds/cstrike/liblist.gam" "$C/datos-cliente/cstrike/liblist.gam"
for d in scientist barney hgrunt vox fvox agrunt aslave bullchicken controller garg gonarch headcrab houndeye ichy leech \
         nihilanth tentacle turret zombie x apache osprey gman holo hassault squeek roach boid babycrab bigmomma; do
    rm -rf "$C/datos-cliente/valve/sound/$d"
done
(cd "$C/datos-cliente" && tar -cf - valve cstrike | gzip -6 > "$C/arena-cs-datos.tar.gz")

# 5. Motor web y cliente de CS (copias guardadas: el paquete npm está deprecado y el de CS despublicado)
rm -rf "$C/web" && mkdir -p "$C/web/pkg" "$C/web/xash" "$C/web/cs"
tar xzf "$COPIAS/xash3d-fwgs-1.2.2.tgz" -C "$C/web/pkg"
D=$C/web/pkg/package/dist W=$COPIAS/webxash-gh-pages/cs16-client/dist/cstrike
cp "$D/xash.wasm" "$D/filesystem_stdio.wasm" "$D/libref_webgl2.wasm" "$D/valve/extras.pk3" "$C/web/xash/"
cp "$W/cl_dlls/client_emscripten_wasm32.wasm" "$W/cl_dlls/menu_emscripten_wasm32.wasm" "$W/dlls/cs_emscripten_wasm32.wasm" "$C/web/cs/"
du -sh "$C/srv" "$C/arena-cs-datos.tar.gz" "$C/web"
