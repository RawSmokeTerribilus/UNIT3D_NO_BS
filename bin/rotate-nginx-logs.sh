#!/usr/bin/env bash
#
# Rotate the relocated nginx access/error logs (storage/logs-nginx).
#
# Background: nginx logs were moved out of storage/logs into a dedicated
# storage/logs-nginx bind mount so the staff-panel Laravel log viewer no longer
# OOM/500s on the multi-hundred-MB announce-access.log. Relocation cropped them
# out of the viewer path but added no rotation, so they grew unbounded. This
# script adds that rotation.
#
# Run DAILY from cron. The logrotate config rotates weekly by default, but a
# `maxsize` guard lets a traffic spike trigger an earlier rotation — which only
# works if logrotate is *invoked* more often than weekly. Hence daily cron.
#
# Portable / public-FOSS: no hardcoded paths (resolves repo root from $0). The
# log dir and everything under storage/ is owned by uid/gid 82 (the container's
# www-data), which has no host passwd entry — so we can't drop to it via `su` or
# `sudo -u`. Instead this runs as root (re-execs via sudo) and the log dir is
# kept mode 0750 (no group/world write) so logrotate's root-only "insecure
# parent directory" check is satisfied without an `su` directive.
#
# copytruncate: nginx keeps writing to the same fd after the file is truncated
# in place — no SIGUSR1/reopen, so no docker-socket dependency.
#
# Manual use:
#   bin/rotate-nginx-logs.sh         # normal run (logrotate decides)
#   bin/rotate-nginx-logs.sh -f      # force a rotation now
#   bin/rotate-nginx-logs.sh -d      # debug / dry-run (no changes)
#
set -euo pipefail

[ "$(id -u)" -eq 0 ] || exec sudo "$0" "$@"

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LOG_DIR="$REPO_ROOT/storage/logs-nginx"
STATE_DIR="$REPO_ROOT/storage/logrotate"
CONF="$STATE_DIR/nginx.conf"
STATE="$STATE_DIR/nginx.state"

mkdir -p "$STATE_DIR"

# logrotate (run as root) refuses any log whose parent dir is writable by group
# or other. Docker creates this dir 0755 (fine), but a umask-002 host can leave
# it 0775 — normalize so the rotation isn't skipped. nginx writes as the owner
# (uid 82), so dropping group/other write does not affect logging.
[ -d "$LOG_DIR" ] && chmod g-w,o-w "$LOG_DIR"

# Generated each run so the resolved path stays correct.
# copytruncate   -> rotate without needing nginx to reopen its log fd.
# weekly+maxsize -> weekly normally, or sooner if a spike pushes past 200M
#                   (only effective because cron invokes this daily).
cat > "$CONF" <<EOF
$LOG_DIR/*.log {
    weekly
    maxsize 200M
    rotate 4
    compress
    delaycompress
    missingok
    notifempty
    copytruncate
}
EOF

exec /usr/sbin/logrotate -s "$STATE" "$CONF" "$@"
