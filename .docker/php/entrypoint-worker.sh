#!/bin/sh
set -e

# Wait for MySQL to be ready
echo "Waiting for database..."
until nc -z db 3306; do
  sleep 1
done
echo "Database is ready!"

echo "Starting Queue Worker on queues: ${QUEUE_WORK_QUEUES:-default}"
exec php artisan queue:work --queue="${QUEUE_WORK_QUEUES:-default}" --sleep="${QUEUE_WORK_SLEEP:-3}" --tries="${QUEUE_WORK_TRIES:-1}" --timeout="${QUEUE_WORK_TIMEOUT:-300}"
