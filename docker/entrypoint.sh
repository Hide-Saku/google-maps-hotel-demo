#!/bin/sh
set -e
mkdir -p /var/www/html/logs
chown -R www-data:www-data /var/www/html/logs 2>/dev/null || true
php /var/www/html/scripts/bootstrap.php
exec docker-php-entrypoint "$@"
