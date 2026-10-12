#!/bin/bash
# Rehidrata lo que el repo NO trae y sin lo cual el sitio sale roto o a medias.
#
# Un `git clone` no basta para levantar NOBS: hay ficheros fuera de git (por tamaño
# o por copyright) y cachés que sólo rehace un comando que nadie se acuerda de
# lanzar. La regla: ignorar algo sólo vale si un comando lo rehidrata Y ese comando
# está en la rutina de despliegue. Esta es esa rutina.
#
#   1. Ficheros de Internet Archive (bin/assets-externos/<item>.sha256): arcade
#      ScummVM (motor, portadas, juegos) y RetroArch (ROMs, cores, frontend). Baja
#      SÓLO lo que falta o no cuadra con el sha256, y lo deja en su ruta.
#   2. Manual (/manual): compila book/ con mdbook y lo copia a storage/app/manual.
#   3. Comandos de artisan que ninguna otra rutina lanza: índice del selector de
#      iconos, catálogo y portadas de RetroArch, lista de correos desechables.
#
# Uso:
#   bin/rehidratar-assets.sh [--comprobar] [--contenedor NOMBRE] [--sin-descargas]
#                            [--sin-manual] [--sin-artisan] [--item ID]
#
#   --comprobar      sólo informa de lo que falta; no descarga ni escribe nada
#   --contenedor     contenedor de la app (por defecto unit3d-app; en staging
#                    unit3d-staging-app)
#   --item ID        sólo ese item de Archive
#
# Inventario y decisiones: Raw_Vault/Knowledge/Infrastructure/UNIT3D/
# assets-fuera-de-git-inventario.md
set -euo pipefail

PROYECTO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MANIFIESTOS="$PROYECTO/bin/assets-externos"
CONTENEDOR=unit3d-app
COMPROBAR=0 DESCARGAS=1 MANUAL=1 ARTISAN=1 SOLO_ITEM=""

while [ $# -gt 0 ]; do
    case "$1" in
        --comprobar) COMPROBAR=1 ;;
        --contenedor) CONTENEDOR=$2; shift ;;
        --sin-descargas) DESCARGAS=0 ;;
        --sin-manual) MANUAL=0 ;;
        --sin-artisan) ARTISAN=0 ;;
        --item) SOLO_ITEM=$2; shift ;;
        -h|--help) sed -n '2,27p' "$0"; exit 0 ;;
        *) echo "Opción desconocida: $1" >&2; exit 2 ;;
    esac
    shift
done

# public/ y storage/ son del usuario del contenedor (82), no del host.
DUENO="$(stat -c '%u:%g' "$PROYECTO/public")"
fallos=0

# --- 1. Internet Archive -------------------------------------------------------
if [ "$DESCARGAS" = 1 ]; then
    for manifiesto in "$MANIFIESTOS"/*.sha256; do
        item="$(basename "$manifiesto" .sha256)"
        [ -n "$SOLO_ITEM" ] && [ "$item" != "$SOLO_ITEM" ] && continue
        faltan=0 bajados=0
        echo "== Archive: $item"

        while read -r suma ruta; do
            destino="$PROYECTO/$ruta"
            if [ -f "$destino" ] && echo "$suma  $destino" | sha256sum -c --status 2>/dev/null; then
                continue
            fi
            faltan=$((faltan + 1))
            [ "$COMPROBAR" = 1 ] && { echo "   falta o no cuadra: $ruta"; continue; }

            url="https://archive.org/download/$item/$(python3 -I -c 'import sys, urllib.parse; print(urllib.parse.quote(sys.argv[1]))' "$ruta")"
            tmp="$(mktemp "$PROYECTO/storage/.rehidratar.XXXXXX")"
            if curl -fsSL --retry 5 --retry-delay 20 -o "$tmp" "$url" \
               && echo "$suma  $tmp" | sha256sum -c --status; then
                sudo -n mkdir -p "$(dirname "$destino")"
                sudo -n mv "$tmp" "$destino"
                sudo -n chown "$DUENO" "$destino"
                bajados=$((bajados + 1))
            else
                rm -f "$tmp"
                echo "   ERROR al bajar o verificar: $ruta" >&2
                fallos=$((fallos + 1))
            fi
        done <"$manifiesto"

        echo "   $(wc -l <"$manifiesto") en el manifiesto · $faltan faltaban · $bajados bajados"
    done
fi

# --- 2. Manual -------------------------------------------------------------------
if [ "$MANUAL" = 1 ]; then
    echo "== Manual (/manual)"
    if ! command -v mdbook >/dev/null; then
        echo "   ERROR: falta mdbook en el host" >&2; fallos=$((fallos + 1))
    elif [ "$COMPROBAR" = 1 ]; then
        [ -f "$PROYECTO/storage/app/manual/index.html" ] && echo "   presente" || echo "   FALTA"
    else
        mdbook build "$PROYECTO/book" >/dev/null
        sudo -n mkdir -p "$PROYECTO/storage/app/manual"
        sudo -n cp -a "$PROYECTO/book/book/." "$PROYECTO/storage/app/manual/"
        sudo -n chown -R "$DUENO" "$PROYECTO/storage/app/manual"
        echo "   compilado y copiado"
    fi
fi

# --- 3. Artisan ------------------------------------------------------------------
# Como www-data: lo que escriben lo tiene que poder reescribir la web después.
if [ "$ARTISAN" = 1 ]; then
    for orden in icon:build-index retroarch:scan-roms retroarch:fetch-covers email-blacklist:sync; do
        if [ "$COMPROBAR" = 1 ]; then
            echo "== artisan $orden (se lanzaría)"
            continue
        fi
        echo "== artisan $orden"
        if ! docker exec -u www-data "$CONTENEDOR" php artisan "$orden"; then
            echo "   ERROR en $orden" >&2
            fallos=$((fallos + 1))
        fi
    done
fi

[ "$fallos" -eq 0 ] && echo "OK" || { echo "$fallos fallos" >&2; exit 1; }
