#!/bin/bash
# CSI BACKUP - "Operación Cirujano"
set -e 

if [ "$EUID" -ne 0 ]; then
  echo "❌ ERROR: Requiere sudo."
  exit 1
fi

# --- CONFIGURACIÓN ---
NOW=$(date +"%Y-%m-%d_%H%M")
DOCKER_DIR="/home/rawserver/UNIT3D_Docker"
BASE_BACKUP_DIR="$DOCKER_DIR/backups"
SNAPSHOT_DIR="$BASE_BACKUP_DIR/snapshot_$NOW"
MAX_BACKUPS=3

cd "$DOCKER_DIR"
mkdir -p "$SNAPSHOT_DIR"

# Leer credenciales desde .env (nunca hardcoded)
DB_USER=$(grep "^DB_USERNAME=" "$DOCKER_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'")
DB_PASS=$(grep "^DB_PASSWORD=" "$DOCKER_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'")
DB_NAME=$(grep "^DB_DATABASE=" "$DOCKER_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'")

if [ -z "$DB_PASS" ]; then
  echo "❌ ERROR: DB_PASSWORD no encontrado en .env. Abortando."
  exit 1
fi

echo "🎬 Iniciando PULICIÓN de Backup ($NOW)..."

# 1. DUMP DE DB (Soberanía de datos)
echo "💾 Volcando DB..."
docker exec unit3d-db mysqldump -u "$DB_USER" -p"$DB_PASS" --no-tablespaces "$DB_NAME" > "$SNAPSHOT_DIR/db_unit3d.sql" 2>/dev/null

# 2. STOP (Consistencia total)
echo "🛑 Deteniendo el ecosistema..."
docker compose stop

# 3. COMPRESIÓN QUIRÚRGICA
# Hemos quitado el volcado de imágenes para ahorrar ~500MB por backup
echo "📂 Comprimiendo archivos críticos..."
tar -czf "$SNAPSHOT_DIR/unit3d_full_$NOW.tar.gz" \
    --exclude='./backups' \
    --exclude='./storage/app/backups' \
    --exclude='./storage/framework/cache/*' \
    --exclude='./storage/framework/sessions/*' \
    --exclude='./storage/framework/views/*' \
    --exclude='./storage/logs/*.log' \
    --exclude='./.docker/data/mysql' \
    --exclude='./node_modules' \
    --exclude='*.sock' \
    --exclude='./storage/app/backup-temp/*' \
    -C "$DOCKER_DIR" .

# 4. ROTACIÓN (Orden inverso para no borrar el nuevo)
echo "♻️ Rotando backups antiguos..."
cd "$BASE_BACKUP_DIR"
ls -dt snapshot_* | tail -n +$((MAX_BACKUPS + 1)) | xargs -r rm -rf

# 5. RESURRECCIÓN
echo "🚀 Levantando el stack..."
cd "$DOCKER_DIR"
docker compose up -d

echo "✅ Backup completado. Disco a salvo."
