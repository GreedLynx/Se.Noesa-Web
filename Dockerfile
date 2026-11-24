
FROM php:8.5-apache

# Instalasi ekstensi PostgreSQL dan kebutuhan Composer
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-install \
    pdo_pgsql \
    pgsql \
    zip \
    && rm -rf /var/lib/apt/lists/*

# Aktifkan Apache rewrite
RUN a2enmod rewrite

# Arahkan document root ke folder public
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

# Salin proyek
WORKDIR /var/www/html
COPY . .

# Instal dependency PHP
RUN composer install --no-dev --optimize-autoloader \
    || true

EXPOSE 80
