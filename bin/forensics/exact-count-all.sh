#!/bin/bash
# Exact COUNT(*) for EVERY prod table in one shot (+ lab when up). Read-only.
# Heavier than the information_schema estimate (full scans on big tables) so it's
# an on-demand job, not the topology poll. Writes {table:{prod,lab}} JSON to $1.
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/_common.sh"

OUT="${1:?usage: exact-count-all.sh <out.json>}"

TABLES="$(prod_mysql -N -s -e "
  SELECT table_name FROM information_schema.tables
  WHERE table_schema='${DB_NAME}' AND table_type='BASE TABLE' ORDER BY table_name;")"
if [ -z "$TABLES" ]; then echo '{}' > "$OUT"; log "no tables"; exit 0; fi

# One UNION ALL query: 'table' , COUNT(*). Table names come from information_schema
# (safe identifier charset), wrapped in backticks.
SQL=""
while IFS= read -r t; do
  [ -z "$t" ] && continue
  SQL="${SQL}${SQL:+ UNION ALL }SELECT '${t}' AS t, COUNT(*) AS c FROM \`${DB_NAME}\`.\`${t}\`"
done <<< "$TABLES"

NT="$(printf '%s\n' "$TABLES" | grep -c .)"
log "Exact COUNT(*) over ${NT} prod tables (full scans — can take a moment)…"
PROD_ROWS="$(prod_mysql -N -s -e "$SQL")"

LAB_ROWS=""
if docker ps --format '{{.Names}}' | grep -Fxq "$LAB_DB_CONTAINER"; then
  log "Counting lab…"
  LAB_ROWS="$(lab_mysql -N -s -e "$SQL" 2>/dev/null || true)"
fi

PROD_ROWS="$PROD_ROWS" LAB_ROWS="$LAB_ROWS" OUT="$OUT" python3 - <<'PY'
import os, json
def parse(blob):
    d = {}
    for line in blob.splitlines():
        if "\t" in line:
            name, cnt = line.split("\t", 1)
            try: d[name] = int(cnt)
            except ValueError: pass
    return d
prod = parse(os.environ.get("PROD_ROWS", ""))
lab = parse(os.environ.get("LAB_ROWS", ""))
out = {t: {"prod": prod[t], "lab": lab.get(t)} for t in prod}
with open(os.environ["OUT"], "w") as f:
    json.dump(out, f)
PY
log "Exact counts written → $(basename "$OUT")"
