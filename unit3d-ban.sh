#!/bin/bash
# unit3d-ban.sh v2 — bans por login fallido de UNIT3D, aplicados vía CrowdSec.
#
# Cron de root cada 5 min (`sudo crontab -l -u root`). Lee `failed_login_attempts`
# (la IP es la real: Laravel la toma de CF-Connecting-IP) y, si una IP pasa de un
# umbral, añade una decisión `ban` de CrowdSec CON CADUCIDAD. Así el bloqueo llega:
#   - a la web, por crowdsec-nginx-bans (nginx ve la IP real; firewalld NO, porque
#     la web entra por el túnel de Cloudflare y para el host todo es cloudflared);
#   - al host, por crowdsec-firewall-bouncer.
# CrowdSec las hace caducar solas: aquí no hay estado propio ni bans eternos.
#
# NO VOLVER A LA v1 (>3 fallos → drop PERMANENTE en firewalld, sin log ni
# whitelist). Estuvo corriendo del 29-04 al 26-09-2026 por un `git reset` y
# baneó de por vida a ~70 socios que se equivocaron de contraseña, sin proteger
# la web. El informe diario de CrowdSec comprueba el latido de este script
# ($HEARTBEAT, versión incluida): si falta, es viejo o la versión no cuadra,
# el informe lo marca en rojo.
#
# Prueba sin aplicar nada:  sudo DRY_RUN=1 ./unit3d-ban.sh
# Nota del vault: Knowledge/Infrastructure/UNIT3D/unit3d-ban-sh-roto-2026-09-26.md

set -uo pipefail

VERSION=2
BASE_DIR="/home/rawserver/UNIT3D_Docker"
LOG_FILE="${BASE_DIR}/backups/unit3d-ban.log"
WHITELIST="${BASE_DIR}/unit3d-ban.whitelist"
HEARTBEAT_DIR="/var/lib/unit3d-ban"
HEARTBEAT="${HEARTBEAT_DIR}/heartbeat.json"
APP_CONTAINER="unit3d-app"
DRY_RUN="${DRY_RUN:-0}"

# Umbrales. Un socio que se equivoca de contraseña hace 3-12 intentos en pocos
# minutos contra SU cuenta; el throttle de Laravel ya le frena. Estos buscan
# martilleo sostenido o probar muchas cuentas desde una misma IP.
T1_FAILS_10M=10;  T1_DURATION="1h"
T2_FAILS_1H=15;   T2_ACCOUNTS_1H=5;  T2_DURATION="24h"
T3_FAILS_24H=50;  T3_DURATION="720h"

log() { echo "[$(date '+%F %T')] $*" >> "$LOG_FILE"; }
fail() {
    log "ERROR v${VERSION} $*"
    logger -t unit3d-ban -p user.err "$*" 2>/dev/null
    exit 1
}

[[ "$DRY_RUN" == "1" ]] && log() { echo "[DRY_RUN] $*"; }

[[ $EUID -eq 0 ]] || fail "hay que ejecutarlo como root (cscli)"
command -v cscli >/dev/null || fail "cscli no encontrado"
command -v jq >/dev/null || fail "jq no encontrado"

# Una fila por IP con fallos en las últimas 24 h:
# ROW <tab> ip <tab> fallos_10m <tab> fallos_1h <tab> fallos_24h <tab> cuentas_1h
DATA=$(docker exec "$APP_CONTAINER" php artisan tinker --execute='
$t10 = now()->subMinutes(10); $t60 = now()->subHour();
foreach (DB::table("failed_login_attempts")->where("created_at", ">=", now()->subDay())
    ->selectRaw("ip_address, SUM(created_at >= ?) c10, SUM(created_at >= ?) c60, COUNT(*) c1440, COUNT(DISTINCT CASE WHEN created_at >= ? THEN username END) u60", [$t10, $t60, $t60])
    ->groupBy("ip_address")->get() as $r) {
    echo "ROW\t{$r->ip_address}\t{$r->c10}\t{$r->c60}\t{$r->c1440}\t{$r->u60}\n";
}
echo "END\n";' 2>&1) || fail "docker exec contra ${APP_CONTAINER} falló"
grep -q '^END' <<<"$DATA" || fail "la consulta no terminó: $(head -c 300 <<<"$DATA" | tr '\n' ' ')"

WL=$(grep -vE '^\s*(#|$)' "$WHITELIST" 2>/dev/null | awk '{print $1}')

is_protected() {
    local ip="$1"
    # loopback, RFC1918 y la tailnet (100.64.0.0/10)
    [[ "$ip" =~ ^(127\.|10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.|100\.(6[4-9]|[7-9][0-9]|1[01][0-9]|12[0-7])\.) ]] && return 0
    [[ "$ip" == "::1" || "$ip" =~ ^(fe80|fc|fd) ]] && return 0
    grep -qxF -- "$ip" <<<"$WL" && return 0
    return 1
}

# Nivel más alto de las decisiones unit3d-ban aún vivas para esa IP (0 si ninguna).
active_tier() {
    cscli decisions list -i "$1" -o json 2>/dev/null \
        | jq -r '[.[]?.decisions[]? | .scenario | select(startswith("unit3d-ban tier")) | ltrimstr("unit3d-ban tier") | .[0:1] | tonumber] | max // 0' 2>/dev/null \
        || echo 0
}

EVALUATED=0
ADDED=0
while IFS=$'\t' read -r tag ip c10 c60 c1440 u60; do
    [[ "$tag" == "ROW" ]] || continue
    [[ "$ip" =~ ^[0-9a-fA-F:.]+$ ]] || { log "SKIP ip no válida: ${ip}"; continue; }
    EVALUATED=$((EVALUATED + 1))
    is_protected "$ip" && continue

    tier=0; dur=""
    if (( c1440 >= T3_FAILS_24H )); then
        tier=3; dur="$T3_DURATION"
    elif (( c60 >= T2_FAILS_1H || u60 >= T2_ACCOUNTS_1H )); then
        tier=2; dur="$T2_DURATION"
    elif (( c10 >= T1_FAILS_10M )); then
        tier=1; dur="$T1_DURATION"
    fi
    (( tier == 0 )) && continue

    current=$(active_tier "$ip")
    (( current >= tier )) && continue

    reason="unit3d-ban tier${tier} fallos10m=${c10} fallos1h=${c60} fallos24h=${c1440} cuentas1h=${u60}"
    if [[ "$DRY_RUN" == "1" ]]; then
        log "BAN (simulado) tier=${tier} ip=${ip} dur=${dur} ${reason}"
    elif cscli decisions add --ip "$ip" --duration "$dur" --type ban --reason "$reason" >/dev/null 2>&1; then
        log "BAN tier=${tier} ip=${ip} dur=${dur} fallos10m=${c10} fallos1h=${c60} fallos24h=${c1440} cuentas1h=${u60}"
    else
        fail "cscli decisions add falló para ${ip}"
    fi
    ADDED=$((ADDED + 1))
done <<<"$DATA"

if [[ "$DRY_RUN" == "1" ]]; then
    echo "[DRY_RUN] evaluadas=${EVALUATED} bans=${ADDED}"
    exit 0
fi

mkdir -p "$HEARTBEAT_DIR"
printf '{"version":%d,"last_run":"%s","last_run_epoch":%d,"ips_evaluated":%d,"bans_added":%d}\n' \
    "$VERSION" "$(date -Is)" "$(date +%s)" "$EVALUATED" "$ADDED" > "${HEARTBEAT}.tmp" \
    && mv "${HEARTBEAT}.tmp" "$HEARTBEAT"
exit 0
