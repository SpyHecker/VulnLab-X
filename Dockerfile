FROM php:8.2-apache

# pdo_sqlite backs the database and libxml (bundled with PHP) powers the
# seller XML import, so only the SQLite extension needs to be compiled in.
RUN apt-get update \
    && apt-get install -y --no-install-recommends sqlite3 libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

ENV DB_PATH=/var/www/data/vulnmart.db

# Application code. docker compose bind-mounts ./src over this directory for
# live editing, but a plain `docker run` of the image works too.
COPY src/ /var/www/html/
COPY docker/entrypoint.sh /usr/local/bin/vulnmart-entrypoint

RUN chmod +x /usr/local/bin/vulnmart-entrypoint \
    && mkdir -p /var/www/data /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/data /var/www/html/uploads

EXPOSE 80

ENTRYPOINT ["vulnmart-entrypoint"]
CMD ["apache2-foreground"]
