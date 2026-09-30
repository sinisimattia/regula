#!/bin/sh
set -e

# Only the main app process needs a fresh cache — `docker compose exec app ...` one-offs don't
# go through this entrypoint at all, so this only covers php-fpm and any `php artisan ...` CMD
# (workers, scheduler).
if [ "$1" = "php-fpm" ] || { [ "$1" = "php" ] && [ "$2" = "artisan" ]; }; then
    php artisan optimize
    php artisan filament:optimize
fi

exec "$@"
