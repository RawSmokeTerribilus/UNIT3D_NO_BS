#!/bin/bash
# Read-only: combine one or more export .sql files into a single ordered apply-kit
# and print the exact terminal command + checklist to apply it to prod by hand.
# Never writes prod. The manual fallback if the in-dash apply button fails.
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/_common.sh"

[ "$#" -ge 1 ] || die "usage: build-apply-kit.sh <export1.sql> [export2.sql ...]"

EXPORT_DIR="$PROJECT_ROOT/backups/forensics-export"
KIT="$EXPORT_DIR/apply-kit-$(date +%Y%m%d-%H%M%S).sql"
PROD_WRITE_USER="${PROD_WRITE_USER:-${DB_USERNAME:-unit3d}}"

{
  echo "-- forensics apply-kit  generated $(date +'%F %T')"
  echo "-- review before applying. additive INSERT IGNORE only."
  echo "SET autocommit=0; START TRANSACTION;"
} > "$KIT"

for f in "$@"; do
  case "$f" in */*) die "export name must be a bare filename: $f";; esac
  src="$EXPORT_DIR/$f"
  [ -f "$src" ] || die "export not found: $f"
  printf -- '\n-- ===== %s =====\n' "$f" >> "$KIT"
  cat "$src" >> "$KIT"
done

echo "COMMIT;" >> "$KIT"

log "Apply-kit written: $(basename "$KIT") ($(wc -l < "$KIT") lines)"
cat >&2 <<EOF

  ── apply this kit to prod by hand ──────────────────────────────
  1. it is additive (INSERT IGNORE) — it will not overwrite rows.
  2. take a fresh prod snapshot first:
       bin/db-backup-regular.sh
  3. apply (you'll be prompted for the ${PROD_WRITE_USER} password):
       docker exec -i -e MYSQL_PWD='<PROD_DB_PASSWORD>' ${PROD_DB_CONTAINER} \\
         mysql -h127.0.0.1 --protocol=TCP -u${PROD_WRITE_USER} ${DB_NAME} \\
         < backups/forensics-export/$(basename "$KIT")
  4. confirm with a diff afterwards.
  ────────────────────────────────────────────────────────────────
EOF
