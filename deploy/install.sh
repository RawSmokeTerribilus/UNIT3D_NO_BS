#!/bin/bash
# Install + start the forensics lab dashboard as a systemd --user service.
# No root. Mirrors the existing Jellyfin user-unit pattern (linger already on).
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
UNIT_SRC="$PROJECT_ROOT/deploy/unit3d-forensics-dashboard.service"
UNIT_NAME="unit3d-forensics-dashboard.service"
USER_UNIT_DIR="${XDG_CONFIG_HOME:-$HOME/.config}/systemd/user"
ENV_FILE="$PROJECT_ROOT/forensics/dashboard/dashboard.env"
ENV_EXAMPLE="$PROJECT_ROOT/forensics/dashboard/dashboard.env.example"

if [ ! -f "$ENV_FILE" ]; then
  echo "First run — creating dashboard.env from example."
  cp "$ENV_EXAMPLE" "$ENV_FILE"
fi

mkdir -p "$USER_UNIT_DIR"
# Symlink so edits to the tracked unit take effect after a daemon-reload.
ln -sf "$UNIT_SRC" "$USER_UNIT_DIR/$UNIT_NAME"

systemctl --user daemon-reload
systemctl --user enable --now "$UNIT_NAME"

echo
systemctl --user --no-pager status "$UNIT_NAME" || true
echo
echo "Dashboard: http://$(grep -E '^BIND_ADDR=' "$ENV_FILE" | cut -d= -f2):$(grep -E '^PORT=' "$ENV_FILE" | cut -d= -f2)"
