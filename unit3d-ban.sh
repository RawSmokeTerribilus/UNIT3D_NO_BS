#!/bin/bash

# 1. Extraer IPs maliciosas de la DB de UNIT3D
IP_MALAS=$(docker exec unit3d-staging-app php artisan tinker --execute="echo DB::table('failed_login_attempts')->select('ip_address')->groupBy('ip_address')->havingRaw('COUNT(*) > 3')->pluck('ip_address')->implode(PHP_EOL);" 2>/dev/null | grep -vE '^(192\.168|10|172\.(1[6-9]|2[0-9]|3[0-1])|127)\.')

# 2. Si no hay IPs para banear, salir
if [ -z "$IP_MALAS" ]; then
    exit 0
fi

RELOAD_NEEDED=0

# 3. Comprobar cada IP y banearla si no está ya en la lista
for IP in $IP_MALAS; do
    if ! firewall-cmd --zone=drop --query-source=$IP -q; then
        firewall-cmd --permanent --zone=drop --add-source=$IP > /dev/null
        RELOAD_NEEDED=1
    fi
done

# 4. Recargar el firewall si hubo cambios
if [ $RELOAD_NEEDED -eq 1 ]; then
    firewall-cmd --reload > /dev/null
fi
