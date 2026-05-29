#!/bin/bash
# Apply a reviewed export (.sql of INSERT IGNORE rows — ADDITIVE) back to PROD.
# This WRITES prod. Gated:
#   - password (PROD_WRITE_PW env, never argv/log/stored) verified by a live connect
#   - mandatory backup-first (aborts if the snapshot fails)
#   - dry-run inside a rolled-back transaction before the real (committed) apply
# INSERT IGNORE never overwrites existing rows, so this only fills gaps.
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/_common.sh"

FILE="${1:?usage: prod-apply.sh <export.sql> [--commit]}"
COMMIT="${2:-}"
PROD_WRITE_USER="${PROD_WRITE_USER:-${DB_USERNAME:-unit3d}}"
: "${PROD_WRITE_PW:?PROD_WRITE_PW env required (the typed prod DB password)}"

case "$FILE" in
  */*) die "export name must be a bare filename" ;;
esac
EXPORT_PATH="$PROJECT_ROOT/backups/forensics-export/$FILE"
[ -f "$EXPORT_PATH" ] || die "export not found: $FILE"

# Prod write account, authenticated with the typed password (env, not argv).
pw_mysql() {
  docker exec -i -e MYSQL_PWD="$PROD_WRITE_PW" "$PROD_DB_CONTAINER" \
    mysql -h127.0.0.1 --protocol=TCP -u"$PROD_WRITE_USER" "$@"
}

log "Verifying prod credentials for ${PROD_WRITE_USER}@${PROD_DB_CONTAINER}…"
echo "SELECT 1;" | pw_mysql >/dev/null 2>&1 || die "credential check failed — wrong password or no access (nothing written)"
log "Account grants:"
echo "SHOW GRANTS FOR CURRENT_USER();" | pw_mysql -N 2>/dev/null | sed 's/^/    /' >&2 || true

log "Backup-first: snapshotting prod before any write…"
"$PROJECT_ROOT/bin/db-backup-regular.sh" >&2 || die "backup-first FAILED — aborting, no write performed"

log "Dry-run (loads in a transaction, then ROLLBACK — nothing committed)…"
{ echo "START TRANSACTION;"; cat "$EXPORT_PATH"; echo "ROLLBACK;"; } \
  | pw_mysql "$DB_NAME" 2>&1 | sed 's/^/    /' >&2 \
  && log "Dry-run applied cleanly." \
  || die "Dry-run FAILED — the export does not apply cleanly; nothing committed"

if [ "$COMMIT" != "--commit" ]; then
  log "Dry-run only (no --commit). Review the log, then apply for real."
  exit 0
fi

log "Applying for real (committed) from $FILE…"
pw_mysql "$DB_NAME" < "$EXPORT_PATH"
log "Apply complete. Run a diff to confirm the gap closed."
