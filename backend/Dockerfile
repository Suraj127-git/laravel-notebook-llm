FROM php:8.4-cli-bookworm

# System deps
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        curl \
        postgresql-client \
        libpq-dev \
        libzip-dev \
        libonig-dev \
        libxml2-dev \
        libgd-dev \
        libfreetype-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        zip \
        mbstring \
        xml \
        dom \
        pcntl \
        bcmath \
        opcache \
        gd \
        exif \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP dependencies (cached layer — reruns only when composer.json/lock changes)
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --no-interaction --prefer-dist

# Copy application source
COPY . .

# Finish composer setup (autoloader, post-install scripts)
RUN composer dump-autoload --optimize

# Entrypoint
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint \
    && chmod +x /usr/local/bin/docker-entrypoint

EXPOSE 8000

ENTRYPOINT ["docker-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
