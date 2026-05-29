#!/bin/bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$PROJECT_ROOT/.env"
BACKUP_ROOT="$PROJECT_ROOT/backups/db_regular"
LOG_DIR="$PROJECT_ROOT/logs"
LOG_FILE="$LOG_DIR/db_backup_regular.log"
DB_CONTAINER="${DB_CONTAINER:-unit3d-db}"
DB_NAME="${DB_NAME:-unit3d}"
BACKUP_DB_USER="${BACKUP_DB_USER:-backupbot}"
FULL_SNAPSHOT_HOUR="${FULL_SNAPSHOT_HOUR:-6}"
TIMESTAMP="$(date +%F_%H%M)"
TMP_FILE=""

read_env() {
  local key="$1"
  local default="${2-}"
  local value

  value="$(grep -E "^${key}=" "$ENV_FILE" 2>/dev/null | head -1 | cut -d'=' -f2- | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//")"

  if [ -n "$value" ]; then
    printf '%s' "$value"
  else
    printf '%s' "$default"
  fi
}

log() {
  printf '[%s] %s\n' "$(date +'%F %T')" "$*" | tee -a "$LOG_FILE"
}

mkdir -p "$BACKUP_ROOT" "$LOG_DIR"

if ! command -v docker >/dev/null 2>&1; then
  log "ERROR: docker no está disponible"
  exit 1
fi

if ! docker ps --format '{{.Names}}' | grep -Fxq "$DB_CONTAINER"; then
  log "ERROR: el contenedor $DB_CONTAINER no está levantado"
  exit 1
fi

DB_PASSWORD="$(read_env DB_PASSWORD '')"
if [ -z "$DB_PASSWORD" ]; then
  log "ERROR: no se pudo leer DB_PASSWORD desde $ENV_FILE"
  exit 1
fi

OUT_FILE="$BACKUP_ROOT/db_unit3d_${TIMESTAMP}.sql.gz"
SHA_FILE="$OUT_FILE.sha256"
TMP_FILE="$OUT_FILE.tmp"

cleanup() {
  if [ -n "$TMP_FILE" ] && [ -f "$TMP_FILE" ]; then
    rm -f "$TMP_FILE"
  fi
}

trap cleanup EXIT

log "Inicio dump regular -> $OUT_FILE"

# Capture the binlog coordinate for point-in-time replay WITHOUT any DB privilege:
# the newest binlog file and its current byte size == the next write position.
# Recorded just before the dump's consistent snapshot; the few-ms overlap is
# absorbed by `mysql --force` during lab replay, and lab-diff is authoritative.
POS_FILE="$OUT_FILE.binlogpos"
BINLOG_COORD="$(docker exec "$DB_CONTAINER" sh -c \
  'f=$(ls -1 /var/lib/mysql/binlog.[0-9]* 2>/dev/null | sort | tail -1); \
   [ -n "$f" ] && printf "%s %s" "$(basename "$f")" "$(stat -c %s "$f")"' 2>/dev/null || true)"

docker exec -e MYSQL_PWD="$DB_PASSWORD" "$DB_CONTAINER" \
  mysqldump \
    -h127.0.0.1 \
    --protocol=TCP \
    -u"$BACKUP_DB_USER" \
    --single-transaction \
    --skip-lock-tables \
    --quick \
    --routines \
    --triggers \
    --events \
    --set-gtid-purged=OFF \
    "$DB_NAME" | gzip -1 > "$TMP_FILE"

gzip -t "$TMP_FILE"
mv "$TMP_FILE" "$OUT_FILE"
sha256sum "$OUT_FILE" > "$SHA_FILE"

if [ -n "$BINLOG_COORD" ]; then
  printf 'SOURCE_LOG_FILE=%s\nSOURCE_LOG_POS=%s\n' $BINLOG_COORD > "$POS_FILE"
  log "binlog coordinate: $BINLOG_COORD -> $POS_FILE"
else
  log "WARNING: could not read binlog coordinate (PITR replay will be snapshot-only)"
fi

if [ "$(date +%H)" -ge "$FULL_SNAPSHOT_HOUR" ]; then
  SNAPSHOT_CUTOFF="$(date +"%F") ${FULL_SNAPSHOT_HOUR}:00:00"
else
  SNAPSHOT_CUTOFF="$(date -d 'yesterday' +"%F") ${FULL_SNAPSHOT_HOUR}:00:00"
fi

find "$BACKUP_ROOT" -type f \( -name 'db_unit3d_*.sql.gz' -o -name 'db_unit3d_*.sql.gz.sha256' \) ! -newermt "$SNAPSHOT_CUTOFF" -delete

log "OK size=$(du -h "$OUT_FILE" | cut -f1) sha256=$(cut -d' ' -f1 "$SHA_FILE") cutoff=$SNAPSHOT_CUTOFF"
