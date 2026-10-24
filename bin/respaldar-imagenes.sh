#!/usr/bin/env bash
# Respaldo de NUESTRAS imágenes Docker (rawsmoke/*) por si Docker Hub falla. Cada imagen (por ID) se
# guarda UNA vez por capa:
#   local   ~/UNIT3D_Docker/backups/imagenes  → remoto: lo sube cifrado rclone (run_sync.sh, 08:00)
#   externo /run/media/rawserver/8Tb/backups-imagenes (copia verificada leyendo del disco)
# Retención: las 2 versiones más nuevas de cada repo en local (y remoto), 4 en el externo, y siempre
# las que usa algún contenedor. Restaurar: zstd -dc FICHERO.tar.zst | docker load
# Cron: 15 5 * * * (antes de la parada de backup.sh a las 06:00 y de rclone a las 08:00).
set -euo pipefail
LOCAL=${IMG_LOCAL:-$HOME/UNIT3D_Docker/backups/imagenes}
EXTERNO=${IMG_EXTERNO:-/run/media/rawserver/8Tb/backups-imagenes}
KEEP_LOCAL=2 KEEP_EXT=4
log() { echo "$(date '+%F %T') $*"; }
mkdir -p "$LOCAL"
en_uso=$(docker ps --format '{{.Image}}' | sort -u)

declare -A keep_local keep_ext
for repo in $(docker images --format '{{.Repository}}' | grep '^rawsmoke/' | sort -u); do
    n=0
    # IDs únicos del repo, del más nuevo al más viejo
    while read -r id tag; do
        n=$((n + 1))
        f="${repo//\//_}__${tag}__${id}.tar.zst"
        uso=$(grep -qx "$repo:$tag" <<<"$en_uso" && echo 1 || echo 0)
        (( n <= KEEP_LOCAL || uso )) && keep_local[$f]=1
        (( n <= KEEP_EXT || uso )) && keep_ext[$f]="$repo:$tag"
    done < <(docker images "$repo" --format '{{.CreatedAt}}|{{.ID}}|{{.Tag}}' | grep -v '|<none>$' | sort -r \
             | awk -F'|' '!visto[$2]++ {print $2, $3}')
done

externo_ok() { mountpoint -q "$(dirname "$EXTERNO")" && [[ -f $EXTERNO/$1.sha256 ]]; }
for f in "${!keep_ext[@]}"; do
    ref=${keep_ext[$f]}
    # Solo en la capa externa y ya está allí verificada: nada que hacer (no se regenera cada día)
    if [[ -z ${keep_local[$f]:-} ]] && externo_ok "$f"; then
        continue
    fi
    if [[ ! -f $LOCAL/$f.sha256 ]]; then
        log "guardando $ref"
        docker save "$ref" | zstd -q -T4 -10 -o "$LOCAL/$f.tmp"
        mv "$LOCAL/$f.tmp" "$LOCAL/$f"
        (cd "$LOCAL" && sha256sum "$f" > "$f.sha256")
    fi
    if [[ -d $(dirname "$EXTERNO") ]] && mountpoint -q "$(dirname "$EXTERNO")"; then
        mkdir -p "$EXTERNO"
        if [[ ! -f $EXTERNO/$f.sha256 ]]; then
            cp "$LOCAL/$f" "$EXTERNO/$f.tmp"
            sync
            # Verificación leyendo del disco (O_DIRECT), no de la caché
            suma=$(dd if="$EXTERNO/$f.tmp" iflag=direct bs=4M status=none | sha256sum | cut -d' ' -f1)
            if [[ $suma == $(cut -d' ' -f1 "$LOCAL/$f.sha256") ]]; then
                mv "$EXTERNO/$f.tmp" "$EXTERNO/$f" && cp "$LOCAL/$f.sha256" "$EXTERNO/"
                log "externo OK $f"
            else
                rm -f "$EXTERNO/$f.tmp"; log "ERROR: copia externa corrupta de $f"
            fi
        fi
    else
        log "AVISO: disco externo no montado; solo local"
    fi
done

# Retención: fuera lo que ya no está en el conjunto de cada capa
for f in "$LOCAL"/*.tar.zst; do
    [[ -e $f ]] || continue; b=$(basename "$f")
    [[ -n ${keep_local[$b]:-} ]] || { rm -f "$f" "$f.sha256"; log "local: retirado $b"; }
done
if [[ -d $EXTERNO ]]; then
    for f in "$EXTERNO"/*.tar.zst; do
        [[ -e $f ]] || continue; b=$(basename "$f")
        [[ -n ${keep_ext[$b]:-} ]] || { rm -f "$f" "$f.sha256"; log "externo: retirado $b"; }
    done
fi
# El local guarda temporalmente las 4 (para copiarlas al externo); se queda solo con las 2
log "hecho: local $(du -sh "$LOCAL" | cut -f1), externo $(du -sh "$EXTERNO" 2>/dev/null | cut -f1)"
