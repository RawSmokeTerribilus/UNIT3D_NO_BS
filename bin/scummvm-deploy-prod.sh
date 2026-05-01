#!/usr/bin/env bash
# scummvm-deploy-prod.sh
#
# Mirrors the staging arcade (UNIT3D_Develop) into production (UNIT3D_Docker).
# Designed to be re-run safely: it compares sha256 of code files and only
# re-copies what actually changed. Game-data dirs are wiped and re-copied
# unconditionally because their contents are large + binary (rsync would be
# better but we deliberately avoid it for predictability).
#
# What it copies:
#   - public/games/<id>/                 (every game-data dir, recursively)
#   - public/img/games/<id>.{png,jpg,…}  (cover images)
#   - app/Http/Controllers/GamingController.php
#   - public/js/scummvm-launcher.js
#   - resources/views/gaming/{show,index}.blade.php
#   - config/gaming.php
#   - bin/scummvm-*.sh                   (the scripts themselves, kept in sync)
#
# What it does NOT touch:
#   - public/engine/   (engine binaries deployed separately, manual)
#   - any DB / .env / docker-compose.yml
#   - prod's git tree (no commit; caller verifies in browser, then commits)
#
# Usage:
#   bin/scummvm-deploy-prod.sh --dry-run   # report what would change
#   bin/scummvm-deploy-prod.sh             # actually copy

set -euo pipefail

DRY=0
[ "${1:-}" = "--dry-run" ] && DRY=1

SRC=/home/rawserver/UNIT3D_Develop
DST=/home/rawserver/UNIT3D_Docker

[ -d "$SRC" ] || { echo "ERROR: source not found: $SRC" >&2; exit 1; }
[ -d "$DST" ] || { echo "ERROR: destination not found: $DST" >&2; exit 1; }

run() {
    if [ "$DRY" = 1 ]; then echo "  [DRY] $*"; else "$@"; fi
}

hash_of() {
    [ -f "$1" ] || { echo "absent"; return; }
    sha256sum "$1" | cut -c1-12
}

[ "$DRY" = 1 ] && echo "*** DRY RUN — no changes will be made ***" && echo

# ────────── 1. Game data dirs ──────────────────────────────────────────────
# Skip dirs where prod already matches: same file count AND same total bytes.
# Not a content hash (too expensive for 1 GB games) but catches additions,
# removals, and any change that affects file size — which is everything in
# practice for binary game assets. Force a re-copy with FORCE_GAMES=1.
echo "=== public/games/ ==="
for d in "$SRC/public/games/"*/; do
    id="$( basename "$d" )"
    src_n="$(  find "$d" -type f | wc -l )"
    src_b="$(  du -sb  "$d" | cut -f1 )"
    src_size="$( du -sh "$d" | cut -f1 )"

    if [ -d "$DST/public/games/$id" ]; then
        dst_n="$( find "$DST/public/games/$id" -type f | wc -l )"
        dst_b="$( du -sb  "$DST/public/games/$id" | cut -f1 )"
        if [ "${FORCE_GAMES:-0}" != 1 ] && [ "$src_n" = "$dst_n" ] && [ "$src_b" = "$dst_b" ]; then
            echo "  $id  unchanged ($src_n files, $src_size)"
            continue
        fi
        echo "  $id  changed   (src: $src_n files / $src_b B   dst: $dst_n files / $dst_b B)"
    else
        echo "  $id  new       ($src_n files, $src_size)"
    fi

    run sudo rm -rf "$DST/public/games/$id"
    run sudo cp -rp "$d" "$DST/public/games/$id"
    run sudo chown -R 82:82 "$DST/public/games/$id"
done

# Detect prod-only dirs (catalog dropped a game) and warn — never auto-delete
for d in "$DST/public/games/"*/; do
    id="$( basename "$d" )"
    [ -d "$SRC/public/games/$id" ] || echo "  WARN: prod has /public/games/$id but staging doesn't — leaving alone"
done

# ────────── 2. Cover images ────────────────────────────────────────────────
echo
echo "=== public/img/games/ ==="
for img in "$SRC/public/img/games/"*; do
    bn="$( basename "$img" )"
    [ "$bn" = "README.md" ] && continue
    [ -f "$img" ] || continue
    src_h="$( hash_of "$img" )"
    dst_h="$( hash_of "$DST/public/img/games/$bn" )"
    if [ "$src_h" = "$dst_h" ]; then
        echo "  $bn  unchanged ($src_h)"
    else
        echo "  $bn  src=$src_h dst=$dst_h"
        run sudo cp -p "$img" "$DST/public/img/games/$bn"
        run sudo chown 82:82 "$DST/public/img/games/$bn"
    fi
done

# ────────── 3. Code files ──────────────────────────────────────────────────
echo
echo "=== code files ==="
CODE_FILES=(
    app/Http/Controllers/GamingController.php
    public/js/scummvm-launcher.js
    resources/views/gaming/show.blade.php
    resources/views/gaming/index.blade.php
    config/gaming.php
    bin/scummvm-stage-game.sh
    bin/scummvm-deploy-prod.sh
)
for f in "${CODE_FILES[@]}"; do
    src_h="$( hash_of "$SRC/$f" )"
    dst_h="$( hash_of "$DST/$f" )"
    if [ "$src_h" = "absent" ]; then
        echo "  $f  MISSING in src — skipping"
        continue
    fi
    if [ "$src_h" = "$dst_h" ]; then
        echo "  $f  unchanged ($src_h)"
        continue
    fi
    echo "  $f  src=$src_h dst=$dst_h"
    parent="$( dirname "$DST/$f" )"
    [ -d "$parent" ] || run sudo mkdir -p "$parent"
    run sudo cp -p "$SRC/$f" "$DST/$f"
    run sudo chown 82:82 "$DST/$f"
done

# ────────── 4. Bust Laravel's config cache on prod ─────────────────────────
echo
echo "=== post-deploy ==="
if [ "$DRY" = 1 ]; then
    echo "  [DRY] cd $DST && docker compose exec -T app php artisan config:clear"
else
    if cd "$DST" && docker compose ps -q app >/dev/null 2>&1; then
        cd "$DST" && docker compose exec -T app php artisan config:clear
    else
        echo "  WARN: prod 'app' container not running — skipping config:clear"
    fi
fi

echo
if [ "$DRY" = 1 ]; then
    echo "Re-run without --dry-run to apply."
else
    echo "Done. Verify https://nobs.rawsmoke.net/gaming, then on prod:"
    echo "    cd $DST && git add -A && git commit -m 'feat(arcade): sync from staging'"
fi
