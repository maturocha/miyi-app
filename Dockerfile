# Usa la imagen oficial de PHP con FPM
FROM php:7.4.7-fpm

# Configurar repositorios archivados de Debian Buster (EOL)
RUN echo "deb http://archive.debian.org/debian buster main" > /etc/apt/sources.list && \
    echo "deb http://archive.debian.org/debian-security buster/updates main" >> /etc/apt/sources.list && \
    echo "Acquire::Check-Valid-Until false;" > /etc/apt/apt.conf.d/99no-check-valid-until

# Instalar dependencias necesarias
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    git \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd zip pdo pdo_mysql bcmath \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Instalar Xdebug con versión específica compatible con PHP 7.4.7
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

# Configurar git para evitar error de "dubious ownership"
RUN git config --global --add safe.directory /var/www/html

# Configurar Composer para permitir paquetes con vulnerabilidades conocidas (dompdf)
# Nota: Esto es temporal para permitir la instalación de dompdf que tiene vulnerabilidades conocidas
RUN composer config --global audit.ignore '{"dompdf/dompdf":["all"]}' && \
    composer config --global audit.block-insecure false

# Instalar dependencias con Composer
RUN composer update --no-interaction --no-plugins --no-scripts

# Asegurar que www-data tenga permisos sobre el directorio storage
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Exponer el puerto 9000 para el contenedor PHP
EXPOSE 9000

# Comando por defecto al iniciar el contenedor
CMD ["php-fpm"]
