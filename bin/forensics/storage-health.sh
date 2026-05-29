#!/bin/bash
# Read-only storage/infra health for the dashboard: binlog footprint + PITR window,
# redis/meilisearch datadir sizes, and the datadir filesystem capacity. Touches no
# Laravel app state and runs no artisan — pure filesystem/container reads.
# Consumed by GET /api/storage-health.
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/_common.sh"

# Binlog facts from the always-up prod container (privilege-free stat, same pattern
# db-backup-regular.sh uses for the coordinate).
BINLOGS='{"count":0,"bytes":0,"oldest":null,"newest":null}'
if docker ps --format '{{.Names}}' | grep -Fxq "$PROD_DB_CONTAINER"; then
  RAW="$(docker exec "$PROD_DB_CONTAINER" sh -c \
    'for f in /var/lib/mysql/binlog.[0-9]*; do [ -f "$f" ] && stat -c "%s %Y" "$f"; done' 2>/dev/null || true)"
  if [ -n "$RAW" ]; then
    BINLOGS="$(printf '%s\n' "$RAW" | awk '
      { c++; b += $1; if (min=="" || $2<min) min=$2; if ($2>max) max=$2 }
      END { printf "{\"count\":%d,\"bytes\":%d,\"oldest\":%s,\"newest\":%s}", c, b, (min==""?"null":min), (max==""?"null":max) }')"
  fi
fi

# Redis / Meilisearch datadir footprint (host paths; passwordless sudo for the
# mysql-owned dirs). Best-effort — null if unreadable.
dir_bytes() {
  local d="$1"
  case "$d" in /*) : ;; *) d="$PROJECT_ROOT/${d#./}" ;; esac
  [ -d "$d" ] || { printf 'null'; return; }
  local n
  n="$(sudo du -sb "$d" 2>/dev/null | cut -f1)"
  [ -n "$n" ] && printf '%s' "$n" || printf 'null'
}
REDIS_BYTES="$(dir_bytes "${REDIS_DIR:-./.docker/data/redis}")"
MEILI_BYTES="$(dir_bytes "${MEILI_DIR:-./.docker/data/meilisearch}")"

# Datadir filesystem capacity (the real ceiling).
DATADIR="$PROJECT_ROOT/${PROD_MYSQL_DIR#./}"
DISK="$(df -B1 --output=size,used,avail,pcent "$DATADIR" 2>/dev/null \
  | awk 'NR==2{gsub("%","",$4); printf "{\"total\":%d,\"used\":%d,\"avail\":%d,\"pct\":%d}",$1,$2,$3,$4}')"
[ -z "$DISK" ] && DISK=null

printf '{"binlogs":%s,"redis_bytes":%s,"meili_bytes":%s,"disk":%s,"ts":"%s"}\n' \
  "$BINLOGS" "$REDIS_BYTES" "$MEILI_BYTES" "$DISK" "$(date +%FT%T%:z)"
