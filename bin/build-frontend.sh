#!/bin/bash
# Frontend build without a service window.
#
# `npm run build` empties public/build in place and rebuilds into it: for a
# few seconds there is no manifest.json and every page render 500s with
# "Vite manifest not found" (it bit a real user on 2026-08-20). This script
# builds into public/build.new instead and swaps directories with mv — the
# gap shrinks to milliseconds — verifying the result before purging the old
# build, and rolling back if anything is off.
#
# public/ belongs to the container user (82:82), so the staging directory is
# created and handed over via sudo, which is passwordless on this box.
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

read_env() {
  local value
  value="$(grep -E "^$1=" .env 2>/dev/null | head -1 | cut -d'=' -f2- | tr -d '"' | tr -d "'")"
  printf '%s' "${value:-$2}"
}

HTTP_PORT="$(read_env HTTP_PORT 8008)"
WEB_OWNER="$(stat -c '%u:%g' public)"
NEW="public/build.new"
STAMP="$(date +%s)"

[ -d node_modules ] || { echo "ERROR: node_modules ausente en $PROJECT_ROOT" >&2; exit 1; }

sudo rm -rf "$NEW"
sudo mkdir -p "$NEW"
sudo chown "$(id -u):$(id -g)" "$NEW"

npx vite build --outDir "$NEW"

[ -f "$NEW/manifest.json" ] || { echo "ERROR: el build no produjo manifest.json" >&2; sudo rm -rf "$NEW"; exit 1; }

sudo chown -R "$WEB_OWNER" "$NEW"
sudo mv public/build "public/build.old.$STAMP"
sudo mv "$NEW" public/build

STATUS="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:${HTTP_PORT}/build/manifest.json" || echo 000)"

if [ "$STATUS" != "200" ]; then
  echo "ERROR: manifest responde $STATUS tras el swap — vuelta atrás" >&2
  sudo mv public/build "public/build.failed.$STAMP"
  sudo mv "public/build.old.$STAMP" public/build
  exit 1
fi

sudo rm -rf "public/build.old.$STAMP"
echo "OK: build servido (manifest 200), build anterior purgado"
