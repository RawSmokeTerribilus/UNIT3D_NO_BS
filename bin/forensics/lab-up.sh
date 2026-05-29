#!/bin/bash
# Build (if needed) and start the forensics bench, then wait for lab-db healthy.
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/_common.sh"

log "Starting forensics bench (project=$COMPOSE_PROJECT)"
# Use the persisted/published toolbox image. If it's not present locally, try a
# registry pull; if that fails (e.g. offline or unpublished), build it once.
# Rebuild deliberately with bin/forensics/lab-build.sh.
if ! docker image inspect "${FORENSICS_IMAGE}" >/dev/null 2>&1; then
  log "Toolbox image ${FORENSICS_IMAGE} not local — trying registry pull"
  if ! docker pull "${FORENSICS_IMAGE}" >/dev/null 2>&1; then
    log "Pull failed — building locally"
    compose build forensics
  fi
fi
compose up -d

log "Waiting for $LAB_DB_CONTAINER to become healthy..."
for i in $(seq 1 60); do
  status="$(docker inspect -f '{{.State.Health.Status}}' "$LAB_DB_CONTAINER" 2>/dev/null || echo missing)"
  case "$status" in
    healthy) log "lab-db healthy."; exit 0 ;;
    missing) die "$LAB_DB_CONTAINER not found — did compose start it?" ;;
  esac
  sleep 2
done
die "lab-db did not become healthy in time (last status: ${status:-unknown})"
