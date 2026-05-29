#!/bin/bash
# Prod table maintenance: check (read-only), analyze (refresh stats/estimates),
# optimize (defrag/rebuild — reclaims data_free). analyze/optimize WRITE prod and
# are password-gated (PROD_WRITE_PW env); optimize also runs backup-first and warns
# (InnoDB OPTIMIZE rebuilds the table and can lock it under live traffic). check
# uses the read-only backup account.
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/_common.sh"

OP="${1:?usage: prod-maint.sh <check|analyze|optimize> <table> [table ...]}"
shift
[ "$#" -ge 1 ] || die "no tables given"
for t in "$@"; do
  case "$t" in *[!A-Za-z0-9_]*) die "bad table name: $t";; esac
done
LIST="$(printf '`%s`,' "$@" | sed 's/,$//')"

case "$OP" in
  check)
    log "CHECK TABLE on prod (read-only): $*"
    prod_mysql "$DB_NAME" -e "CHECK TABLE $LIST;" 2>&1 | sed 's/^/    /'
    ;;
  analyze|optimize)
    PROD_WRITE_USER="${PROD_WRITE_USER:-${DB_USERNAME:-unit3d}}"
    : "${PROD_WRITE_PW:?PROD_WRITE_PW env required (typed prod DB password)}"
    pw_mysql() {
      docker exec -i -e MYSQL_PWD="$PROD_WRITE_PW" "$PROD_DB_CONTAINER" \
        mysql -h127.0.0.1 --protocol=TCP -u"$PROD_WRITE_USER" "$@"
    }
    log "Verifying prod credentials for ${PROD_WRITE_USER}…"
    echo "SELECT 1;" | pw_mysql >/dev/null 2>&1 || die "credential check failed — wrong password or no access (nothing changed)"
    if [ "$OP" = optimize ]; then
      log "Backup-first before OPTIMIZE (table rebuild)…"
      "$PROJECT_ROOT/bin/db-backup-regular.sh" >&2 || die "backup-first FAILED — aborting"
      log "OPTIMIZE TABLE $* — rebuild; may lock under live traffic…"
      pw_mysql "$DB_NAME" -e "OPTIMIZE TABLE $LIST;" 2>&1 | sed 's/^/    /'
    else
      log "ANALYZE TABLE $* — refresh stats so the row estimates stop lying…"
      pw_mysql "$DB_NAME" -e "ANALYZE TABLE $LIST;" 2>&1 | sed 's/^/    /'
    fi
    ;;
  *) die "unknown op: $OP (use check|analyze|optimize)" ;;
esac
log "Maintenance done."
