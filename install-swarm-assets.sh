#!/usr/bin/env bash
# install-swarm-assets.sh
#
# Downloads vendor JS libraries used by the Swarm Map feature.
# Assets live under public/vendor/ which is gitignored, so they must
# be re-fetched after every fresh clone, container rebuild from scratch, or
# server migration.
#
# Safe to run multiple times — only downloads missing/undersized files.
#
# Usage:
#   ./install-swarm-assets.sh                  # run on host (writes to bind mount)
#   docker compose exec app ./install-swarm-assets.sh   # run inside container
#
# Dependencies: curl (or wget fallback)

set -euo pipefail

if [ -d /var/www/html/public ]; then
    PUBLIC_DIR="/var/www/html/public"
else
    PUBLIC_DIR="$(cd "$(dirname "$0")" && pwd)/public"
fi

# Asset table: dir|filename|url|min_size_bytes
ASSETS="
force-graph|force-graph.min.js|https://unpkg.com/force-graph/dist/force-graph.min.js|100000
3d-force-graph|3d-force-graph.min.js|https://unpkg.com/3d-force-graph@1.73.4/dist/3d-force-graph.min.js|500000
three|three.min.js|https://unpkg.com/three@0.150.0/build/three.min.js|400000
d3|d3.min.js|https://unpkg.com/d3@7/dist/d3.min.js|200000
"

fetch() {
    local target="$1" url="$2"
    if command -v curl >/dev/null 2>&1; then
        curl -fsSL "$url" -o "$target"
    elif command -v wget >/dev/null 2>&1; then
        wget -q "$url" -O "$target"
    else
        echo "[swarm-assets] ERROR: neither curl nor wget available" >&2
        return 1
    fi
}

file_size() {
    stat -c%s "$1" 2>/dev/null || stat -f%z "$1"
}

while IFS='|' read -r dir filename url min_size; do
    [ -z "$dir" ] && continue
    vendor_dir="$PUBLIC_DIR/vendor/$dir"
    target="$vendor_dir/$filename"
    mkdir -p "$vendor_dir"

    if [ -f "$target" ] && [ "$(file_size "$target")" -ge "$min_size" ]; then
        echo "[swarm-assets] $filename present ($(du -h "$target" | cut -f1)) — skip"
        continue
    fi

    echo "[swarm-assets] fetching $filename from $url"
    if ! fetch "$target" "$url"; then
        echo "[swarm-assets] ERROR: failed to fetch $filename" >&2
        rm -f "$target"
        continue
    fi

    fetched_size="$(file_size "$target")"
    if [ "$fetched_size" -lt "$min_size" ]; then
        echo "[swarm-assets] ERROR: $filename too small ($fetched_size bytes < $min_size) — likely failed" >&2
        rm -f "$target"
        continue
    fi

    echo "[swarm-assets] installed $filename ($(du -h "$target" | cut -f1))"
done <<< "$ASSETS"

echo "[swarm-assets] done"
