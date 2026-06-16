FROM php:8.2-apache

# Install SQLite3 development packages
RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/*

# Install and enable pdo_sqlite
RUN docker-php-ext-install pdo pdo_sqlite

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# We will mount /var/www/html via docker-compose, but we need a writable data folder
RUN mkdir -p /var/www/data && chown -R www-data:www-data /var/www/data
