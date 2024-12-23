# Usa la imagen oficial de PHP con FPM
FROM php:7.3-fpm

# Instalar dependencias necesarias
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    git \
    unzip \
    && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
    && docker-php-ext-install -j$(nproc) gd zip pdo pdo_mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Instalar Xdebug con versión específica compatible con PHP 7.3
RUN pecl install xdebug-3.1.6 && \
    docker-php-ext-enable xdebug

# Configurar Xdebug
RUN echo "zend_extension=xdebug.so" >> /usr/local/etc/php/conf.d/50-xdebug.ini \
    && echo "xdebug.mode=develop,debug" >> /usr/local/etc/php/conf.d/50-xdebug.ini \
    && echo "xdebug.idekey=docker" >> /usr/local/etc/php/conf.d/50-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/50-xdebug.ini \
    && echo "xdebug.log=/dev/stdout" >> /usr/local/etc/php/conf.d/50-xdebug.ini \
    && echo "xdebug.log_level=0" >> /usr/local/etc/php/conf.d/50-xdebug.ini \
    && echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/50-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/50-xdebug.ini

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Crear directorios necesarios
RUN mkdir -p /var/www/html/storage/logs /var/www/html/storage/framework/cache \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/bootstrap/cache

# Copiar los archivos de la aplicación al contenedor
COPY --chown=www-data:www-data . /var/www/html/

# Establecer el directorio de trabajo
WORKDIR /var/www/html

# Instalar dependencias con Composer
RUN composer install --no-interaction --no-plugins --no-scripts

# Asegurar que www-data tenga permisos sobre el directorio storage
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Exponer el puerto 9000 para el contenedor PHP
EXPOSE 9000

# Comando por defecto al iniciar el contenedor
CMD ["php-fpm"]
