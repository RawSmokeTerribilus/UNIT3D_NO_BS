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

# Estado del ultimo backup, para el digest del staff (StaffDigest::backup()).
# El contenedor de la app ve el repo entero en /var/www/html, asi que lo lee de
# ahi. Sin este fichero las copias externas salieron corruptas tres dias seguidos
# (29-09 a 01-10-2026) sin que nadie se enterara: `cp` no se queja si el disco
# devuelve basura.
ESTADO_FILE="$PROJECT_ROOT/backups/estado_backup.json"
ESTADO_ESCRITO=false
FASE="inicio"
EXTERNO_ESTADO="desactivado"
EXTERNO_MALOS=()

escribir_estado() {
  local estado="$1"
  local mensaje="$2"
  local bytes

  bytes="$(du -sb "${SNAPSHOT_DIR:-/nonexistent}" 2>/dev/null | cut -f1)"
  mkdir -p "$(dirname "$ESTADO_FILE")"
  jq -n \
    --arg fecha "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
    --arg estado "$estado" \
    --arg fase "$FASE" \
    --arg snapshot "$(basename "${SNAPSHOT_DIR:-}")" \
    --argjson bytes "${bytes:-0}" \
    --arg externo "$EXTERNO_ESTADO" \
    --arg externo_dir "${EXTERNAL_BACKUP_DIR:-}" \
    --arg mensaje "$mensaje" \
    '{fecha: $fecha, estado: $estado, fase: $fase, snapshot: $snapshot, bytes: $bytes,
      externo: {estado: $externo, dir: $externo_dir, ficheros_malos: $ARGS.positional},
      mensaje: $mensaje}' \
    --args "${EXTERNO_MALOS[@]}" > "$ESTADO_FILE.tmp"
  chmod 644 "$ESTADO_FILE.tmp"
  mv -f "$ESTADO_FILE.tmp" "$ESTADO_FILE"
  ESTADO_ESCRITO=true
}

# Hash de lo que hay EN EL DISCO, no en la cache de paginas: con O_DIRECT la
# lectura va al dispositivo. Un sha256sum normal justo despues del cp lee la
# cache y da la copia por buena aunque en el disco haya basura.
hash_directo() {
  dd if="$1" iflag=direct bs=4M status=none | sha256sum | cut -d' ' -f1
}

cleanup() {
  local rc=$?

  if [ "$ESTADO_ESCRITO" != true ]; then
    escribir_estado "error" "backup.sh terminó con código $rc en la fase '$FASE'" || true
  fi

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
FASE="volcado_db"
echo "💾 Volcando DB..."
docker compose exec -T "$DB_SERVICE" mysqldump -u "$DB_USER" -p"$DB_PASS" --no-tablespaces "$DB_NAME" > "$SNAPSHOT_DIR/db_unit3d.sql" 2>/dev/null

# mysqldump escribe esta marca como ultima linea solo si termina entero; un
# volcado cortado a medias tambien sale con tamano y pasaria por bueno.
if ! tail -c 200 "$SNAPSHOT_DIR/db_unit3d.sql" | grep -q -- '-- Dump completed'; then
  echo "❌ ERROR: el volcado de la DB está incompleto (falta '-- Dump completed')."
  escribir_estado "error" "volcado de la DB incompleto: falta la marca '-- Dump completed'"
  exit 1
fi

# 2. STOP (Consistencia total)
FASE="parada"
echo "🛑 Deteniendo el ecosistema..."
docker compose stop --timeout "$BACKUP_STOP_TIMEOUT"
STACK_STOPPED=true

# 3. COMPRESIÓN QUIRÚRGICA
# El código vive en disco; el backup ya no exporta imágenes Docker por defecto.
FASE="compresion"
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
FASE="rotacion_local"
echo "♻️ Rotando backups locales..."
rotate_snapshots "$LOCAL_BACKUP_DIR" "$LOCAL_RETENTION"

# 5. RESURRECCIÓN
FASE="arranque"
echo "🚀 Levantando el stack..."
docker compose up -d
STACK_STOPPED=false

# 5b. VERIFICACIÓN LOCAL — con el stack ya arriba, que no alarga la parada.
FASE="verificacion_local"
echo "🔍 Comprobando la integridad del tar.gz..."
if ! gzip -t "$SNAPSHOT_DIR/unit3d_full_$NOW.tar.gz"; then
  echo "❌ ERROR: el tar.gz local no pasa gzip -t."
  escribir_estado "error" "el tar.gz local está corrupto (gzip -t falla)"
  exit 1
fi

# 6. ESPEJO EXTERNO
if [[ "${EXTERNAL_BACKUP_ENABLED,,}" == "true" ]]; then
  if [ -z "$EXTERNAL_BACKUP_DIR_RAW" ]; then
    echo "⚠️ BACKUP_EXTERNAL_ENABLED=true pero BACKUP_EXTERNAL_DIR está vacío. Se omite la copia externa."
    EXTERNO_ESTADO="sin_ruta"
  else
    EXTERNAL_BACKUP_DIR="$(resolve_path "$EXTERNAL_BACKUP_DIR_RAW")"

    # Si el disco externo no está montado, su ruta (/run/media/...) cae en un
    # tmpfs: la copia llena la RAM y tumba todo `docker exec` (pasó el 29-09-2026).
    # Se mira el sistema de ficheros del ancestro que exista, ANTES del mkdir -p.
    EXTERNAL_PROBE="$EXTERNAL_BACKUP_DIR"
    while [ ! -d "$EXTERNAL_PROBE" ]; do
      EXTERNAL_PROBE="$(dirname "$EXTERNAL_PROBE")"
    done
    EXTERNAL_FSTYPE="$(findmnt -n -o FSTYPE -T "$EXTERNAL_PROBE" 2>/dev/null || true)"
    case "$EXTERNAL_FSTYPE" in
      tmpfs|ramfs|devtmpfs|"")
        echo "❌ ERROR: $EXTERNAL_BACKUP_DIR cae en '${EXTERNAL_FSTYPE:-desconocido}' ($(findmnt -n -o TARGET -T "$EXTERNAL_PROBE" 2>/dev/null)): el disco externo no está montado. Se omite la copia externa; el snapshot local está en $SNAPSHOT_DIR."
        EXTERNO_ESTADO="desmontado"
        escribir_estado "error" "el disco externo no está montado; solo hay copia local"
        exit 1
        ;;
    esac

    FASE="copia_externa"
    echo "🧳 Copiando snapshot al backup externo..."
    mkdir -p "$EXTERNAL_BACKUP_DIR"
    EXTERNAL_SNAPSHOT="$EXTERNAL_BACKUP_DIR/$(basename "$SNAPSHOT_DIR")"
    rm -rf "$EXTERNAL_SNAPSHOT"
    cp -a "$SNAPSHOT_DIR" "$EXTERNAL_BACKUP_DIR/"
    sync -f "$EXTERNAL_SNAPSHOT"

    FASE="verificacion_externa"
    echo "🔍 Verificando la copia externa contra la local..."
    for fichero in "$SNAPSHOT_DIR"/*; do
      nombre="$(basename "$fichero")"
      if [ "$(sha256sum < "$fichero" | cut -d' ' -f1)" != "$(hash_directo "$EXTERNAL_SNAPSHOT/$nombre")" ]; then
        EXTERNO_MALOS+=("$nombre")
      fi
    done

    if [ "${#EXTERNO_MALOS[@]}" -gt 0 ]; then
      # Se aparta con otro nombre para que la rotacion (snapshot_*) no la cuente
      # y no empuje fuera a una copia buena anterior.
      mv "$EXTERNAL_SNAPSHOT" "$EXTERNAL_BACKUP_DIR/CORRUPTO_$(basename "$SNAPSHOT_DIR")"
      EXTERNO_ESTADO="corrupto"
      echo "❌ ERROR: la copia externa NO coincide con la local: ${EXTERNO_MALOS[*]}. Apartada como CORRUPTO_$(basename "$SNAPSHOT_DIR"); no se rotan las externas."
      escribir_estado "error" "la copia externa no coincide con la local (${#EXTERNO_MALOS[@]} fichero(s))"
      exit 1
    fi
    EXTERNO_ESTADO="verificado"

    FASE="rotacion_externa"
    echo "♻️ Rotando backups externos..."
    rotate_snapshots "$EXTERNAL_BACKUP_DIR" "$EXTERNAL_RETENTION"
  fi
fi

FASE="fin"
if [ "$EXTERNO_ESTADO" = "sin_ruta" ]; then
  escribir_estado "error" "copia externa activada pero sin BACKUP_EXTERNAL_DIR; solo hay copia local"
else
  escribir_estado "ok" "backup completado"
fi
echo "✅ Backup completado. Disco a salvo."
