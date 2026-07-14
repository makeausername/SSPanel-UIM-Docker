FROM php:8.3-fpm

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer \
    APP_DIR=/var/www/html

WORKDIR /var/www/html

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        autoconf \
        build-essential \
        ca-certificates \
        curl \
        git \
        gosu \
        libcurl4-openssl-dev \
        libgmp-dev \
        libonig-dev \
        libsodium-dev \
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
        gmp \
        mbstring \
        mysqli \
        opcache \
        pcntl \
        pdo_mysql \
        posix \
        xml \
        zip; \
    if ! php -m | grep -qi '^sodium$'; then \
        docker-php-ext-install -j"$(nproc)" sodium; \
    fi; \
    printf "\n" | pecl install redis; \
    printf "\n" | pecl install yaml; \
    docker-php-ext-enable redis yaml opcache; \
    rm -rf /tmp/pear ~/.pearrc /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-sspanel.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf.template

RUN set -eux; \
    sed \
        -e 's/{{PHP_FPM_PM}}/dynamic/g' \
        -e 's/{{PHP_FPM_MAX_CHILDREN}}/20/g' \
        -e 's/{{PHP_FPM_START_SERVERS}}/4/g' \
        -e 's/{{PHP_FPM_MIN_SPARE_SERVERS}}/2/g' \
        -e 's/{{PHP_FPM_MAX_SPARE_SERVERS}}/8/g' \
        -e 's/{{PHP_FPM_MAX_REQUESTS}}/500/g' \
        /usr/local/etc/php-fpm.d/www.conf.template > /usr/local/etc/php-fpm.d/www.conf

COPY . /var/www/html

RUN set -eux; \
    find /var/www/html -xdev -type d -exec chmod 0755 {} +; \
    find /var/www/html -xdev -type f -exec chmod 0644 {} +; \
    chmod 0755 \
        /var/www/html/docker/entrypoint.sh \
        /var/www/html/docker/cron/scheduler; \
    bash -n /var/www/html/docker/entrypoint.sh; \
    bash -n /var/www/html/docker/cron/scheduler; \
    gosu www-data test -r /var/www/html/docker/cron/scheduler; \
    gosu www-data bash -n /var/www/html/docker/cron/scheduler; \
    gosu www-data test -x /var/www/html/public; \
    gosu www-data test -r /var/www/html/public/index.php; \
    gosu www-data test -r /var/www/html/src/Utils/Tools.php; \
    gosu www-data test ! -w /var/www/html/src/Utils/Tools.php; \
    test "$(stat -c '%U:%G' /var/www/html/src/Utils/Tools.php)" = root:root

RUN set -eux; \
    php -v; \
    php -m | sort; \
    php -r '$required = ["bcmath","curl","fileinfo","gmp","json","mbstring","mysqli","openssl","pdo","pdo_mysql","posix","redis","sodium","xml","yaml","zip"]; foreach ($required as $extension) { if (!extension_loaded($extension)) { fwrite(STDERR, "Missing required PHP extension: {$extension}\n"); exit(1); } } if (!extension_loaded("Zend OPcache") && !extension_loaded("opcache")) { fwrite(STDERR, "Missing required PHP extension: opcache\n"); exit(1); }'; \
    composer install --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist; \
    sed -i 's/\r$//' docker/entrypoint.sh docker/cron/scheduler; \
    bash -n docker/entrypoint.sh; \
    bash -n docker/cron/scheduler; \
    test -r docker/cron/scheduler; \
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
        public/clients; \
    chmod -R ug+rwX \
        storage/framework \
        storage/framework/smarty/cache \
        storage/framework/smarty/compile \
        storage/framework/twig/cache \
        public/clients

ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["php-fpm"]
