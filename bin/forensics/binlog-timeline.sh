#!/bin/bash
# Read-only, idempotent scan of prod binlogs into a JSON damage timeline. Runs the
# version-matched mysqlbinlog inside the toolbox (RO /prod/mysql mount) and pipes
# through /scripts/binlog_parse.py. Default window = from the latest dump's binlog
# coordinate (the replay window) to HEAD; pass --all to scan every binlog.
# Writes the JSON to the path in $1. Never touches prod or lab data.
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/_common.sh"

OUT="${1:?usage: binlog-timeline.sh <out.json> [--all]}"
ALL="${2:-}"
require_lab_up   # toolbox carries mysqlbinlog + the RO prod binlog mount

START_FILE=""
START_POS=4
if [ "$ALL" != "--all" ]; then
  SIDE="$(ls -1t "$BACKUPS_DIR_ABS"/db_regular/*.binlogpos 2>/dev/null | head -1 || true)"
  if [ -n "$SIDE" ]; then
    START_FILE="$(grep -E '^SOURCE_LOG_FILE=' "$SIDE" | cut -d= -f2 | tr -d '[:space:]')"
    START_POS="$(grep -E '^SOURCE_LOG_POS=' "$SIDE" | cut -d= -f2 | tr -d '[:space:]')"
    log "Window from latest dump coordinate: ${START_FILE}:${START_POS}"
  fi
else
  log "Scanning ALL binlogs"
fi

log "Running mysqlbinlog scan in toolbox (this can take a moment on a busy window)…"
JSON="$(fx bash -c '
  set -e
  cd /prod/mysql
  start="'"$START_FILE"'"
  files=$(ls -1 binlog.[0-9]* 2>/dev/null | sort | awk -v s="$start" "s==\"\" || \$0 >= s")
  if [ -z "$files" ]; then echo "{\"events\":[],\"count\":0,\"truncated\":false}"; exit 0; fi
  mysqlbinlog --base64-output=DECODE-ROWS -v --start-position='"$START_POS"' $files 2>/dev/null \
    | python3 /scripts/binlog_parse.py
')"

printf '%s\n' "$JSON" > "$OUT"
CNT="$(printf '%s' "$JSON" | python3 -c 'import sys,json;print(json.load(sys.stdin).get("count",0))' 2>/dev/null || echo '?')"
log "Timeline scan complete: ${CNT} notable events → $(basename "$OUT")"
