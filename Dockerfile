FROM php:7.4.7-fpm

# php:7.4.7-fpm is Debian buster — official mirrors moved to archive.debian.org
RUN sed -i 's|deb.debian.org|archive.debian.org|g; s|security.debian.org|archive.debian.org|g; /buster-updates/d' /etc/apt/sources.list

RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    git \
    unzip \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        exif \
        gd \
        mbstring \
        opcache \
        pcntl \
        pdo \
        pdo_mysql \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN pecl install xdebug-3.1.6 \
    && docker-php-ext-enable xdebug \
    && { \
    echo "xdebug.mode=develop,debug"; \
    echo "xdebug.idekey=docker"; \
    echo "xdebug.start_with_request=yes"; \
    echo "xdebug.client_port=9003"; \
    echo "xdebug.client_host=host.docker.internal"; \
    echo "xdebug.log=/dev/stdout"; \
    echo "xdebug.log_level=0"; \
    } > /usr/local/etc/php/conf.d/50-xdebug.ini

COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

RUN curl -fsSL -o /usr/local/bin/php-fpm-healthcheck \
        https://raw.githubusercontent.com/renatomefi/php-fpm-healthcheck/v0.5.0/php-fpm-healthcheck \
    && chmod +x /usr/local/bin/php-fpm-healthcheck

RUN mkdir -p /var/www/html/storage/logs \
    /var/www/html/storage/framework/cache \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/bootstrap/cache

COPY --chown=www-data:www-data . /var/www/html/

WORKDIR /var/www/html

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]
