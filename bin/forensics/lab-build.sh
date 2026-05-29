#!/bin/bash
# Build (or rebuild) the forensics toolbox image. Run this deliberately — the
# resulting image is tagged and persists across bench up/down cycles, so the
# normal lab-up.sh does NOT rebuild it.
#
# To make the image survive a full local prune / machine rebuild, push it to a
# registry (see TOOLBOX_IMAGE below and bin/forensics/README if present).
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/_common.sh"

log "Building forensics toolbox image (unit3d-forensics-toolbox:latest)"
compose build forensics
log "Built. Image is cached locally; lab-up.sh will reuse it without rebuilding."
