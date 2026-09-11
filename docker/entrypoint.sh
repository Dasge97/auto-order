#!/bin/sh
set -e

# Espera a que la base de datos acepte conexiones antes de seguir.
tries=0
until php bin/console dbal:run-sql "SELECT 1" >/dev/null 2>&1; do
    tries=$((tries + 1))
    if [ "$tries" -ge 30 ]; then
        echo "La base de datos no responde despues de 30 intentos." >&2
        exit 1
    fi
    sleep 2
done

php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
php bin/console cache:clear --no-warmup
php bin/console cache:warmup

mkdir -p var/uploads
chown -R www-data:www-data var

exec "$@"
