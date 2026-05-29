#!/bin/bash
# Exact COUNT(*) for one table on prod (+ lab when up). On-demand only — the
# topology panel uses cheap estimates by default; this is the explicit "give me
# the real number" path. Read-only. Consumed by GET /api/table-count.
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/_common.sh"

T="${1:?usage: table-count.sh <table>}"
case "$T" in *[!A-Za-z0-9_]*) die "bad table name: $T";; esac

PROD="$(prod_mysql -N -s -e "SELECT COUNT(*) FROM \`${DB_NAME}\`.\`${T}\`;" 2>/dev/null || true)"
[ -z "$PROD" ] && PROD=null

LAB=null
if docker ps --format '{{.Names}}' | grep -Fxq "$LAB_DB_CONTAINER"; then
  LAB="$(lab_mysql -N -s -e "SELECT COUNT(*) FROM \`${DB_NAME}\`.\`${T}\`;" 2>/dev/null || true)"
  [ -z "$LAB" ] && LAB=null
fi

printf '{"table":"%s","prod":%s,"lab":%s}\n' "$T" "$PROD" "$LAB"
