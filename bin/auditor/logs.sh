#!/bin/bash
source "$(dirname "${BASH_SOURCE[0]}")/_common.sh"
dc logs -f --tail=100 auditor
