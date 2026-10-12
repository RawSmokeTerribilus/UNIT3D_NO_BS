#!/bin/sh
# Comprobación de assets fuera de git en CADA arranque del contenedor de la app.
#
# Lo llama .docker/entrypoint.sh. Es POSIX sh a propósito: el contenedor es Alpine
# y no trae bash, así que bin/rehidratar-assets.sh (bash, desde el host) no corre
# aquí dentro.
#
# NO descarga nada: bajar varios GB de Internet Archive dentro del entrypoint
# dejaría php-fpm sin arrancar durante horas. Lo que hace:
#   - avisa (muy visible en los logs del contenedor) si faltan ficheros de los
#     manifiestos de Archive o el manual del staff, y dice qué lanzar desde el host;
#   - rehace lo barato si falta: el índice del selector de iconos y el catálogo de
#     RetroArch.
# Nunca falla el arranque: todo es informativo o recuperable.
#
# Corre como root antes del chown final del entrypoint, así que lo que genere
# acaba siendo de www-data igual que el resto.

cd "$(dirname "$0")/.." || exit 0

for manifiesto in bin/assets-externos/*.sha256; do
    [ -f "$manifiesto" ] || continue
    faltan=0
    while read -r _suma ruta; do
        [ -f "$ruta" ] || faltan=$((faltan + 1))
    done <"$manifiesto"
    if [ "$faltan" -gt 0 ]; then
        echo "⚠️  AVISO: faltan $faltan ficheros de $(basename "$manifiesto" .sha256) (Internet Archive)."
        echo "    Desde el host: make rehidratar   (o bin/rehidratar-assets.sh)"
    fi
done

if [ ! -f storage/app/manual/index.html ]; then
    echo "⚠️  AVISO: falta el manual del staff (/dashboard/manual). Desde el host: make rehidratar"
fi

if [ ! -f public/vendor/fontawesome/icon-index.json ]; then
    echo "Índice del selector de iconos ausente: regenerando..."
    php artisan icon:build-index || echo "WARN: icon:build-index falló (no fatal)"
fi

if [ ! -f public/retroarch/assets/cores/.index-xhr ]; then
    echo "Catálogo de RetroArch ausente: regenerando..."
    php artisan retroarch:scan-roms || echo "WARN: retroarch:scan-roms falló (no fatal)"
fi

exit 0
