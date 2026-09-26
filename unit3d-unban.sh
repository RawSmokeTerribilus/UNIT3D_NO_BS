#!/bin/bash
#
# UNIT3D unban companion — limpieza diaria (cron root `30 4 * * *`)
# - Purga filas de failed_login_attempts de más de 30 días (privacidad + rendimiento).
# - Registra cuántos bans de unit3d-ban.sh v2 siguen vivos en CrowdSec, por nivel.
#
# No desbanea nada: desde la v2 los bans son decisiones de CrowdSec con
# caducidad y CrowdSec las retira solo. Ver unit3d-ban.sh.
#

set -uo pipefail

BASE_DIR="/home/rawserver/UNIT3D_Docker"
LOG_FILE="${BASE_DIR}/backups/unit3d-ban.log"

mkdir -p "$(dirname "$LOG_FILE")"
log() { echo "[$(date '+%F %T')] $*" >> "$LOG_FILE"; }

PURGED=$(docker exec unit3d-app php artisan tinker --execute="
\$cutoff = now()->subDays(30);
echo DB::table('failed_login_attempts')->where('created_at','<',\$cutoff)->delete();
" 2>/dev/null | grep -oE '^[0-9]+$' | tail -1)

log "CLEANUP failed_login_attempts purged=${PURGED:-0} rows_older_than_30d"

TIERS=$(cscli decisions list --origin cscli -o json 2>/dev/null \
    | jq -r '[.[]?.decisions[]? | .scenario | select(startswith("unit3d-ban tier")) | ltrimstr("unit3d-ban tier") | .[0:1]] | group_by(.) | map("tier\(.[0])=\(length)") | join(" ")' 2>/dev/null)
log "STATUS active_bans ${TIERS:-ninguno}"

exit 0
