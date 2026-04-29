FROM php:8.4-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    icu-dev \
    oniguruma-dev \
    libzip-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    linux-headers \
    mysql-client \
    netcat-openbsd \
    git \
    shadow \
    nodejs \
    npm \
    gnu-libiconv

# Install PHP extensions
RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        gd \
        intl \
        mbstring \
        pdo_mysql \
        zip \
        bcmath \
        opcache \
        pcntl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Expose port
EXPOSE 9000

# Entrypoints
COPY .docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY .docker/php/entrypoint-scheduler.sh /usr/local/bin/entrypoint-scheduler.sh
COPY .docker/php/entrypoint-worker.sh /usr/local/bin/entrypoint-worker.sh

RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/entrypoint-scheduler.sh /usr/local/bin/entrypoint-worker.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
