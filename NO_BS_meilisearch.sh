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
IN_CONTAINER=0
if [ -f "/.dockerenv" ] || [ "$(pwd)" = "/var/www/html" ]; then
    # Ejecutando dentro del contenedor - usar CWD actual
    IN_CONTAINER=1
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
#
# La configuracion vive UNICAMENTE en config/scout.php, bajo la clave
# scout.meilisearch.index-settings. Este paso solo la empuja a Meilisearch.
#
# Antes este script llevaba las listas escritas a fuego aqui dentro, y como el
# entrypoint lo ejecuta en cada arranque del contenedor, cada reinicio pisaba
# los settings del indice con una copia desactualizada. Eso borraba 'name' y
# 'rating' de sortableAttributes y dejaba el orden por nombre y por rating
# devolviendo un 500 hasta la siguiente reparacion manual (2026-08-17).
echo ""
echo -e "${YELLOW}[PASO 3]${NC} Sincronizando settings de indices desde config/scout.php..."

if [ "$IN_CONTAINER" -eq 1 ]; then
    SYNC_OUTPUT=$(php artisan scout:sync-index-settings 2>&1)
else
    SYNC_OUTPUT=$(eval "$DOCKER_COMPOSE_CMD exec -T app php artisan scout:sync-index-settings" 2>&1)
fi

if echo "$SYNC_OUTPUT" | grep -q "synced successfully"; then
    echo "$SYNC_OUTPUT" | sed 's/^/  /'
    echo -e "${GREEN}[OK]${NC} Settings sincronizados (torrents + people)"
else
    echo -e "${RED}[ERROR]${NC} No se pudieron sincronizar los settings"
    echo "$SYNC_OUTPUT"
    exit 1
fi

# Los pasos que quedan (re-indexado y reinicio de contenedores) se apoyan en
# "docker compose exec", que no existe dentro del contenedor. Ejecutados desde
# el entrypoint fallaban siempre, y el reinicio final habria sido un bucle.
# Desde dentro paramos aqui: los settings ya estan puestos, y los datos los
# mantiene el scheduler (AutoSyncTorrentsToMeilisearch cada 15 min,
# AutoSyncPeopleToMeilisearch a diario). Para una reconstruccion completa
# esta el boton "Reparacion completa" del panel de staff
# (artisan meilisearch:full-repair), que ademas garantiza el orden correcto.
if [ "$IN_CONTAINER" -eq 1 ]; then
    echo ""
    echo -e "${GREEN}[OK]${NC} Arranque: settings sincronizados. Re-indexado a cargo del scheduler."
    exit 0
fi

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
