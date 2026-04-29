#!/bin/bash
# --- CONFIGURACIÓN ---
DOCKER_DIR="/home/rawserver/UNIT3D_Develop"
LOG_FILE="$DOCKER_DIR/backups/health_check.log"

cd "$DOCKER_DIR"

# Obtener lista de servicios desde docker-compose
SERVICES=$(docker compose ps --format "{{.Service}}")

for SERVICE in $SERVICES; do
    # Verificar si el servicio está corriendo
    STATUS=$(docker compose ps --format "{{.Status}}" "$SERVICE")
    
    if [[ ! "$STATUS" =~ "Up" ]]; then
        echo "[$(date +"%Y-%m-%d %H:%M:%S")] 🚨 ALERTA: $SERVICE está PAJARITO (Status: $STATUS). Resucitando..." >> "$LOG_FILE"
        docker compose up -d "$SERVICE" >> "$LOG_FILE" 2>&1
    fi
done

# 2. Check de VIDA REAL (HTTP)
# Verificamos si el sitio responde un 200/302 en el puerto 8008.
HTTP_STATUS=$(curl -o /dev/null -s -w "%{http_code}" http://localhost:58080/)

if [[ "$HTTP_STATUS" -ne 200 && "$HTTP_STATUS" -ne 302 ]]; then
    echo "[$(date +"%Y-%m-%d %H:%M:%S")] 🚨 ALERTA CRÍTICA: Error HTTP $HTTP_STATUS detectado. El Búnker está herido. Reiniciando stack PHP..." >> "$LOG_FILE"
    docker compose restart app web >> "$LOG_FILE" 2>&1
fi
