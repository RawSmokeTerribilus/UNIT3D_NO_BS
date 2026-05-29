#!/bin/bash
# Emit a JSON snapshot of the prod DB table topology, plus a lab overlay when the
# lab is up. Read-only everywhere: prod via the backupbot account (never DDL/DML),
# lab via root. Row counts are the cheap information_schema ESTIMATE (no COUNT(*)).
# Consumed by the dashboard's GET /api/topology.
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/_common.sh"

# Prod: per-table size/rows/engine/fragmentation as a JSON array (one cheap query).
PROD_JSON="$(prod_mysql -N -s -e "
  SELECT IFNULL(JSON_ARRAYAGG(JSON_OBJECT(
    'name',   table_name,
    'rows',   IFNULL(table_rows,0),
    'data',   IFNULL(data_length,0),
    'idx',    IFNULL(index_length,0),
    'free',   IFNULL(data_free,0),
    'engine', IFNULL(engine,'?')
  )), JSON_ARRAY())
  FROM information_schema.tables
  WHERE table_schema='${DB_NAME}' AND table_type='BASE TABLE';" 2>/dev/null)"
[ -z "$PROD_JSON" ] && PROD_JSON='[]'

# Tables carrying an `id` column = the ones Diff/Export can key on.
IDS_JSON="$(prod_mysql -N -s -e "
  SELECT IFNULL(JSON_ARRAYAGG(table_name), JSON_ARRAY())
  FROM information_schema.columns
  WHERE table_schema='${DB_NAME}' AND column_name='id';" 2>/dev/null)"
[ -z "$IDS_JSON" ] && IDS_JSON='[]'

# Lab overlay: estimated rows per table, only if the lab is awake.
LAB_JSON='null'
LAB_UP=false
if docker ps --format '{{.Names}}' | grep -Fxq "$LAB_DB_CONTAINER"; then
  LAB_UP=true
  LAB_JSON="$(lab_mysql -N -s -e "
    SELECT IFNULL(JSON_OBJECTAGG(table_name, IFNULL(table_rows,0)), JSON_OBJECT())
    FROM information_schema.tables
    WHERE table_schema='${DB_NAME}' AND table_type='BASE TABLE';" 2>/dev/null)"
  [ -z "$LAB_JSON" ] && LAB_JSON='{}'
fi

printf '{"prod":%s,"ids":%s,"lab":%s,"lab_up":%s,"ts":"%s"}\n' \
  "$PROD_JSON" "$IDS_JSON" "$LAB_JSON" "$LAB_UP" "$(date +%FT%T%:z)"
