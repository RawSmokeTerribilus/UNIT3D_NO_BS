#!/bin/bash
# Tear down the bench AND wipe the lab datadir for a clean run. Destructive,
# but only touches the lab datadir (mysql_lab) — never prod data.
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/_common.sh"

LAB_DATADIR="$PROJECT_ROOT/.docker/data/mysql_lab"

if [ "${1:-}" != "--yes" ]; then
  echo "This will stop the bench and DELETE the lab datadir:"
  echo "  $LAB_DATADIR"
  echo "Prod data is NOT touched. Re-run with --yes to confirm."
  exit 1
fi

log "Stopping bench"
compose down || true
log "Wiping lab datadir $LAB_DATADIR (needs sudo; owned by uid 27)"
sudo rm -rf "$LAB_DATADIR"
log "Lab datadir wiped. Next lab-up.sh starts from a fresh MySQL init."
