FROM php:8.3-fpm

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer \
    APP_DIR=/var/www/html

WORKDIR /var/www/html

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        git \
        gosu \
        libcurl4-openssl-dev \
        libonig-dev \
        libxml2-dev \
        libyaml-dev \
        libzip-dev \
        pkg-config \
        unzip \
        zip; \
    docker-php-ext-install -j"$(nproc)" \
        bcmath \
        curl \
        fileinfo \
        mbstring \
        mysqli \
        opcache \
        pcntl \
        pdo_mysql \
        posix \
        xml \
        zip; \
    printf "\n" | pecl install redis; \
    printf "\n" | pecl install yaml; \
    docker-php-ext-enable redis yaml opcache; \
    rm -rf /tmp/pear ~/.pearrc /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-sspanel.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini

COPY . /var/www/html

RUN set -eux; \
    composer install --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist; \
    sed -i 's/\r$//' docker/entrypoint.sh docker/cron/scheduler; \
    mkdir -p \
        storage/framework \
        storage/framework/smarty/cache \
        storage/framework/smarty/compile \
        storage/framework/twig/cache \
        public/clients \
        config; \
    chown -R www-data:www-data \
        storage/framework \
        storage/framework/smarty/cache \
        storage/framework/smarty/compile \
        storage/framework/twig/cache \
        public/clients \
        config; \
    chmod -R ug+rwX \
        storage/framework \
        storage/framework/smarty/cache \
        storage/framework/smarty/compile \
        storage/framework/twig/cache \
        public/clients \
        config; \
    chmod +x docker/entrypoint.sh docker/cron/scheduler

ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["php-fpm"]
