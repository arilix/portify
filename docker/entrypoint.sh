#!/bin/sh
set -eu

cd /var/www/html

if [ -z "${APP_KEY:-}" ]; then
    echo "ERROR: APP_KEY belum diisi. Buat key sesuai petunjuk di DOCKER_VM.md." >&2
    exit 1
fi

mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

if [ "${DB_CONNECTION:-mysql}" = "mysql" ] || [ "${DB_CONNECTION:-mysql}" = "mariadb" ]; then
    db_host="${DB_HOST:-database}"
    db_port="${DB_PORT:-3306}"
    max_attempts="${DB_WAIT_RETRIES:-30}"
    wait_interval="${DB_WAIT_INTERVAL:-2}"
    attempt=1

    echo "Menunggu database di ${db_host}:${db_port}..."

    until php -r '
        $dsn = sprintf(
            "mysql:host=%s;port=%s;dbname=%s",
            getenv("DB_HOST") ?: "database",
            getenv("DB_PORT") ?: "3306",
            getenv("DB_DATABASE") ?: "portify"
        );
        new PDO(
            $dsn,
            getenv("DB_USERNAME") ?: "portify",
            getenv("DB_PASSWORD") ?: "",
            [PDO::ATTR_TIMEOUT => 2]
        );
    ' >/dev/null 2>&1; do
        if [ "$attempt" -ge "$max_attempts" ]; then
            echo "ERROR: database ${db_host}:${db_port} belum siap setelah ${max_attempts} percobaan." >&2
            exit 1
        fi

        attempt=$((attempt + 1))
        sleep "$wait_interval"
    done
fi

php artisan config:clear
php artisan route:clear
php artisan view:clear

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force
fi

if [ "${RUN_SEEDERS:-true}" = "true" ]; then
    php artisan db:seed --class="${SEEDER_CLASS:-TemplateSeeder}" --force
fi

if [ ! -e public/storage ] && [ ! -L public/storage ]; then
    php artisan storage:link
fi
php artisan config:cache
php artisan view:cache

exec "$@"
