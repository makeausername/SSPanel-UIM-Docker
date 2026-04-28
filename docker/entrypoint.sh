#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/html}"
PHP_FPM_TEMPLATE="${PHP_FPM_TEMPLATE:-/usr/local/etc/php-fpm.d/www.conf.template}"
PHP_FPM_CONFIG="${PHP_FPM_CONFIG:-/usr/local/etc/php-fpm.d/www.conf}"

normalize_positive_int() {
    local name="$1"
    local default="$2"
    local value="${!name-}"

    if [[ -z "${value}" || "${value}" =~ [^0-9] ]]; then
        echo "${default}"
        return
    fi

    if (( 10#${value} < 1 )); then
        echo "${default}"
        return
    fi

    echo "${value}"
}

normalize_php_fpm_pm() {
    local value="${PHP_FPM_PM:-dynamic}"

    case "${value}" in
        static|dynamic|ondemand)
            echo "${value}"
            ;;
        *)
            echo "dynamic"
            ;;
    esac
}

generate_php_fpm_config() {
    if [ ! -f "${PHP_FPM_TEMPLATE}" ]; then
        return
    fi

    if [ ! -w "$(dirname "${PHP_FPM_CONFIG}")" ]; then
        echo "WARNING: PHP-FPM config directory is not writable; using image defaults." >&2
        return
    fi

    local php_fpm_pm
    local php_fpm_max_children
    local php_fpm_start_servers
    local php_fpm_min_spare_servers
    local php_fpm_max_spare_servers
    local php_fpm_max_requests

    php_fpm_pm="$(normalize_php_fpm_pm)"
    php_fpm_max_children="$(normalize_positive_int PHP_FPM_MAX_CHILDREN 20)"
    php_fpm_start_servers="$(normalize_positive_int PHP_FPM_START_SERVERS 4)"
    php_fpm_min_spare_servers="$(normalize_positive_int PHP_FPM_MIN_SPARE_SERVERS 2)"
    php_fpm_max_spare_servers="$(normalize_positive_int PHP_FPM_MAX_SPARE_SERVERS 8)"
    php_fpm_max_requests="$(normalize_positive_int PHP_FPM_MAX_REQUESTS 500)"

    sed \
        -e "s/{{PHP_FPM_PM}}/${php_fpm_pm}/g" \
        -e "s/{{PHP_FPM_MAX_CHILDREN}}/${php_fpm_max_children}/g" \
        -e "s/{{PHP_FPM_START_SERVERS}}/${php_fpm_start_servers}/g" \
        -e "s/{{PHP_FPM_MIN_SPARE_SERVERS}}/${php_fpm_min_spare_servers}/g" \
        -e "s/{{PHP_FPM_MAX_SPARE_SERVERS}}/${php_fpm_max_spare_servers}/g" \
        -e "s/{{PHP_FPM_MAX_REQUESTS}}/${php_fpm_max_requests}/g" \
        "${PHP_FPM_TEMPLATE}" > "${PHP_FPM_CONFIG}"
}

REQUIRED_DIRS=(
    "${APP_DIR}/storage/framework"
    "${APP_DIR}/storage/framework/smarty/cache"
    "${APP_DIR}/storage/framework/smarty/compile"
    "${APP_DIR}/storage/framework/twig/cache"
    "${APP_DIR}/public/clients"
    "${APP_DIR}/config"
)

WRITABLE_DIRS=(
    "${APP_DIR}/storage/framework"
    "${APP_DIR}/storage/framework/smarty/cache"
    "${APP_DIR}/storage/framework/smarty/compile"
    "${APP_DIR}/storage/framework/twig/cache"
    "${APP_DIR}/public/clients"
)

for dir in "${REQUIRED_DIRS[@]}"; do
    mkdir -p "${dir}"
done

generate_php_fpm_config

if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data "${WRITABLE_DIRS[@]}"
    chmod -R ug+rwX "${WRITABLE_DIRS[@]}"

    case "${1:-}" in
        php-fpm|php-fpm*|-*)
            exec docker-php-entrypoint "$@"
            ;;
        *)
            exec gosu www-data docker-php-entrypoint "$@"
            ;;
    esac
fi

for dir in "${WRITABLE_DIRS[@]}"; do
    if [ ! -w "${dir}" ]; then
        echo "ERROR: Required writable directory is not writable: ${dir}" >&2
        exit 1
    fi
done

exec docker-php-entrypoint "$@"
