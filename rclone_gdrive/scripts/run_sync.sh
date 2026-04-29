#!/bin/bash
# ==============================================================================
# ID: UNIT3D_GDRIVE_BACKUP_SYNC
# ACR: Sincronización masiva y cifrada de snapshots de UNIT3D contra Google Drive.
# Solución: Contenedor efímero vía Compose. Bypass de interfaz web de Google.
#           Uso intensivo de RAM (4GB) para chunks de 1024M evitando timeouts.
#           Desencriptado desatendido usando el rclone.conf local.
# ==============================================================================

# 1. Reconocer el entorno
PROJECT_DIR="/home/rawserver/UNIT3D_Docker/rclone_gdrive"
cd "$PROJECT_DIR" || exit 1

# 2. Inyectar contexto de usuario (Soberanía de permisos)
#export UID=$(id -u)
#export GID=$(id -g)

echo "[$(date +'%Y-%m-%d %H:%M:%S')] Iniciando sincronización masiva..." >> logs/cron_wrapper.log

# 3. Lanzar la pulición efímera. 
# 'docker compose run' respeta los volúmenes, ejecuta el 'command' del YAML y '--rm' lo destruye al terminar.
docker compose run --rm rclone_sync

# 4. Verificar salida
EXIT_CODE=$?
if [ $EXIT_CODE -eq 0 ]; then
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] Sync completado con éxito. Contenedor destruido." >> logs/cron_wrapper.log
else
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] ERROR Crítico (Código $EXIT_CODE). Revisar logs/sync_execution.log" >> logs/cron_wrapper.log
fi

exit $EXIT_CODE
