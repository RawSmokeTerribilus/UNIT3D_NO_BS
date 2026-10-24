#!/usr/bin/env bash
# Arena NOBS · Quake 3 — fuente de ioquake3 en el commit de parche/BASE + parche de la Arena,
# y OpenArena 0.8.8 (zip oficial, sha256 comprobado). Todo en $Q3_TRABAJO (~/tmp-trabajo/q3).
set -euo pipefail
Q=${Q3_TRABAJO:-$HOME/tmp-trabajo/q3}
AQUI=$(cd "$(dirname "$0")/.." && pwd)
COMMIT=$(awk '/^ioq3 /{print $2}' "$AQUI/parche/BASE")
OA_SHA=5a8faf7f5b51f351b0a1618c06b6b98a5f1a6758f1d39818de2c87df2a0bac4a
mkdir -p "$Q"
if [[ ! -d $Q/ioq3/.git ]]; then
    git clone -q https://github.com/ioquake/ioq3.git "$Q/ioq3"
fi
git -C "$Q/ioq3" fetch -q origin "$COMMIT" 2>/dev/null || true
git -C "$Q/ioq3" checkout -q -f "$COMMIT"
git -C "$Q/ioq3" apply "$AQUI/parche/0001-arena-web-net.patch"
echo "ioq3 en $COMMIT con el parche de la Arena"
if [[ ! -f $Q/oa/openarena-0.8.8/baseoa/pak0.pk3 ]]; then
    curl -sfL https://download.tuxfamily.org/openarena/rel/088/openarena-0.8.8.zip -o "$Q/openarena-0.8.8.zip"
    echo "$OA_SHA  $Q/openarena-0.8.8.zip" | sha256sum -c -
    mkdir -p "$Q/oa" && unzip -q -o "$Q/openarena-0.8.8.zip" -d "$Q/oa"
fi
echo "OpenArena 0.8.8 en $Q/oa"
