#!/usr/bin/env bash
# Arena NOBS · SuperTuxKart — deja en $STK_TRABAJO (~/tmp-trabajo/stk) lo que usa publicar-stk.sh:
#   demo/game/supertuxkart.{js,wasm,worker.js}  cliente web (de supertuxkart.pages.dev, sha256 de BASE)
#   demo/data_low.tar.gz                         datos (los 7 trozos del manifiesto, concatenados)
#   srv/{supertuxkart, data/}                    servidor nativo SERVER_ONLY del mismo commit + datos
set -euo pipefail
S=${STK_TRABAJO:-$HOME/tmp-trabajo/stk}
AQUI=$(cd "$(dirname "$0")" && pwd)
URL=https://supertuxkart.pages.dev/game
COMMIT=$(awk '$1 == "stk-code" {print $NF}' "$AQUI/BASE")
mkdir -p "$S/demo/game"
for f in supertuxkart.js supertuxkart.wasm supertuxkart.worker.js; do
    [[ -f $S/demo/game/$f ]] || curl -sfL "$URL/$f" -o "$S/demo/game/$f"
done
if [[ ! -f $S/demo/data_low.tar.gz ]]; then
    curl -sfL "$URL/data_low.tar.gz.manifest" -o "$S/demo/game/data_low.tar.gz.manifest"
    tail -n +2 "$S/demo/game/data_low.tar.gz.manifest" | while read -r trozo; do
        [[ -n $trozo ]] && curl -sfL "$URL/$trozo"
    done > "$S/demo/data_low.tar.gz"
fi
# Las sumas de BASE: si la demo cambia, se avisa (habría que recompilar el servidor de ese commit)
(cd "$S/demo/game" && grep -E 'supertuxkart\.(js|wasm|worker\.js)$' "$AQUI/BASE" | sha256sum -c --quiet -) || echo "⚠️  el cliente de la demo no coincide con BASE"
(cd "$S/demo" && grep 'data_low.tar.gz$' "$AQUI/BASE" | sha256sum -c --quiet -) || echo "⚠️  los datos de la demo no coinciden con BASE"
if [[ ! -x $S/stk-code/build-srv/bin/supertuxkart ]]; then
    [[ -d $S/stk-code/.git ]] || git clone -q -b wasm https://github.com/ading2210/stk-code.git "$S/stk-code"
    git -C "$S/stk-code" checkout -q "$COMMIT"
    cp "$AQUI/build-servidor.sh" "$S/"
    docker run --rm -v "$S":/w:z ubuntu:24.04 sh -c 'apt-get update -qq >/dev/null && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq build-essential cmake pkg-config libcurl4-openssl-dev libssl-dev zlib1g-dev libenet-dev git >/dev/null 2>&1; /w/build-servidor.sh; chown -R '"$(id -u):$(id -g)"' /w/stk-code'
fi
rm -rf "$S/srv" && mkdir -p "$S/srv/data"
cp "$S/stk-code/build-srv/bin/supertuxkart" "$S/srv/"
tar xzf "$S/demo/data_low.tar.gz" -C "$S/srv/data"
du -sh "$S/srv" "$S/demo/data_low.tar.gz"
