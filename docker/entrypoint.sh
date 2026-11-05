#!/bin/sh
# The data/ and uploads/ directories are usually bind-mounted from the host, so
# the ownership set at build time is hidden. SQLite needs write access to both
# the directory (for the -journal/-wal files) and the database itself.
set -e

mkdir -p /var/www/data /var/www/html/uploads

if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data /var/www/data /var/www/html/uploads
    chmod -R u+rwX,g+rwX /var/www/data /var/www/html/uploads
fi

exec "$@"
