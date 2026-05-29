#!/bin/bash
# Diff recovered lab tables against current prod, per table, by primary key `id`.
# Pulls each prod table read-only into the lab's prod_ns schema, then reports:
#   lab_rows / prod_rows / lab_only (recovery candidates) / prod_only.
#
# Usage: lab-diff.sh <table> [<table> ...]
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/_common.sh"

[ $# -ge 1 ] || die "usage: lab-diff.sh <table> [<table> ...]"
require_lab_up

lab_mysql -e "CREATE DATABASE IF NOT EXISTS \`$PROD_NS\`;"

printf '%-20s %10s %10s %10s %10s\n' table lab_rows prod_rows lab_only prod_only
for T in "$@"; do
  # guard: table must have an `id` column in lab
  has_id="$(lab_mysql -N -e "SELECT COUNT(*) FROM information_schema.columns \
    WHERE table_schema='$DB_NAME' AND table_name='$T' AND column_name='id';" 2>/dev/null || echo 0)"
  if [ "${has_id:-0}" != "1" ]; then
    printf '%-20s %s\n' "$T" "SKIP (no id column in lab.$T — restore first?)"
    continue
  fi

  log "Pulling prod.$T (read-only) into $PROD_NS"
  prod_mysqldump "$DB_NAME" "$T" 2>/dev/null | lab_mysql "$PROD_NS"

  read -r lab prod labonly prodonly < <(lab_mysql -N -e "
    SELECT
      (SELECT COUNT(*) FROM \`$DB_NAME\`.\`$T\`),
      (SELECT COUNT(*) FROM \`$PROD_NS\`.\`$T\`),
      (SELECT COUNT(*) FROM \`$DB_NAME\`.\`$T\` l
         LEFT JOIN \`$PROD_NS\`.\`$T\` p ON l.id=p.id WHERE p.id IS NULL),
      (SELECT COUNT(*) FROM \`$PROD_NS\`.\`$T\` p
         LEFT JOIN \`$DB_NAME\`.\`$T\` l ON p.id=l.id WHERE l.id IS NULL);")
  printf '%-20s %10s %10s %10s %10s\n' "$T" "$lab" "$prod" "$labonly" "$prodonly"
done

echo
echo "lab_only = rows present in the recovered lab but missing from prod (export candidates)."
echo "Use: bin/forensics/lab-export.sh <table>  to emit INSERT IGNORE for them."
