#!/bin/sh
set -e

# Ensure permissions for storage and bootstrap cache
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/storage/app/private/documents

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# If command is worker
if [ "$1" = "worker" ]; then
    echo "[NexusCRM] Starting Queue Worker..."
    exec su-exec www-data php /var/www/html/artisan queue:work --tries=3 --timeout=90 --sleep=3
fi

# If command is scheduler
if [ "$1" = "scheduler" ]; then
    echo "[NexusCRM] Starting Scheduler Daemon..."
    exec su-exec www-data php /var/www/html/artisan schedule:work
fi

# Default: start php-fpm
echo "[NexusCRM] Starting PHP-FPM..."
exec "$@"
