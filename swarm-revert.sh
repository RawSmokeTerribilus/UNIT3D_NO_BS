#!/usr/bin/env bash
# swarm-revert.sh — one-shot revert of the Swarm Map graph.blade.php
# back to its pre-Opera-fix SAFE backup.
#
# Usage:
#   ./swarm-revert.sh prod      # revert UNIT3D_Docker  (default)
#   ./swarm-revert.sh staging   # revert UNIT3D_Develop
set -euo pipefail

ENV="${1:-prod}"

case "$ENV" in
    prod)
        REPO="/home/rawserver/UNIT3D_Docker"
        CONTAINER="unit3d-app"
        ;;
    staging)
        REPO="/home/rawserver/UNIT3D_Develop"
        CONTAINER="unit3d-staging-app"
        ;;
    *)
        echo "usage: $0 [prod|staging]" >&2
        exit 1
        ;;
esac

LIVE="$REPO/resources/views/swarm/graph.blade.php"
BACKUP="$REPO/backups/manual/graph.blade.php.SAFE-20260521"

if [ ! -f "$BACKUP" ]; then
    echo "[swarm-revert] ERROR: backup not found: $BACKUP" >&2
    exit 1
fi

cp -p "$BACKUP" "$LIVE"
echo "[swarm-revert] restored $LIVE from SAFE backup"

docker exec "$CONTAINER" php artisan view:clear
echo "[swarm-revert] view cache cleared in $CONTAINER"
echo "[swarm-revert] done — hard-refresh /swarm to confirm"
