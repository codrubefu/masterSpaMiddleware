# Imagine de bază PHP cu Apache
FROM php:8.2-apache

# Instalare dependințe de bază
RUN apt-get update && apt-get install -y \
    gnupg2 \
    wget \
    curl \
    unzip \
    git \
    zip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    ca-certificates \
    && docker-php-ext-install pdo mbstring zip exif pcntl

# Adăugare key și repository Microsoft (compatibil cu Debian 12)
RUN wget -qO- https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor > /usr/share/keyrings/microsoft.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/microsoft.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y msodbcsql18 mssql-tools18 \
    && apt-get install -y unixodbc-dev gcc g++ make autoconf libc-dev pkg-config \
    && yes '' | pecl install pdo_sqlsrv sqlsrv \
    && docker-php-ext-enable pdo_sqlsrv sqlsrv \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Instalare Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Setare document root
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Activare mod_rewrite pentru Laravel
RUN a2enmod rewrite

# Copiere cod sursă (sau utilizați bind mount în docker-compose)
WORKDIR /var/www/html
COPY . .

# Asigurare existență directoare necesare Laravel
RUN mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache

# Instalare dependințe Laravel (poate fi rulat și manual în container)
# RUN composer install --no-dev --optimize-autoloader
