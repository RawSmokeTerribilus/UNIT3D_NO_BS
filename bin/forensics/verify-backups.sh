#!/bin/bash
# Read-only: verify every dump against its .sha256 sidecar. Reports pass/fail per
# file and a summary. Touches nothing but the backups dir.
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/_common.sh"

DIR="$BACKUPS_DIR_ABS/db_regular"
[ -d "$DIR" ] || die "no backups dir: $DIR"

ok=0; bad=0; missing=0
shopt -s nullglob
for dump in "$DIR"/db_unit3d_*.sql.gz; do
  base="$(basename "$dump")"
  if [ ! -f "$dump.sha256" ]; then
    log "MISSING sha256 — $base"
    missing=$((missing+1))
    continue
  fi
  if ( cd "$DIR" && sha256sum -c "$base.sha256" >/dev/null 2>&1 ); then
    log "OK       $base"
    ok=$((ok+1))
  else
    log "FAILED   $base — checksum mismatch"
    bad=$((bad+1))
  fi
done

log "Verify summary: ${ok} OK, ${bad} FAILED, ${missing} missing-sidecar"
[ "$bad" -eq 0 ] || exit 2
