#!/bin/sh
set -e

# git safe directory
git config --global --add safe.directory /var/www/html

# Copy .env if not exists
if [ ! -f .env ]; then
    echo "Creating .env from .env.example..."
    cp .env.example .env
fi

# Install dependencies if vendor is missing
if [ ! -d "vendor" ]; then
    echo "Vendor directory not found. Running composer install..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Build assets if missing or manifest is not found
if [ ! -d "public/build" ] || [ ! -f "public/build/manifest.json" ]; then
    echo "Frontend assets or Vite manifest not found. Building..."
    npm install
    npm run build
fi

# 1. Crear estructura de carpetas interna (Evita errores de "Folder not found")
mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/app/public \
         storage/logs \
         bootstrap/cache

# Wait for MySQL AND Redis to be ready
echo "Waiting for services to boot..."
until nc -z db 3306; do
  sleep 1
done
# Asegúrate de que tu contenedor/servicio de redis se llama "redis" en el docker-compose. 
# Si se llama de otra forma (ej: unit3d-redis), cambia el nombre aquí abajo:
until nc -z redis 6379; do
  sleep 1
done
echo "Database and Redis are ready!"

# Generate key if not set
if [ -z "$(grep APP_KEY .env | cut -d '=' -f2)" ]; then
    echo "Generating app key..."
    php artisan key:generate
fi

# Run migrations
echo "Running migrations..."
php artisan migrate --force --schema-path=/dev/null || php artisan migrate --force

# Actualizar Blacklist de Emails ANTES de cambiar los permisos
echo "Actualizando Blacklist de Emails..."
php artisan auto:email-blacklist-update

# === MEILISEARCH DUAL-INDEX AUTO-CONFIGURATION ===
echo "Waiting for Meilisearch service..."
MEILISEARCH_READY=0
MEILISEARCH_RETRIES=0
while [ $MEILISEARCH_READY -eq 0 ] && [ $MEILISEARCH_RETRIES -lt 30 ]; do
    if wget -q -O- http://meilisearch:7700/health 2>/dev/null | grep -q '"status":"available"'; then
        MEILISEARCH_READY=1
        echo "✓ Meilisearch is available. Configuring dual indexes (torrents + people)..."
        # Ejecutar script de configuración
        if sh ./NO_BS_meilisearch.sh > /tmp/meilisearch-config.log 2>&1; then
            echo "✓ Meilisearch configuration completed successfully"
        else
            echo "⚠️  Meilisearch configuration completed with warnings (check logs: /tmp/meilisearch-config.log)"
        fi
    else
        MEILISEARCH_RETRIES=$((MEILISEARCH_RETRIES + 1))
        sleep 1
    fi
done

if [ $MEILISEARCH_READY -eq 0 ]; then
    echo "⚠️  Meilisearch failed to respond after 30s - skipping auto-configuration"
    echo "   Manual recovery: docker compose restart app && make meilisearch"
fi

# 2. Ajuste masivo de permisos (0775)
# Esto cubre: vendor, storage, public y bootstrap/cache
echo "Ajustando permisos a 775 en carpetas críticas..."
chmod -R 775 vendor storage public bootstrap/cache
# EL TRUCO FINAL: Ajuste de dueño de ÚLTIMA HORA
# Esto garantiza que cualquier archivo de caché que haya escupido el comando anterior, pase a ser de www-data
echo "Cambiando propietario final a www-data..."
chown -R www-data:www-data vendor storage public bootstrap/cache

# Run Octane or PHP-FPM
echo "Starting PHP-FPM..."
exec php-fpm
