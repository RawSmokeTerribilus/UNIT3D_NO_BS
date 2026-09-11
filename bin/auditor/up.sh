#!/bin/bash
# Construye (si hace falta) y levanta el panel de consultas.
source "$(dirname "${BASH_SOURCE[0]}")/_common.sh"

# El archivo de ejecuciones lo escribe el contenedor como uid 27.
mkdir -p "$AUDITOR_DIR/run/queries"
if [ "$(stat -c %u "$AUDITOR_DIR/run")" != "27" ]; then
  echo "ajustando propietario de auditor/run a uid 27 (necesita sudo)"
  sudo chown -R 27:27 "$AUDITOR_DIR/run"
fi

dc up -d --build
echo
dc ps
echo
echo "panel en http://${BIND_ADDR:-127.0.0.1}:${PORT:-8781}"
