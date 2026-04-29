#!/bin/bash
#
# UNIT3D unban companion — daily cleanup
# - Purges failed_login_attempts rows > 30 days old (privacy + perf)
# - Logs summary of active bans per tier
#

set -uo pipefail

BASE_DIR="/home/rawserver/UNIT3D_Docker"
LOG_FILE="${BASE_DIR}/backups/unit3d-ban.log"
STATE_FILE="${BASE_DIR}/unit3d-ban.state"

mkdir -p "$(dirname "$LOG_FILE")"
log() { echo "[$(date '+%F %T')] $*" >> "$LOG_FILE"; }

PURGED=$(docker exec unit3d-app php artisan tinker --execute="
\$cutoff = now()->subDays(30);
echo DB::table('failed_login_attempts')->where('created_at','<',\$cutoff)->delete();
" 2>/dev/null | grep -oE '^[0-9]+$' | tail -1)

log "CLEANUP failed_login_attempts purged=${PURGED:-0} rows_older_than_30d"

if [[ -s "$STATE_FILE" ]]; then
    T1=$(awk '$2==1' "$STATE_FILE" | wc -l)
    T2=$(awk '$2==2' "$STATE_FILE" | wc -l)
    T3=$(awk '$2==3' "$STATE_FILE" | wc -l)
    log "STATUS active_bans tier1=$T1 tier2=$T2 tier3=$T3"
fi

exit 0
