#!/usr/bin/env bash
#
# Respalda un fichero del proyecto en backups/manual/ antes de tocarlo.
#
# Uso:  bin/respaldo-manual.sh <ruta-relativa> <motivo-en-kebab-case>
#
# El respaldo conserva el árbol del proyecto bajo backups/manual/ y lleva en su
# primera línea (o justo tras `<?php` / el shebang, para que siga siendo
# restaurable tal cual) un comentario con su ruta de origen.
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEST_ROOT="$ROOT/backups/manual"

[ $# -eq 2 ] || { echo "uso: $0 <ruta-relativa> <motivo>" >&2; exit 1; }

REL="$1"
MOTIVO="$2"
SRC="$ROOT/$REL"

[ -e "$SRC" ] || { echo "no existe: $REL" >&2; exit 1; }

STAMP="$(date -u +%Y%m%d_%H%M)"
DEST="$DEST_ROOT/${REL}.${STAMP}-pre-${MOTIVO}.bak"
mkdir -p "$(dirname "$DEST")"

if [ -d "$SRC" ]; then
    cp -a "$SRC" "$DEST"
    printf 'origen: %s\n' "$REL" > "$DEST.origen"
    echo "$DEST (directorio)"
    exit 0
fi

cp -a "$SRC" "$DEST"

case "$REL" in
    .env*|*.json|*.lock)  printf 'origen: %s\n' "$REL" > "$DEST.origen"; chmod 600 "$DEST" 2>/dev/null || true ;;
    *.blade.php)          sed -i "1i {{-- origen: $REL --}}" "$DEST" ;;
    *.php)                sed -i "1a // origen: $REL" "$DEST" ;;
    *.js|*.ts|*.scss|*.css) sed -i "1i // origen: $REL" "$DEST" ;;
    *.sh)                 sed -i "1a # origen: $REL" "$DEST" ;;
    *.yml|*.yaml|*.conf|*.ini) sed -i "1i # origen: $REL" "$DEST" ;;
    *)                    printf 'origen: %s\n' "$REL" > "$DEST.origen" ;;
esac

echo "$DEST"
