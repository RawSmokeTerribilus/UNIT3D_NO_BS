#!/bin/bash
# Stop the forensics bench. Keeps the lab datadir (use lab-reset.sh to wipe).
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/_common.sh"

log "Stopping forensics bench (datadir preserved)"
compose down
log "Done."
