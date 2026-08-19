#!/bin/bash
# Ejecuta la suite de tests contra la base DESECHABLE, nunca contra la viva.
#
# Por qué existe este script en lugar de llamar a pest directamente:
#
#   1. docker-compose inyecta DB_DATABASE=unit3d en el contenedor `app`. PHP CLI
#      corre con variables_order=EGPCS, así que esa variable acaba en $_SERVER, y
#      el adaptador de entorno de Laravel lee $_SERVER antes que lo que fija
#      phpunit.xml — incluso con force="true". Sin este -e, la suite apunta a la
#      base viva y LazilyRefreshDatabase (en el TestCase base, atado a Feature y
#      Unit) la tira y la re-migra. Pasó el 2026-08-19.
#
#   2. --schema-path=/dev/null: el cliente `mysql` de la imagen es MariaDB 11.4 y
#      no puede autenticarse contra MySQL 8 con caching_sha2_password (sin TLS le
#      falta el plugin; con TLS choca con el certificado autofirmado). Cargar
#      database/schema/mysql-schema.sql falla siempre. Las migraciones completas
#      sí funcionan porque van por PDO. El entrypoint hace este mismo apaño.
#
# El cortafuegos de tests/CreatesApplication.php aborta si aun así se colara una
# base que no termine en _testing. Este script es la comodidad; ese es el seguro.

set -uo pipefail

CONTAINER="${TEST_CONTAINER:-unit3d-staging-app}"
TEST_DB="${TEST_DB:-unit3d_testing}"

# La suite envenena bootstrap/cache/config.php. phpunit.xml fija valores de
# prueba (SCOUT_DRIVER=null, APP_ENV=testing...) y algo dentro del recorrido
# acaba escribiendo la cache de configuracion de la APLICACION con ellos. El
# sintoma observado el 2026-08-19 fue scout.driver = NULL, con lo que el
# arranque del contenedor fallaba en scout:sync-index-settings:
#
#   The "" engine does not support updating index settings.
#
# Por eso se limpia siempre al terminar, pase lo que pase con los tests.
restore_config() {
    local status=$?
    echo
    echo "--- limpiando la cache de configuracion contaminada por los tests ---"
    docker exec "$CONTAINER" php artisan config:clear >/dev/null 2>&1 || true
    docker exec "$CONTAINER" php artisan config:cache  >/dev/null 2>&1 || true
    docker exec "$CONTAINER" php artisan tinker \
        --execute='printf("scout.driver=[%s]\n", config("scout.driver"));' 2>&1 | tail -1
    return $status
}
trap restore_config EXIT

# Y limpiarla ANTES: con la config cacheada, el DB_DATABASE horneado gana sobre
# el -e de abajo y la suite apunta a la base viva. El cortafuegos de
# tests/CreatesApplication.php lo detiene, pero mejor no llegar ahi.
echo "--- limpiando la cache de configuracion antes de los tests ---"
docker exec "$CONTAINER" php artisan config:clear >/dev/null 2>&1 || true

docker exec \
    -e DB_DATABASE="$TEST_DB" \
    "$CONTAINER" \
    ./vendor/bin/pest "$@"
