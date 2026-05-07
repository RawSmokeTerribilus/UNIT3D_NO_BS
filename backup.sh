#!/bin/bash
# CSI BACKUP - "Operación Cirujano"
set -euo pipefail

if [ "$EUID" -ne 0 ]; then
  echo "❌ ERROR: Requiere sudo."
  exit 1
fi

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$PROJECT_ROOT/.env"
NOW=$(date +"%Y-%m-%d_%H%M")
STACK_STOPPED=false

read_env() {
  local key="$1"
  local default="${2-}"
  local value

  value="$(grep -E "^${key}=" "$ENV_FILE" 2>/dev/null | head -1 | cut -d'=' -f2- | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//")"

  if [ -n "$value" ]; then
    printf '%s' "$value"
  else
    printf '%s' "$default"
  fi
}

resolve_path() {
  local path="$1"

  if [[ "$path" = /* ]]; then
    printf '%s' "$path"
  else
    printf '%s/%s' "$PROJECT_ROOT" "$path"
  fi
}

rotate_snapshots() {
  local backup_dir="$1"
  local keep_count="$2"
  local snapshots=()

  shopt -s nullglob
  snapshots=("$backup_dir"/snapshot_*)
  shopt -u nullglob

  if [ "${#snapshots[@]}" -le "$keep_count" ]; then
    return 0
  fi

  mapfile -t snapshots < <(ls -dt -- "${snapshots[@]}")

  local index=0
  for snapshot in "${snapshots[@]}"; do
    index=$((index + 1))
    if [ "$index" -gt "$keep_count" ]; then
      rm -rf -- "$snapshot"
    fi
  done
}

cleanup() {
  if [ "$STACK_STOPPED" = true ]; then
    echo "🚀 Levantando el stack..."
    cd "$PROJECT_ROOT"
    docker compose up -d >/dev/null 2>&1 || true
  fi
}

trap cleanup EXIT

LOCAL_BACKUP_DIR="$(resolve_path "$(read_env BACKUP_LOCAL_DIR backups)")"
LOCAL_RETENTION="$(read_env BACKUP_LOCAL_RETENTION 3)"
BACKUP_STOP_TIMEOUT="$(read_env BACKUP_STOP_TIMEOUT 30)"
DB_SERVICE="$(read_env BACKUP_DB_SERVICE db)"
EXTERNAL_BACKUP_ENABLED="$(read_env BACKUP_EXTERNAL_ENABLED false)"
EXTERNAL_BACKUP_DIR_RAW="$(read_env BACKUP_EXTERNAL_DIR '')"
EXTERNAL_RETENTION="$(read_env BACKUP_EXTERNAL_RETENTION "$LOCAL_RETENTION")"
SNAPSHOT_DIR="$LOCAL_BACKUP_DIR/snapshot_$NOW"

mkdir -p "$SNAPSHOT_DIR"
cd "$PROJECT_ROOT"

DB_USER="$(read_env DB_USERNAME '')"
DB_PASS="$(read_env DB_PASSWORD '')"
DB_NAME="$(read_env DB_DATABASE '')"

if [ -z "$DB_USER" ] || [ -z "$DB_PASS" ] || [ -z "$DB_NAME" ]; then
  echo "❌ ERROR: Credenciales de base de datos incompletas en .env. Abortando."
  exit 1
fi

echo "🎬 Iniciando PULICIÓN de Backup ($NOW)..."

# 1. DUMP DE DB (Soberanía de datos)
echo "💾 Volcando DB..."
docker compose exec -T "$DB_SERVICE" mysqldump -u "$DB_USER" -p"$DB_PASS" --no-tablespaces "$DB_NAME" > "$SNAPSHOT_DIR/db_unit3d.sql" 2>/dev/null

# 2. STOP (Consistencia total)
echo "🛑 Deteniendo el ecosistema..."
docker compose stop --timeout "$BACKUP_STOP_TIMEOUT"
STACK_STOPPED=true

# 3. COMPRESIÓN QUIRÚRGICA
# El código vive en disco; el backup ya no exporta imágenes Docker por defecto.
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
    -C "$PROJECT_ROOT" .

# 4. ROTACIÓN LOCAL
echo "♻️ Rotando backups locales..."
rotate_snapshots "$LOCAL_BACKUP_DIR" "$LOCAL_RETENTION"

# 5. RESURRECCIÓN
echo "🚀 Levantando el stack..."
docker compose up -d
STACK_STOPPED=false

# 6. ESPEJO EXTERNO
if [[ "${EXTERNAL_BACKUP_ENABLED,,}" == "true" ]]; then
  if [ -z "$EXTERNAL_BACKUP_DIR_RAW" ]; then
    echo "⚠️ BACKUP_EXTERNAL_ENABLED=true pero BACKUP_EXTERNAL_DIR está vacío. Se omite la copia externa."
  else
    EXTERNAL_BACKUP_DIR="$(resolve_path "$EXTERNAL_BACKUP_DIR_RAW")"
    echo "🧳 Copiando snapshot al backup externo..."
    mkdir -p "$EXTERNAL_BACKUP_DIR"
    rm -rf "$EXTERNAL_BACKUP_DIR/$(basename "$SNAPSHOT_DIR")"
    cp -a "$SNAPSHOT_DIR" "$EXTERNAL_BACKUP_DIR/"

    echo "♻️ Rotando backups externos..."
    rotate_snapshots "$EXTERNAL_BACKUP_DIR" "$EXTERNAL_RETENTION"
  fi
fi

echo "✅ Backup completado. Disco a salvo."
