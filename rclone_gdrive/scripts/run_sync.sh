#!/bin/bash
set -euo pipefail
# ==============================================================================
# ID: UNIT3D_GDRIVE_BACKUP_SYNC
# ACR: Sincronización masiva y cifrada de snapshots de UNIT3D contra Google Drive.
# Solución: Contenedor efímero vía Compose. Bypass de interfaz web de Google.
#           Uso intensivo de RAM (4GB) para chunks de 1024M evitando timeouts.
#           Desencriptado desatendido usando el rclone.conf local.
# ==============================================================================

# 1. Reconocer el entorno
ROOT_DIR="/home/rawserver/UNIT3D_Docker"
PROJECT_DIR="/home/rawserver/UNIT3D_Docker/rclone_gdrive"
SUMMARY_LOG="${ROOT_DIR}/backups/rclone.log"
WRAPPER_LOG="${PROJECT_DIR}/logs/cron_wrapper.log"
cd "$PROJECT_DIR" || exit 1
mkdir -p logs "${ROOT_DIR}/backups"

log_summary() {
    local line="[$(date +'%Y-%m-%d %H:%M:%S')] $1"
    echo "$line" >> "$WRAPPER_LOG"
    echo "$line" >> "$SUMMARY_LOG"
}

# 2. Inyectar contexto de usuario (Soberanía de permisos)
#export UID=$(id -u)
#export GID=$(id -g)

log_summary "Iniciando sincronización masiva..."

# 3. Lanzar la pulición efímera. 
# 'docker compose run' respeta los volúmenes, ejecuta el 'command' del YAML y '--rm' lo destruye al terminar.
set +e
docker compose run --rm rclone_sync
EXIT_CODE=$?
set -e

# 4. Verificar salida
if [ $EXIT_CODE -eq 0 ]; then
    log_summary "Sync completado con éxito. Contenedor destruido."
else
    log_summary "ERROR crítico (código $EXIT_CODE). Revisar logs/sync_execution.log"
fi

exit $EXIT_CODE
