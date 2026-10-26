#!/bin/sh
set -eu

PORT="${PORT:-10000}"
case "$PORT" in
    ''|*[!0-9]*)
        echo "PORT must contain only digits." >&2
        exit 64
        ;;
esac

sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/*:80/*:${PORT}/g" /etc/apache2/sites-available/uist.conf

php artisan migrate --force

exec apache2-foreground
