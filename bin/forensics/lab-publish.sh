#!/bin/bash
# Build and publish the forensics toolbox image to a registry (Docker Hub by default).
# Pushes a pinned version tag AND :latest. Pin your compose default (FORENSICS_IMAGE)
# to the version tag so a later :latest push can't silently clobber a running bench.
#
# Usage: lab-publish.sh            # publishes FORENSICS_IMAGE from forensics.env
#        lab-publish.sh <image:tag>
#
# Requires: docker login to the target registry.
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/_common.sh"

IMAGE="${1:-$FORENSICS_IMAGE}"
[ -n "$IMAGE" ] || die "no image given and FORENSICS_IMAGE unset"
case "$IMAGE" in *:*) ;; *) die "image must include a version tag, e.g. repo/name:v0.1.0" ;; esac
REPO="${IMAGE%:*}"

log "Building $IMAGE"
docker build -t "$IMAGE" "$FORENSICS_DIR"
docker tag "$IMAGE" "$REPO:latest"

log "Pushing $IMAGE and $REPO:latest"
docker push "$IMAGE"
docker push "$REPO:latest"
log "Published. Pin FORENSICS_IMAGE=$IMAGE in forensics.env so the bench uses the exact tag."
