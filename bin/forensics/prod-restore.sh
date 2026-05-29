#!/bin/bash
# FULL DISASTER RECOVERY — restore a snapshot (+ binlog replay to a point in time)
# straight into PROD. THIS IS DESTRUCTIVE: it DROPs and recreates the prod schema.
# Gated hard:
#   - password (PROD_WRITE_PW env) verified by a live connect
#   - typed confirm token PROD_RESTORE_CONFIRM must equal "RESTORE PROD"
#   - mandatory backup-first (snapshots current prod, even if wiped, for audit/undo)
# Use the damage timeline to set --until to JUST BEFORE the wipe, so you don't
# replay the disaster back in. Run prod-apply afterwards to fill any later gap.
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/_common.sh"

DUMP_ARG="latest"
UNTIL="HEAD"
while [ "$#" -gt 0 ]; do
  case "$1" in
    --dump) DUMP_ARG="$2"; shift 2 ;;
    --until) UNTIL="$2"; shift 2 ;;
    *) die "unknown arg: $1" ;;
  esac
done

PROD_WRITE_USER="${PROD_WRITE_USER:-${DB_USERNAME:-unit3d}}"
: "${PROD_WRITE_PW:?PROD_WRITE_PW env required (the typed prod DB password)}"
[ "${PROD_RESTORE_CONFIRM:-}" = "RESTORE PROD" ] || die "refused: type 'RESTORE PROD' to confirm a destructive prod restore"

# Resolve dump
if [ "$DUMP_ARG" = "latest" ]; then
  DUMP="$(ls -1t "$BACKUPS_DIR_ABS"/db_regular/*.sql.gz 2>/dev/null | head -1 || true)"
else
  case "$DUMP_ARG" in */*) DUMP="$DUMP_ARG";; *) DUMP="$BACKUPS_DIR_ABS/db_regular/$DUMP_ARG";; esac
fi
[ -n "${DUMP:-}" ] && [ -f "$DUMP" ] || die "dump not found: $DUMP_ARG"
log "Dump: $DUMP   until: $UNTIL"

pw_mysql() {
  docker exec -i -e MYSQL_PWD="$PROD_WRITE_PW" "$PROD_DB_CONTAINER" \
    mysql -h127.0.0.1 --protocol=TCP -u"$PROD_WRITE_USER" "$@"
}

log "Verifying prod credentials for ${PROD_WRITE_USER}@${PROD_DB_CONTAINER}…"
echo "SELECT 1;" | pw_mysql >/dev/null 2>&1 || die "credential check failed — wrong password or no access (nothing written)"

# Verify checksum if present
if [ -f "$DUMP.sha256" ]; then
  ( cd "$(dirname "$DUMP")" && sha256sum -c "$(basename "$DUMP").sha256" >/dev/null 2>&1 ) \
    && log "sha256 OK" || die "dump checksum FAILED — aborting"
fi

log "Backup-first: snapshotting current prod state before the destructive restore…"
"$PROJECT_ROOT/bin/db-backup-regular.sh" >&2 || die "backup-first FAILED — aborting, prod untouched"

log "DROP + CREATE prod schema ${DB_NAME}…"
echo "DROP DATABASE IF EXISTS \`$DB_NAME\`; CREATE DATABASE \`$DB_NAME\`;" | pw_mysql

log "Importing snapshot into prod (this can take a minute)…"
gzip -dc "$DUMP" | pw_mysql "$DB_NAME"
log "Snapshot imported."

if [ "$UNTIL" != "HEAD" ] || true; then
  # binlog coordinate from the dump's sidecar (privilege-free)
  SIDE="$DUMP.binlogpos"
  if [ -f "$SIDE" ]; then
    START_FILE="$(grep -E '^SOURCE_LOG_FILE=' "$SIDE" | cut -d= -f2 | tr -d '[:space:]')"
    START_POS="$(grep -E '^SOURCE_LOG_POS=' "$SIDE" | cut -d= -f2 | tr -d '[:space:]')"
  else
    COORD_LINE="$(gzip -dc "$DUMP" | grep -m1 -E 'CHANGE (MASTER|REPLICATION SOURCE) TO' || true)"
    START_FILE="$(printf '%s' "$COORD_LINE" | grep -oE "MASTER_LOG_FILE='[^']+'|SOURCE_LOG_FILE='[^']+'" | cut -d"'" -f2 || true)"
    START_POS="$(printf '%s' "$COORD_LINE" | grep -oE "MASTER_LOG_POS=[0-9]+|SOURCE_LOG_POS=[0-9]+" | grep -oE '[0-9]+' || true)"
  fi
  if [ -n "${START_FILE:-}" ] && [ -n "${START_POS:-}" ]; then
    STOP_ARG=""; [ "$UNTIL" != "HEAD" ] && STOP_ARG="$UNTIL"
    log "Replaying binlogs from ${START_FILE}:${START_POS} up to ${UNTIL} into PROD…"
    # mysqlbinlog runs in the toolbox (version-matched + RO mount); its SQL is piped
    # on the host into the prod mysql client (toolbox has no route to prod).
    fx bash -c '
      set -e
      cd /prod/mysql
      files=$(ls -1 binlog.[0-9]* | sort | awk -v s="'"$START_FILE"'" "$0 >= s")
      args=(--start-position="'"$START_POS"'" --database="'"$DB_NAME"'")
      [ -n "'"$STOP_ARG"'" ] && args+=(--stop-datetime="'"$STOP_ARG"'")
      mysqlbinlog "${args[@]}" $files
    ' | pw_mysql --force "$DB_NAME"
    log "Replay complete."
  else
    log "No binlog coordinate found — snapshot-only restore (no replay)."
  fi
fi

log "Recovered prod state:"
pw_mysql -N "$DB_NAME" -e "
  SELECT 'users', COUNT(*) FROM users
  UNION ALL SELECT 'topics', COUNT(*) FROM topics
  UNION ALL SELECT 'torrents', COUNT(*) FROM torrents;" 2>/dev/null | sed 's/^/    /' >&2 || true
log "Prod restore complete. If you stopped before HEAD, run prod-apply to fill later gaps."
