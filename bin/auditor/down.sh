#!/bin/bash
# Para el panel de consultas. No borra el archivo de ejecuciones.
source "$(dirname "${BASH_SOURCE[0]}")/_common.sh"
dc down
