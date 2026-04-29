#!/bin/sh
set -e

# Wait for MySQL to be ready
echo "Waiting for database..."
until nc -z db 3306; do
  sleep 1
done
echo "Database is ready!"

echo "Starting Queue Worker..."
exec php artisan queue:work
