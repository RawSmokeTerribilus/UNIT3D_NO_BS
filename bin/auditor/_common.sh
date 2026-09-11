#!/bin/bash
# Config compartida del panel de consultas. Lo sourcean los scripts de bin/auditor.
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
AUDITOR_DIR="$PROJECT_ROOT/auditor"
COMPOSE_FILE="$PROJECT_ROOT/docker-compose.auditor.yml"

die() { echo "error: $*" >&2; exit 1; }

# 1) valores por defecto y config local
set -a
if [ -f "$AUDITOR_DIR/auditor.env" ]; then
  source "$AUDITOR_DIR/auditor.env"
elif [ -f "$AUDITOR_DIR/auditor.env.example" ]; then
  echo "aviso: no hay auditor/auditor.env, usando auditor.env.example" >&2
  source "$AUDITOR_DIR/auditor.env.example"
else
  die "falta auditor/auditor.env.example"
fi
set +a

# 2) la contraseña de auditro vive en ~/Hardening/.env y NUNCA en este repo.
#    Se lee en línea, no se imprime, no pasa por argv.
HARDENING_ENV="${HARDENING_ENV:-$HOME/Hardening/.env}"
if [ -z "${AUDITOR_DB_PASS:-}" ]; then
  [ -f "$HARDENING_ENV" ] || die "falta $HARDENING_ENV y AUDITOR_DB_PASS no está en el entorno"
  AUDITOR_DB_PASS="$(
    grep -E '^UNIT3D_PROD_DB_AUDITRO_PASS=' "$HARDENING_ENV" | head -1 |
      cut -d= -f2- | sed -E 's/^"(.*)"$/\1/; s/^'"'"'(.*)'"'"'$/\1/'
  )"
  export AUDITOR_DB_PASS
fi
[ -n "${AUDITOR_DB_PASS:-}" ] || die "UNIT3D_PROD_DB_AUDITRO_PASS vacía o ausente en $HARDENING_ENV"

dc() { docker compose -f "$COMPOSE_FILE" "$@"; }
