#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/html}"

WRITABLE_DIRS=(
    "${APP_DIR}/storage/framework"
    "${APP_DIR}/storage/framework/smarty/cache"
    "${APP_DIR}/storage/framework/smarty/compile"
    "${APP_DIR}/storage/framework/twig/cache"
    "${APP_DIR}/public/clients"
    "${APP_DIR}/config"
)

for dir in "${WRITABLE_DIRS[@]}"; do
    mkdir -p "${dir}"
done

if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data "${WRITABLE_DIRS[@]}"
    chmod -R ug+rwX "${WRITABLE_DIRS[@]}"
    exec gosu www-data docker-php-entrypoint "$@"
fi

chmod -R ug+rwX "${WRITABLE_DIRS[@]}" 2>/dev/null || true
exec docker-php-entrypoint "$@"
