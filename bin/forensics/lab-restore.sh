#!/bin/bash
# Hybrid point-in-time restore into the lab:
#   1. import a 4h SQL dump (latest by default)
#   2. replay binlogs from the coordinate embedded in that dump (--source-data=2)
#      forward to a target time (HEAD by default) to recover the gap since the dump.
#
# Usage:
#   lab-restore.sh [--dump <path|latest>] [--until "YYYY-MM-DD HH:MM:SS"|HEAD] [--no-replay]
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/_common.sh"

DUMP="latest"
UNTIL="HEAD"
REPLAY=1
while [ $# -gt 0 ]; do
  case "$1" in
    --dump) DUMP="$2"; shift 2 ;;
    --until) UNTIL="$2"; shift 2 ;;
    --no-replay) REPLAY=0; shift ;;
    *) die "unknown arg: $1" ;;
  esac
done

require_lab_up

# --- pick the dump ---
if [ "$DUMP" = "latest" ]; then
  DUMP="$(ls -1t "$BACKUPS_DIR_ABS"/db_regular/db_unit3d_*.sql.gz 2>/dev/null | head -1)"
  [ -n "$DUMP" ] || die "no dump found in $BACKUPS_DIR_ABS/db_regular/"
fi
[ -f "$DUMP" ] || die "dump not found: $DUMP"
log "Using dump: $DUMP"

if [ -f "$DUMP.sha256" ]; then
  (cd "$(dirname "$DUMP")" && sha256sum -c "$(basename "$DUMP").sha256") >/dev/null \
    && log "sha256 OK" || die "sha256 mismatch on $DUMP"
fi

# --- import snapshot into a clean lab DB ---
log "Resetting lab schema $DB_NAME"
lab_mysql -e "DROP DATABASE IF EXISTS \`$DB_NAME\`; CREATE DATABASE \`$DB_NAME\`;"
log "Importing snapshot (this can take a minute)"
gzip -dc "$DUMP" | lab_mysql "$DB_NAME"
log "Snapshot imported."

# --- determine the binlog coordinate to replay from ---
# Preferred: the privilege-free sidecar written by db-backup-regular.sh.
# Fallback: an in-dump --source-data coordinate, if one was ever used.
START_FILE=""; START_POS=""
if [ -f "$DUMP.binlogpos" ]; then
  START_FILE="$(grep -E '^SOURCE_LOG_FILE=' "$DUMP.binlogpos" | cut -d= -f2)"
  START_POS="$(grep -E '^SOURCE_LOG_POS=' "$DUMP.binlogpos" | cut -d= -f2)"
  log "Coordinate from sidecar: $START_FILE @ $START_POS"
else
  COORD_LINE="$(gzip -dc "$DUMP" | grep -m1 -E 'CHANGE (MASTER|REPLICATION SOURCE) TO' || true)"
  if [ -n "$COORD_LINE" ]; then
    START_FILE="$(printf '%s' "$COORD_LINE" | grep -oE "(SOURCE|MASTER)_LOG_FILE='[^']+'" | cut -d\' -f2)"
    START_POS="$(printf '%s' "$COORD_LINE" | grep -oE "(SOURCE|MASTER)_LOG_POS=[0-9]+" | cut -d= -f2)"
    log "Coordinate from dump header: $START_FILE @ $START_POS"
  fi
fi
if [ -z "$START_FILE" ] || [ -z "$START_POS" ]; then
  log "WARNING: no binlog coordinate (no .binlogpos sidecar and no in-dump coordinate)."
  log "Snapshot-only restore complete; cannot replay the gap."
  exit 0
fi

if [ "$REPLAY" -eq 0 ]; then
  log "--no-replay set; stopping after snapshot import."
  exit 0
fi

# --- replay binlogs in the toolbox (version-matched Oracle mysqlbinlog + RO mount) ---
# Toolbox has /prod/mysql:ro and reaches lab-db over the forensics network.
STOP_ARG=""
[ "$UNTIL" != "HEAD" ] && STOP_ARG="$UNTIL"
log "Replaying binlogs from $START_FILE:$START_POS up to ${UNTIL}"

fx bash -c '
  set -euo pipefail
  start="$1"; pos="$2"; stop="$3"; db="$4"; host="$5"
  cd /prod/mysql
  files=$(ls -1 binlog.[0-9]* 2>/dev/null | sort | awk -v s="$start" "\$0 >= s")
  [ -n "$files" ] || { echo "no binlog files >= $start found" >&2; exit 1; }
  args=(--start-position="$pos" --database="$db")
  [ -n "$stop" ] && args+=(--stop-datetime="$stop")
  echo "replaying: $files" >&2
  # --force: the coordinate is captured just before the dump snapshot, so a few
  # boundary events may already be in the snapshot. Skipping those dup-key errors
  # is correct; lab-diff is the authoritative check for what prod is missing.
  mysqlbinlog "${args[@]}" $files | mysql -h "$host" --ssl-mode=DISABLED --get-server-public-key --force -uroot "$db"
' _ "$START_FILE" "$START_POS" "$STOP_ARG" "$DB_NAME" "${LAB_DB_HOST:-lab-db}"

log "Replay complete."

# --- quick recovery report ---
log "Recovered state in lab:"
lab_mysql -N "$DB_NAME" -e "
  SELECT 'users',    COUNT(*), MAX(created_at) FROM users
  UNION ALL SELECT 'topics',   COUNT(*), MAX(created_at) FROM topics
  UNION ALL SELECT 'posts',    COUNT(*), MAX(created_at) FROM posts
  UNION ALL SELECT 'comments', COUNT(*), MAX(created_at) FROM comments
  UNION ALL SELECT 'torrents', COUNT(*), MAX(created_at) FROM torrents;" \
  2>/dev/null | awk -F'\t' '{printf "  %-10s rows=%-8s latest=%s\n",$1,$2,$3}' || true
