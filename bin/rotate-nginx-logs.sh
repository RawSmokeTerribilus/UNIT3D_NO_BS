#!/usr/bin/env bash
#
# Rotate the nginx access/error logs in storage/logs-nginx.
#
# Background: nginx logs live in a dedicated storage/logs-nginx bind mount
# (moved out of storage/logs so the staff-panel log viewer stops choking on
# them). This script is the ONLY rotation for them. Run it DAILY from root's
# crontab:
#
#   40 3 * * * /path/to/repo/bin/rotate-nginx-logs.sh >> /path/to/repo/backups/cron_logrotate.log 2>&1
#
# Why not /etc/logrotate.d: the system logrotate runs SELinux-confined
# (logrotate_t) and cannot read anything under /home (user_home_t). It fails
# with "Permission denied" even as root, while its own state file still says
# the logs were rotated. A job in root's crontab runs unconfined, so this
# works. That is how these logs went unrotated from 2026-08-29 to 2026-09-15.
#
# Why the config and state live in .docker/logrotate and not under storage/:
# the app container's entrypoint runs `chown -R www-data` + `chmod -R 775` on
# storage/ at every start, and logrotate silently ignores a config file that
# is not owned by root or is group-writable. A config under storage/ stops
# working after the next container restart.
#
# storage/logs-nginx itself is 775 and owned by uid 82 (the container's
# www-data, which has no name on the host). `su root root` makes logrotate
# accept that parent directory instead of fighting the entrypoint with chmod.
#
# copytruncate: nginx keeps writing to the same fd after the file is truncated
# in place, so no reopen and no docker-socket dependency. CrowdSec and promtail
# follow the truncation.
#
# Retention: 30 days, the same as audits and the MySQL binlog. The announce log
# is the only history of peer IPs (the peers table keeps just the current one).
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
STATE_DIR="$REPO_ROOT/.docker/logrotate"
CONF="$STATE_DIR/nginx.conf"
STATE="$STATE_DIR/nginx.state"

install -d -o root -g root -m 0755 "$STATE_DIR"

# Written fresh every run as root:root 0644: logrotate ignores a config that is
# not owned by root or is writable by group/other.
rm -f "$CONF"
umask 022
cat > "$CONF" <<EOF
$LOG_DIR/*.log {
    su root root
    daily
    rotate 30
    dateext
    dateformat -%Y%m%d
    compress
    missingok
    notifempty
    copytruncate
}
EOF

exec /usr/sbin/logrotate -s "$STATE" "$CONF" "$@"
