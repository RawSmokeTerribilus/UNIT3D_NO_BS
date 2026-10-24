#!/usr/bin/env bash
# Arena NOBS — deja listo lo que la Arena necesita en el host antes de `docker compose up`.
# Idempotente: lo llaman `make install` y `make up`.
#   1. ARENA_CONTROL_TOKEN en el .env: si falta o está vacío, genera uno (nunca lo imprime).
#   2. arena-data/wz2100 (2100) y arena-data/q3 (2101) con el uid de su agente de salas, sin sudo: lo hace un
#      contenedor efímero, que en Docker corre como root.
set -euo pipefail
cd "$(dirname "$0")/.."

if [[ ! -f .env ]]; then
    echo "⚠️  arena-preparar: no hay .env (cópialo de .env.example primero)" >&2
    exit 1
fi

if ! grep -qE '^ARENA_CONTROL_TOKEN=.+' .env; then
    token=$(openssl rand -hex 32)
    if grep -q '^ARENA_CONTROL_TOKEN=' .env; then
        # Reescribe en el mismo inodo (cat >) para conservar dueño y permisos del .env
        contenido=$(awk -v t="$token" '/^ARENA_CONTROL_TOKEN=/{print "ARENA_CONTROL_TOKEN=" t; next} {print}' .env)
        printf '%s\n' "$contenido" > .env
    else
        printf '\n# Arena (arena/README.md)\nARENA_CONTROL_TOKEN=%s\n' "$token" >> .env
    fi
    echo "🔑 arena-preparar: ARENA_CONTROL_TOKEN generado en el .env (aplica config:cache para que lo vea la app)"
fi

# Un uid por contenedor: 2100 el agente de WZ, 2101 el de Quake 3
for par in wz2100:2100 q3:2101; do
    dir=${par%%:*} uid=${par##*:}
    mkdir -p "arena-data/$dir"
    if [[ $(stat -c %u "arena-data/$dir") != "$uid" ]]; then
        docker run --rm -v "$PWD/arena-data:/d:z" alpine:3.20 chown "$uid:$uid" "/d/$dir"
        echo "📁 arena-preparar: arena-data/$dir → $uid:$uid"
    fi
done
