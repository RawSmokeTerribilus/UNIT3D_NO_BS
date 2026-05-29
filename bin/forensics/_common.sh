#!/bin/bash
# Shared helpers for the UNIT3D forensics bench. Sourced by the lab-*.sh scripts.
# Runs on the host; orchestrates the prod + lab containers via `docker exec`.
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
ENV_FILE="$PROJECT_ROOT/.env"
COMPOSE_FILE="$PROJECT_ROOT/docker-compose.forensics.yml"
FORENSICS_DIR="$PROJECT_ROOT/forensics"

# Load bench config (local override first, else the committed example). Exported
# so docker-compose variable substitution sees the same values the scripts use.
set -a
if [ -f "$FORENSICS_DIR/forensics.env" ]; then
  # shellcheck disable=SC1091
  source "$FORENSICS_DIR/forensics.env"
elif [ -f "$FORENSICS_DIR/forensics.env.example" ]; then
  # shellcheck disable=SC1091
  source "$FORENSICS_DIR/forensics.env.example"
fi
set +a

COMPOSE_PROJECT="${FORENSICS_PROJECT:-unit3d_forensics}"
PROD_DB_CONTAINER="${PROD_DB_CONTAINER:-unit3d-db}"
LAB_DB_CONTAINER="${LAB_DB_CONTAINER:-unit3d-lab-db}"
FX_CONTAINER="${FX_CONTAINER:-unit3d-forensics}"
PROD_DB_USER="${PROD_DB_USER:-backupbot}"   # read-only account
DB_NAME="${DB_NAME:-unit3d}"
PROD_NS="${PROD_NS:-prod_ns}"               # schema in lab holding a prod copy for diffing
# Resolve the backups dir to an absolute path (accept relative or absolute config).
case "${BACKUPS_DIR:-./backups}" in
  /*) BACKUPS_DIR_ABS="${BACKUPS_DIR}" ;;
  *)  BACKUPS_DIR_ABS="$(cd "$PROJECT_ROOT" && cd "${BACKUPS_DIR:-./backups}" 2>/dev/null && pwd || echo "$PROJECT_ROOT/backups")" ;;
esac

read_env() {
  local key="$1" default="${2-}" value
  value="$(grep -E "^${key}=" "$ENV_FILE" 2>/dev/null | head -1 | cut -d'=' -f2- \
    | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//")"
  [ -n "$value" ] && printf '%s' "$value" || printf '%s' "$default"
}

log() { printf '[%s] %s\n' "$(date +'%F %T')" "$*" >&2; }
die() { log "ERROR: $*"; exit 1; }

PROD_DB_PASSWORD="$(read_env DB_PASSWORD '')"
LAB_DB_PASSWORD="${LAB_DB_PASSWORD:-$(read_env LAB_DB_PASSWORD labforensics)}"

compose() {
  docker compose -p "$COMPOSE_PROJECT" -f "$COMPOSE_FILE" --profile forensics "$@"
}

# Read-only access to prod DB (uses the backup account; never DDL/DML).
prod_mysql() {
  docker exec -e MYSQL_PWD="$PROD_DB_PASSWORD" "$PROD_DB_CONTAINER" \
    mysql -h127.0.0.1 --protocol=TCP -u"$PROD_DB_USER" "$@" 2>/dev/null
}
prod_mysqldump() {
  docker exec -e MYSQL_PWD="$PROD_DB_PASSWORD" "$PROD_DB_CONTAINER" \
    mysqldump -h127.0.0.1 --protocol=TCP -u"$PROD_DB_USER" --single-transaction --quick "$@"
}

lab_mysql() {
  docker exec -i -e MYSQL_PWD="$LAB_DB_PASSWORD" "$LAB_DB_CONTAINER" \
    mysql -uroot "$@"
}

# Run a command inside the forensics toolbox container (has mysqlbinlog,
# percona-toolkit, RO prod mounts, and network to lab-db).
fx() { docker exec -e MYSQL_PWD="$LAB_DB_PASSWORD" "$FX_CONTAINER" "$@"; }

require_lab_up() {
  docker ps --format '{{.Names}}' | grep -Fxq "$LAB_DB_CONTAINER" \
    || die "lab not running — start it with bin/forensics/lab-up.sh"
}
