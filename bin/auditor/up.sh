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

# Comprobación de que lo desplegado ES lo que hay en disco. Se editó un preset,
# se hizo el commit y no se redesplegó: el panel seguía sirviendo el título
# viejo y nadie se enteró. Un panel que sirve algo distinto de lo que dice el
# repositorio es peor que uno caído.
sleep 2
DESYNC=0
cd "$PROJECT_ROOT/auditor"
for F in $(find . -type f \( -name '*.py' -o -name '*.json' -o -name '*.js' \
           -o -name '*.html' -o -name '*.css' \) ! -path './run/*' \
           ! -path '*__pycache__*' | sed 's|^\./||'); do
  A=$(sha256sum "$F" | cut -d' ' -f1)
  B=$(docker exec "${AUDITOR_CONTAINER:-unit3d-auditor}" sha256sum "/app/$F" 2>/dev/null | cut -d' ' -f1)
  if [ "$A" != "$B" ]; then echo "  DESINCRONIZADO: $F"; DESYNC=$((DESYNC+1)); fi
done
cd "$PROJECT_ROOT"
if [ "$DESYNC" -gt 0 ]; then
  echo "  AVISO: $DESYNC fichero(s) del disco NO están en el contenedor."
else
  echo "  disco y contenedor coinciden"
fi
echo
dc ps
echo
echo "panel en http://${BIND_ADDR:-127.0.0.1}:${PORT:-8781}"
