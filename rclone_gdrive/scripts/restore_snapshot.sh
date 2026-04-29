#!/bin/bash
# ==============================================================================
# ID: UNIT3D_GDRIVE_RESTORE
# ACR: Recuperación y desencriptado de snapshots desde Google Drive.
# Solución: Descarga desatendida vía Rclone container. 
# ==============================================================================

PROJECT_DIR="/home/rawserver/UNIT3D_Docker/rclone_gdrive"
RESTORE_DIR="/home/rawserver/UNIT3D_Docker/restauracion_emergencia"
cd "$PROJECT_DIR" || exit 1

# 1. Recon: Mostrar qué hay en la nube
echo "--- Backups disponibles en Google Drive (Cifrados) ---"
docker compose run --rm rclone_sync lsd gdrive_crypt:/
echo "-------------------------------------------------------"

# 2. Selección de objetivo
echo -n "Introduce el nombre exacto de la carpeta a restaurar (ej: snapshot_2026-03-19_0600): "
read TARGET

if [ -z "$TARGET" ]; then echo "Error: No has puesto nada."; exit 1; fi

# 3. Preparar el terreno local
mkdir -p "$RESTORE_DIR/$TARGET"

# 4. El martillazo: Descarga y desencriptado transparente
echo "Iniciando descarga de $TARGET..."
docker compose run --rm rclone_sync copy gdrive_crypt:/$TARGET "$RESTORE_DIR/$TARGET" -v --drive-chunk-size 1024M

# 5. Resultado
echo "======================================================="
echo "PROCESO COMPLETADO."
echo "Los archivos desencriptados están en: $RESTORE_DIR/$TARGET"
ls -lh "$RESTORE_DIR/$TARGET"
echo "======================================================="
