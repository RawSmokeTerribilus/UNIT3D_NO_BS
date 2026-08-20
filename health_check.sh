#!/bin/bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$PROJECT_ROOT/.env"
RUN_TS="$(date +"%Y-%m-%d %H:%M:%S")"

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

HTTP_PORT="$(read_env HTTP_PORT 8008)"
LOG_FILE="$(resolve_path "$(read_env HEALTHCHECK_LOG_FILE backups/health_check.log)")"
SITE_HEALTH_URL="$(read_env HEALTHCHECK_SITE_URL "http://localhost:${HTTP_PORT}/")"
ANNOUNCE_HEALTH_URL="$(read_env ANNOUNCE_HEALTHCHECK_URL '')"
ANNOUNCE_SERVICE="$(read_env ANNOUNCE_HEALTHCHECK_SERVICE announce)"
ISSUES_DETECTED=0
SERVICE_RESTARTS=0
SITE_STATUS="not-run"
ANNOUNCE_STATUS="disabled"

mkdir -p "$(dirname "$LOG_FILE")"
cd "$PROJECT_ROOT"

# Salir limpio si el stack está abajo. Sin esto, cada tick del cron intenta
# resucitar servicios de un stack apagado a propósito y llena el log de errores.
if [ -z "$(docker compose ps --status=running -q app 2>/dev/null)" ]; then
    echo "[$RUN_TS] SKIP health_check: stack abajo"
    exit 0
fi

# Obtener lista de servicios desde docker-compose
SERVICES=$(docker compose ps --format "{{.Service}}")

for SERVICE in $SERVICES; do
    # Verificar si el servicio está corriendo
    STATUS=$(docker compose ps --format "{{.Status}}" "$SERVICE")
    
    if [[ ! "$STATUS" =~ "Up" ]]; then
        echo "[$(date +"%Y-%m-%d %H:%M:%S")] 🚨 ALERTA: $SERVICE está PAJARITO (Status: $STATUS). Resucitando..." >> "$LOG_FILE"
        docker compose up -d "$SERVICE" >> "$LOG_FILE" 2>&1
        ISSUES_DETECTED=1
        SERVICE_RESTARTS=$((SERVICE_RESTARTS + 1))
    fi
done

# 2. Check de VIDA REAL (HTTP)
# Verificamos si el sitio responde un 200/302 en el puerto 8008.
SITE_STATUS=$(curl -o /dev/null -s -w "%{http_code}" "$SITE_HEALTH_URL" || true)

if [[ "$SITE_STATUS" -ne 200 && "$SITE_STATUS" -ne 302 ]]; then
    echo "[$(date +"%Y-%m-%d %H:%M:%S")] 🚨 ALERTA CRÍTICA: Error HTTP $SITE_STATUS detectado. El Búnker está herido. Reiniciando stack PHP..." >> "$LOG_FILE"
    docker compose restart app web >> "$LOG_FILE" 2>&1
    ISSUES_DETECTED=1
fi

if [[ -n "$ANNOUNCE_HEALTH_URL" ]]; then
    ANNOUNCE_STATUS=$(curl -o /dev/null -s -w "%{http_code}" "$ANNOUNCE_HEALTH_URL" || true)

    if [[ "$ANNOUNCE_STATUS" -ne 200 ]]; then
        echo "[$(date +"%Y-%m-%d %H:%M:%S")] 🚨 ALERTA CRÍTICA: Announce fuera de servicio (HTTP ${ANNOUNCE_STATUS:-000}). Reiniciando $ANNOUNCE_SERVICE..." >> "$LOG_FILE"
        docker compose restart "$ANNOUNCE_SERVICE" >> "$LOG_FILE" 2>&1 || true
        ISSUES_DETECTED=1
    fi
fi

if [[ "$ISSUES_DETECTED" -eq 0 ]]; then
    echo "[$RUN_TS] OK health_check site=$SITE_STATUS announce=$ANNOUNCE_STATUS services=$(wc -w <<< \"$SERVICES\")"
else
    echo "[$RUN_TS] RECOVERY health_check site=$SITE_STATUS announce=$ANNOUNCE_STATUS service_restarts=$SERVICE_RESTARTS"
fi
