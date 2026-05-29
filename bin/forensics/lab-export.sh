#!/bin/bash
# Emit INSERT IGNORE statements for rows present in the recovered lab table but
# missing from prod (by `id`). Output is a .sql file you review, then apply to
# prod yourself as a separate, deliberate step. This script NEVER writes to prod.
#
# Usage: lab-export.sh <table> [--out <file>]
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/_common.sh"

[ $# -ge 1 ] || die "usage: lab-export.sh <table> [--out <file>]"
T="$1"; shift
OUT=""
while [ $# -gt 0 ]; do
  case "$1" in
    --out) OUT="$2"; shift 2 ;;
    *) die "unknown arg: $1" ;;
  esac
done

require_lab_up
EXPORT_DIR="$BACKUPS_DIR_ABS/forensics-export"
mkdir -p "$EXPORT_DIR"
[ -n "$OUT" ] || OUT="$EXPORT_DIR/missing_${T}_$(date +%F_%H%M).sql"

# Refresh the prod copy so the anti-join is against current prod.
lab_mysql -e "CREATE DATABASE IF NOT EXISTS \`$PROD_NS\`;"
log "Refreshing prod.$T (read-only) into $PROD_NS"
prod_mysqldump "$DB_NAME" "$T" 2>/dev/null | lab_mysql "$PROD_NS"

MISSING="$(lab_mysql -N -e "SELECT COUNT(*) FROM \`$DB_NAME\`.\`$T\` l \
  LEFT JOIN \`$PROD_NS\`.\`$T\` p ON l.id=p.id WHERE p.id IS NULL;")"
log "$T: $MISSING row(s) in lab missing from prod"
[ "$MISSING" -gt 0 ] || { log "Nothing to export."; exit 0; }

# Dump only the lab-only rows as INSERT IGNORE. Subquery is cross-schema on the
# same server, so mysqldump --where handles it.
docker exec -e MYSQL_PWD="$LAB_DB_PASSWORD" "$LAB_DB_CONTAINER" \
  mysqldump -uroot --no-create-info --insert-ignore --complete-insert --skip-extended-insert \
    --where="id NOT IN (SELECT id FROM \`$PROD_NS\`.\`$T\`)" \
    "$DB_NAME" "$T" > "$OUT"

log "Wrote $MISSING-row export to: $OUT"
log "Review it, then apply to prod manually when you're ready (this script will not)."
