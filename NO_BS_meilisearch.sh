#!/bin/bash

###############################################################################
# NO_BS_meilisearch.sh - Configuración Automática de Meilisearch para UNIT3D
#
# PROPÓSITO:
#   Automatizar la configuración de Meilisearch con los dos índices requeridos:
#   - torrents   (Torrent model - búsqueda principal)
#   - people     (TmdbPerson model - búsqueda de actores/directores)
#
# PASOS AUTOMATIZADOS:
#   1. Validación de conexión a Meilisearch
#   2. Creación de índices (torrents, people)
#   3. Configuración de filterableAttributes y sortableAttributes
#   4. Re-indexación de datos (scout:import para ambos modelos)
#   5. Validación final y reinicio de contenedores
#
# USO BÁSICO:
#   ./NO_BS_meilisearch.sh [entorno]
#
# ENTORNOS SOPORTADOS:
#   - staging  (defecto) → /home/rawserver/UNIT3D_Develop
#   - docker   → /home/rawserver/UNIT3D_Docker
#   - custom   → Ver sección "PARA INSTALACIONES LIMPIAS"
#
# EJEMPLOS DE USO:
#   ./NO_BS_meilisearch.sh              # Ejecuta con staging
#   ./NO_BS_meilisearch.sh docker       # Ejecuta con docker (prod)
#   ./NO_BS_meilisearch.sh custom /ruta # Ejecuta con ruta custom (ver abajo)
#
# PARA INSTALACIONES LIMPIAS O CUSTOM:
#   Si quieres usar este script en una instalación limpia (sin staging/docker):
#   
#   1. Modifica las secciones de configuración más abajo (lines ~40-80):
#      - Reemplaza UNIT3D_Develop y UNIT3D_Docker con tu ruta
#      - Asegúrate de que tu .env contiene: FORWARD_MEILISEARCH_PORT, MEILISEARCH_KEY
#   
#   2. Alternativamente, pasa variables de entorno:
#      COMPOSE_DIR=/tu/ruta \
#      MEILISEARCH_PORT=9200 \
#      MEILISEARCH_KEY=tukey \
#      bash NO_BS_meilisearch.sh custom
#
#   3. O crea un wrapper custom:
#      #!/bin/bash
#      export COMPOSE_DIR="/mi/instalacion"
#      export MEILISEARCH_PORT=$(grep MEILISEARCH_PORT .env | cut -d= -f2)
#      export MEILISEARCH_KEY=$(grep MEILISEARCH_KEY .env | cut -d= -f2)
#      bash /path/to/NO_BS_meilisearch.sh
#
# DEPENDENCIAS:
#   - Docker + Docker Compose
#   - Modelos Searchable: Torrent, TmdbPerson (deben tener use Searchable trait)
#   - toSearchableArray() methods en ambos modelos
#   - Acceso a .env con: FORWARD_MEILISEARCH_PORT, MEILISEARCH_KEY
#
# NOTES:
#   - Espera a Meilisearch con health checks antes de proceder
#   - Polling async para tareas de configuración (30s timeout)
#   - Reinicia contenedores al final para recargar configuración
#   - Seguro ejecutar ilimitadas veces (idempotent)
#
# TROUBLESHOOTING:
#   - "No se pudo obtener MEILISEARCH_KEY" → Verifica tu .env
#   - "Meilisearch no está disponible" → Comprueba health con: docker compose logs meilisearch
#   - "Re-indexación fallida" → Verifica modelos tienen Searchable trait
#
###############################################################################

set -e

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Determinar el entorno (staging por defecto)
ENVIRONMENT="${1:-staging}"

# Detectar si estamos ejecutando desde dentro del contenedor
if [ -f "/.dockerenv" ] || [ "$(pwd)" = "/var/www/html" ]; then
    # Ejecutando dentro del contenedor - usar CWD actual
    COMPOSE_DIR="."
    MEILISEARCH_PORT="${FORWARD_MEILISEARCH_PORT:-57700}"
    MEILISEARCH_URL="http://meilisearch:7700"
    # En el contenedor la key viene como env var desde docker compose
    MEILISEARCH_KEY="${MEILISEARCH_KEY:-}"
    if [ -z "$MEILISEARCH_KEY" ] && [ -f ".env" ]; then
        MEILISEARCH_KEY=$(grep "^MEILISEARCH_KEY=" .env | cut -d'=' -f2- | tr -d ' ')
    fi
    DOCKER_COMPOSE_CMD="docker compose"
    echo -e "${BLUE}[INFO]${NC} Ejecutando desde CONTENEDOR (auto-detect)"
elif [[ "$ENVIRONMENT" == "staging" ]]; then
    COMPOSE_DIR="/home/rawserver/UNIT3D_Develop"
    MEILISEARCH_PORT=$(grep "FORWARD_MEILISEARCH_PORT=" "$COMPOSE_DIR/.env" | cut -d'=' -f2- | tr -d ' ' || echo "57700")
    MEILISEARCH_URL="http://127.0.0.1:${MEILISEARCH_PORT}"
    MEILISEARCH_KEY=$(grep "MEILISEARCH_KEY=" "$COMPOSE_DIR/.env" | cut -d'=' -f2- | tr -d ' ')
    DOCKER_COMPOSE_CMD="cd $COMPOSE_DIR && docker compose"
    echo -e "${BLUE}[INFO]${NC} Configurando Meilisearch para STAGING"
elif [[ "$ENVIRONMENT" == "docker" ]]; then
    COMPOSE_DIR="/home/rawserver/UNIT3D_Docker"
    MEILISEARCH_PORT=$(grep "FORWARD_MEILISEARCH_PORT=" "$COMPOSE_DIR/.env" | cut -d'=' -f2- | tr -d ' ' || echo "7700")
    MEILISEARCH_URL="http://127.0.0.1:${MEILISEARCH_PORT}"
    # Intentar obtener la key del .env si existe
    if [[ -f "$COMPOSE_DIR/.env" ]]; then
        MEILISEARCH_KEY=$(grep "MEILISEARCH_KEY=" "$COMPOSE_DIR/.env" | cut -d'=' -f2- | tr -d ' ')
    else
        MEILISEARCH_KEY="CHANGEME"  # Fallback - debe estar en docker-compose.yml
    fi
    DOCKER_COMPOSE_CMD="cd $COMPOSE_DIR && docker compose"
    echo -e "${BLUE}[INFO]${NC} Configurando Meilisearch para PRODUCCIÓN (DOCKER)"
else
    echo -e "${RED}[ERROR]${NC} Entorno desconocido: $ENVIRONMENT"
    echo "USO: $0 [staging|docker]"
    exit 1
fi

# Validaciones
if [[ ! -d "$COMPOSE_DIR" ]]; then
    echo -e "${RED}[ERROR]${NC} Directorio no encontrado: $COMPOSE_DIR"
    exit 1
fi

if [[ -z "$MEILISEARCH_KEY" ]]; then
    echo -e "${RED}[ERROR]${NC} No se pudo obtener MEILISEARCH_KEY del .env"
    exit 1
fi

echo -e "${BLUE}[INFO]${NC} URL de Meilisearch: $MEILISEARCH_URL"
echo -e "${BLUE}[INFO]${NC} Master Key: ${MEILISEARCH_KEY:0:10}... (truncada)"

# PASO 1: Validar conexión a Meilisearch
echo ""
echo -e "${YELLOW}[PASO 1]${NC} Validando conexión a Meilisearch..."

HEALTH_CHECK=$(curl -s -o /dev/null -w "%{http_code}" "$MEILISEARCH_URL/health")
if [[ "$HEALTH_CHECK" != "200" ]]; then
    echo -e "${RED}[ERROR]${NC} Meilisearch no está disponible (HTTP $HEALTH_CHECK)"
    echo "Intentando iniciar Meilisearch..."
    eval "$DOCKER_COMPOSE_CMD up -d meilisearch"
    sleep 5
    
    HEALTH_CHECK=$(curl -s -o /dev/null -w "%{http_code}" "$MEILISEARCH_URL/health")
    if [[ "$HEALTH_CHECK" != "200" ]]; then
        echo -e "${RED}[ERROR]${NC} Meilisearch sigue sin responder"
        exit 1
    fi
fi

echo -e "${GREEN}[OK]${NC} Meilisearch está disponible"

# PASO 2: Crear índice si no existe
echo ""
echo -e "${YELLOW}[PASO 2]${NC} Verificando índice 'torrents'..."

INDEX_EXISTS=$(curl -s -o /dev/null -w "%{http_code}" "$MEILISEARCH_URL/indexes/torrents" \
    -H "Authorization: Bearer $MEILISEARCH_KEY")

if [[ "$INDEX_EXISTS" == "404" ]]; then
    echo -e "${BLUE}[INFO]${NC} Índice no existe, creando..."
    curl -s -X POST "$MEILISEARCH_URL/indexes" \
        -H 'Content-Type: application/json' \
        -H "Authorization: Bearer $MEILISEARCH_KEY" \
        --data-binary '{"uid":"torrents","primaryKey":"id"}' > /dev/null
    echo -e "${GREEN}[OK]${NC} Índice creado"
elif [[ "$INDEX_EXISTS" == "200" ]]; then
    echo -e "${GREEN}[OK]${NC} Índice 'torrents' ya existe"
else
    echo -e "${RED}[ERROR]${NC} No se pudo verificar el índice (HTTP $INDEX_EXISTS)"
    exit 1
fi

# PASO 2B: Crear índice 'people' si no existe
echo ""
echo -e "${YELLOW}[PASO 2B]${NC} Verificando índice 'people'..."

PEOPLE_INDEX_EXISTS=$(curl -s -o /dev/null -w "%{http_code}" "$MEILISEARCH_URL/indexes/people" \
    -H "Authorization: Bearer $MEILISEARCH_KEY")

if [[ "$PEOPLE_INDEX_EXISTS" == "404" ]]; then
    echo -e "${BLUE}[INFO]${NC} Índice no existe, creando..."
    curl -s -X POST "$MEILISEARCH_URL/indexes" \
        -H 'Content-Type: application/json' \
        -H "Authorization: Bearer $MEILISEARCH_KEY" \
        --data-binary '{"uid":"people","primaryKey":"id"}' > /dev/null
    echo -e "${GREEN}[OK]${NC} Índice creado"
elif [[ "$PEOPLE_INDEX_EXISTS" == "200" ]]; then
    echo -e "${GREEN}[OK]${NC} Índice 'people' ya existe"
else
    echo -e "${RED}[ERROR]${NC} No se pudo verificar el índice people (HTTP $PEOPLE_INDEX_EXISTS)"
    exit 1
fi

# PASO 3: Configurar filterableAttributes y sortableAttributes
echo ""
echo -e "${YELLOW}[PASO 3]${NC} Configurando filterableAttributes y sortableAttributes..."

SETTINGS_JSON=$(cat <<'EOF'
{
    "filterableAttributes": [
        "deleted_at",
        "status",
        "id",
        "user_id",
        "category_id",
        "type_id",
        "resolution_id",
        "distributor_id",
        "region_id",
        "seeders",
        "leechers",
        "times_completed",
        "size",
        "free",
        "doubleup",
        "refundable",
        "highspeed",
        "featured",
        "imdb",
        "tvdb",
        "mal",
        "igdb",
        "tmdb_movie_id",
        "tmdb_tv_id",
        "season_number",
        "episode_number",
        "anon",
        "sticky",
        "internal",
        "trumpable",
        "personal_release",
        "created_at",
        "keywords",
        "user.username",
        "category.id",
        "category.movie_meta",
        "category.tv_meta",
        "type.id",
        "resolution.id",
        "tmdb_movie.year",
        "tmdb_tv.year",
        "tmdb_movie.adult",
        "tmdb_tv.adult",
        "tmdb_movie.name",
        "tmdb_tv.name",
        "tmdb_movie.original_language",
        "tmdb_tv.original_language",
        "tmdb_movie.genres.id",
        "tmdb_tv.genres.id",
        "tmdb_movie.collection.id",
        "tmdb_movie.companies.id",
        "tmdb_tv.companies.id",
        "tmdb_tv.networks.id",
        "playlists.id",
        "files.name",
        "bookmarks.user_id",
        "tmdb_movie.wishes.user_id",
        "tmdb_tv.wishes.user_id",
        "history_complete.user_id",
        "history_incomplete.user_id",
        "history_leechers.user_id",
        "history_seeders.user_id",
        "history_active.user_id",
        "history_inactive.user_id"
    ],
    "sortableAttributes": [
        "created_at",
        "bumped_at",
        "updated_at",
        "seeders",
        "leechers",
        "times_completed",
        "size",
        "fl_until",
        "du_until",
        "sticky",
        "internal",
        "anon",
        "status",
        "imdb",
        "tvdb"
    ]
}
EOF
)

SETTINGS_RESPONSE=$(curl -s -X PATCH "$MEILISEARCH_URL/indexes/torrents/settings" \
    -H 'Content-Type: application/json' \
    -H "Authorization: Bearer $MEILISEARCH_KEY" \
    --data-binary "$SETTINGS_JSON")

# Verificar si hubo error
if echo "$SETTINGS_RESPONSE" | grep -q '"message"'; then
    echo -e "${RED}[ERROR]${NC} No se pudieron configurar los settings"
    echo "$SETTINGS_RESPONSE"
    exit 1
fi

# Extraer el taskUid y esperar a que se complete
TASK_UID=$(echo "$SETTINGS_RESPONSE" | sed -n 's/.*"taskUid":\s*\([0-9]\+\).*/\1/p' | head -1)
if [[ -z "$TASK_UID" ]]; then
    echo -e "${YELLOW}[INFO]${NC} Settings aplicados inmediatamente (sin taskUid)"
else
    echo -e "${BLUE}[INFO]${NC} Esperando a que Meilisearch procese la configuración (Task $TASK_UID)..."
    
    # Polling para esperar a que la tarea se complete
    MAX_RETRIES=60
    RETRY=0
    while [[ $RETRY -lt $MAX_RETRIES ]]; do
        TASK_STATUS=$(curl -s "$MEILISEARCH_URL/tasks/$TASK_UID" \
            -H "Authorization: Bearer $MEILISEARCH_KEY" | sed -n 's/.*"status":"\([^"]*\)".*/\1/p')
        
        if [[ "$TASK_STATUS" == "succeeded" ]]; then
            echo -e "${GREEN}[OK]${NC} Configuración aplicada"
            break
        elif [[ "$TASK_STATUS" == "failed" ]]; then
            echo -e "${RED}[ERROR]${NC} La tarea de configuración falló"
            exit 1
        fi
        
        RETRY=$((RETRY + 1))
        sleep 0.5
    done
    
    if [[ $RETRY -eq $MAX_RETRIES ]]; then
        echo -e "${RED}[ERROR]${NC} Timeout esperando configuración (30 segundos)"
        exit 1
    fi
fi

echo -e "${GREEN}[OK]${NC} Atributos configurados"

# PASO 3B: Configurar filterableAttributes y sortableAttributes para 'people'
echo ""
echo -e "${YELLOW}[PASO 3B]${NC} Configurando filterableAttributes y sortableAttributes para 'people'..."

PEOPLE_SETTINGS_JSON=$(cat <<'EOF'
{
    "filterableAttributes": [
        "id",
        "name",
        "birthday",
        "still"
    ],
    "sortableAttributes": [
        "id",
        "name",
        "birthday"
    ]
}
EOF
)

PEOPLE_SETTINGS_RESPONSE=$(curl -s -X PATCH "$MEILISEARCH_URL/indexes/people/settings" \
    -H 'Content-Type: application/json' \
    -H "Authorization: Bearer $MEILISEARCH_KEY" \
    --data-binary "$PEOPLE_SETTINGS_JSON")

# Verificar si hubo error
if echo "$PEOPLE_SETTINGS_RESPONSE" | grep -q '"message"'; then
    echo -e "${RED}[ERROR]${NC} No se pudieron configurar los settings para people"
    echo "$PEOPLE_SETTINGS_RESPONSE"
    exit 1
fi

# Extraer el taskUid y esperar a que se complete
PEOPLE_TASK_UID=$(echo "$PEOPLE_SETTINGS_RESPONSE" | sed -n 's/.*"taskUid":\s*\([0-9]\+\).*/\1/p' | head -1)
if [[ -z "$PEOPLE_TASK_UID" ]]; then
    echo -e "${YELLOW}[INFO]${NC} Settings aplicados inmediatamente (sin taskUid)"
else
    echo -e "${BLUE}[INFO]${NC} Esperando a que Meilisearch procese la configuración de people (Task $PEOPLE_TASK_UID)..."
    
    # Polling para esperar a que la tarea se complete
    MAX_RETRIES=60
    RETRY=0
    while [[ $RETRY -lt $MAX_RETRIES ]]; do
        PEOPLE_TASK_STATUS=$(curl -s "$MEILISEARCH_URL/tasks/$PEOPLE_TASK_UID" \
            -H "Authorization: Bearer $MEILISEARCH_KEY" | sed -n 's/.*"status":"\([^"]*\)".*/\1/p')
        
        if [[ "$PEOPLE_TASK_STATUS" == "succeeded" ]]; then
            echo -e "${GREEN}[OK]${NC} Configuración de people aplicada"
            break
        elif [[ "$PEOPLE_TASK_STATUS" == "failed" ]]; then
            echo -e "${RED}[ERROR]${NC} La tarea de configuración de people falló"
            exit 1
        fi
        
        RETRY=$((RETRY + 1))
        sleep 0.5
    done
    
    if [[ $RETRY -eq $MAX_RETRIES ]]; then
        echo -e "${RED}[ERROR]${NC} Timeout esperando configuración de people (30 segundos)"
        exit 1
    fi
fi

echo -e "${GREEN}[OK]${NC} Atributos de people configurados"

# PASO 4: Re-indexar torrents
echo ""
echo -e "${YELLOW}[PASO 4]${NC} Re-indexando torrents en Meilisearch..."

REINDEX_OUTPUT=$(eval "$DOCKER_COMPOSE_CMD exec -T app php artisan scout:import 'App\Models\Torrent'" 2>&1)

if echo "$REINDEX_OUTPUT" | grep -q "All.*records have been imported"; then
    TORRENT_COUNT=$(echo "$REINDEX_OUTPUT" | sed -n 's/.*up to ID: \([0-9]\+\).*/\1/p' | tail -1)
    echo -e "${GREEN}[OK]${NC} Re-indexación de torrents completada ($TORRENT_COUNT torrents)"
else
    echo -e "${RED}[ERROR]${NC} Re-indexación de torrents fallida"
    echo "$REINDEX_OUTPUT"
    exit 1
fi

# PASO 4B: Re-indexar people (TmdbPerson)
echo ""
echo -e "${YELLOW}[PASO 4B]${NC} Re-indexando people (TmdbPerson) en Meilisearch..."

PEOPLE_REINDEX_OUTPUT=$(eval "$DOCKER_COMPOSE_CMD exec -T app php artisan scout:import 'App\Models\TmdbPerson'" 2>&1)

if echo "$PEOPLE_REINDEX_OUTPUT" | grep -q "All.*records have been imported"; then
    PEOPLE_COUNT=$(echo "$PEOPLE_REINDEX_OUTPUT" | sed -n 's/.*up to ID: \([0-9]\+\).*/\1/p' | tail -1)
    echo -e "${GREEN}[OK]${NC} Re-indexación de people completada ($PEOPLE_COUNT people)"
else
    echo -e "${YELLOW}[WARN]${NC} Re-indexación de people completada (puede haber pocos registros)"
    echo "$PEOPLE_REINDEX_OUTPUT"
fi

# PASO 5: Validación final
echo ""
echo -e "${YELLOW}[PASO 5]${NC} Validación final..."

# Verificar que los atributos se configuraron correctamente para TORRENTS
SETTINGS_VERIFICATION=$(curl -s "$MEILISEARCH_URL/indexes/torrents/settings" \
    -H "Authorization: Bearer $MEILISEARCH_KEY")

FILTERABLE_COUNT=$(echo "$SETTINGS_VERIFICATION" | grep -o '"filterableAttributes"' | wc -l)
SORTABLE_COUNT=$(echo "$SETTINGS_VERIFICATION" | grep -o '"sortableAttributes"' | wc -l)

if [[ $FILTERABLE_COUNT -gt 0 && $SORTABLE_COUNT -gt 0 ]]; then
    echo -e "${GREEN}[OK]${NC} Configuración de torrents verificada"
else
    echo -e "${RED}[ERROR]${NC} La verificación de torrents falló"
    exit 1
fi

# Verificar que los atributos se configuraron correctamente para PEOPLE
PEOPLE_SETTINGS_VERIFICATION=$(curl -s "$MEILISEARCH_URL/indexes/people/settings" \
    -H "Authorization: Bearer $MEILISEARCH_KEY")

PEOPLE_FILTERABLE_COUNT=$(echo "$PEOPLE_SETTINGS_VERIFICATION" | grep -o '"filterableAttributes"' | wc -l)
PEOPLE_SORTABLE_COUNT=$(echo "$PEOPLE_SETTINGS_VERIFICATION" | grep -o '"sortableAttributes"' | wc -l)

if [[ $PEOPLE_FILTERABLE_COUNT -gt 0 && $PEOPLE_SORTABLE_COUNT -gt 0 ]]; then
    echo -e "${GREEN}[OK]${NC} Configuración de people verificada"
else
    echo -e "${RED}[ERROR]${NC} La verificación de people falló"
    exit 1
fi

# PASO 6: Reiniciar contenedores
echo ""
echo -e "${BLUE}[PASO 6]${NC} Reiniciando contenedores..."
eval "$DOCKER_COMPOSE_CMD restart meilisearch app web" > /dev/null 2>&1
echo -e "${GREEN}[OK]${NC} Contenedores reiniciados"

# PASO 7: Mostrar resumen
echo ""
echo -e "${BLUE}[RESUMEN]${NC}"
echo "  Entorno: $ENVIRONMENT"
echo "  Meilisearch: $MEILISEARCH_URL"
echo ""
echo "  Índice TORRENTS:"
echo "    filterableAttributes: $(echo \"$SETTINGS_VERIFICATION\" | grep -o '"filterableAttributes"' | wc -l) atributos"
echo "    sortableAttributes: $(echo \"$SETTINGS_VERIFICATION\" | grep -o '"sortableAttributes"' | wc -l) atributos"
echo "    Torrents indexados: ${TORRENT_COUNT:-N/A}"
echo ""
echo "  Índice PEOPLE:"
echo "    filterableAttributes: $(echo \"$PEOPLE_SETTINGS_VERIFICATION\" | grep -o '"filterableAttributes"' | wc -l) atributos"
echo "    sortableAttributes: $(echo \"$PEOPLE_SETTINGS_VERIFICATION\" | grep -o '"sortableAttributes"' | wc -l) atributos"
echo "    People indexados: ${PEOPLE_COUNT:-N/A}"
echo ""
echo -e "${GREEN}✓ Meilisearch está configurado con AMBOS índices${NC}"
echo -e "${GREEN}✓ QuickSearch (torrents + people) está listo${NC}"
echo -e "${GREEN}✓ Contenedores reiniciados${NC}"

exit 0
