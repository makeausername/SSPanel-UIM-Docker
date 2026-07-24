#!/usr/bin/env bash
set -Eeuo pipefail

TEST_SOURCE="${BASH_SOURCE[0]:-}"
[ -n "$TEST_SOURCE" ] || {
    printf 'ERROR: this smoke test must be executed from its file path.\n' >&2
    exit 1
}

ROOT_DIR="$(cd "$(dirname "$TEST_SOURCE")/.." && pwd -P)"
cd "$ROOT_DIR"

for generated_file in .env config/.config.php config/appprofile.php; do
    [ ! -e "$generated_file" ] || {
        printf 'ERROR: refusing to overwrite existing %s.\n' "$generated_file" >&2
        exit 1
    }
done

export COMPOSE_PROJECT_NAME="sspanel-production-smoke"
SMOKE_FAILED="true"

cleanup() {
    local status="$?"

    if [ "$SMOKE_FAILED" = "true" ]; then
        docker compose ps >&2 || true
        docker compose logs --tail=150 app nginx mariadb redis scheduler >&2 || true
    fi
    docker compose down -v --remove-orphans >/dev/null 2>&1 || true
    rm -f -- .env config/.config.php config/appprofile.php
    exit "$status"
}
trap cleanup EXIT

cat > .env <<'EOF'
APP_DOMAIN='localhost'
APP_NAME='SSPanel Production Smoke'
HTTPS_ENABLED='false'
HTTP_PORT='18080'
HTTPS_PORT='18443'
CADDY_SITE_ADDRESS='http://:80'
CADDY_TRUSTED_PROXIES='private_ranges'
DB_DATABASE='sspanel_smoke'
DB_USERNAME='sspanel_smoke'
DB_PASSWORD='111111111111111111111111111111111111111111111111'
DB_ROOT_PASSWORD='222222222222222222222222222222222222222222222222'
REDIS_PASSWORD='333333333333333333333333333333333333333333333333'
TZ='Asia/Shanghai'
EOF

cp config/.config.example.php config/.config.php
cp config/appprofile.example.php config/appprofile.php

sed -i \
    -e "/^\$_ENV\\['key'\\]/c\\\$_ENV['key'] = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';" \
    -e "/^\$_ENV\\['baseUrl'\\]/c\\\$_ENV['baseUrl'] = 'http://localhost';" \
    -e "/^\$_ENV\\['muKey'\\]/c\\\$_ENV['muKey'] = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';" \
    -e "/^\$_ENV\\['db_host'\\]/c\\\$_ENV['db_host'] = 'mariadb';" \
    -e "/^\$_ENV\\['db_database'\\]/c\\\$_ENV['db_database'] = 'sspanel_smoke';" \
    -e "/^\$_ENV\\['db_username'\\]/c\\\$_ENV['db_username'] = 'sspanel_smoke';" \
    -e "/^\$_ENV\\['db_password'\\]/c\\\$_ENV['db_password'] = '111111111111111111111111111111111111111111111111';" \
    -e "/^\$_ENV\\['redis_host'\\]/c\\\$_ENV['redis_host'] = 'redis';" \
    -e "/^\$_ENV\\['redis_password'\\]/c\\\$_ENV['redis_password'] = '333333333333333333333333333333333333333333333333';" \
    -e "/^\$_ENV\\['maxmind_account_id'\\]/c\\\$_ENV['maxmind_account_id'] = '';" \
    -e "/^\$_ENV\\['maxmind_license_key'\\]/c\\\$_ENV['maxmind_license_key'] = '';" \
    config/.config.php
printf "\n\$_ENV['cookie_secure'] = true;\n" >> config/.config.php
chmod 0600 .env
chmod 0640 config/.config.php
if [ "$(id -u)" = "0" ]; then
    chown 0:33 config/.config.php
else
    sudo chown 0:33 config/.config.php
fi

docker compose config >/dev/null
docker compose build app
docker compose up -d mariadb redis
docker compose up -d app nginx

docker compose exec -T app test -f vendor/autoload.php
docker compose exec -T app gosu www-data test -r /var/www/html/config/.config.php
docker compose exec -T app php xcat Migration new
docker compose exec -T app php xcat Migration latest
docker compose exec -T app php xcat Tool importSetting
docker compose exec -T mariadb sh -c \
    'exec mariadb -u root --password="$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE" -e "
        ALTER TABLE \`user\`
        ADD COLUMN IF NOT EXISTS \`ga_enable\` tinyint(1) unsigned NOT NULL DEFAULT 0;
    "'
docker compose exec -T app php xcat Tool createAdmin smoke-admin@example.invalid SmokeAdminPass1234
docker compose exec -T app php xcat Tool ensureAdminOwner
docker compose exec -T app sh -c 'date +%s > storage/framework/geoip.last_success'
docker compose up -d scheduler

wait_for_healthy() {
    local service="$1"
    local timeout_seconds="$2"
    local started_at
    local container_id
    local state

    started_at="$(date +%s)"
    while true; do
        container_id="$(docker compose ps -q "$service")"
        state="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$container_id" 2>/dev/null || true)"
        case "$state" in
            healthy) return 0 ;;
            unhealthy|exited|dead)
                printf 'ERROR: %s reached terminal state %s.\n' "$service" "$state" >&2
                return 1
                ;;
        esac
        if [ $(( "$(date +%s)" - started_at )) -ge "$timeout_seconds" ]; then
            printf 'ERROR: %s did not become healthy within %s seconds.\n' "$service" "$timeout_seconds" >&2
            return 1
        fi
        sleep 3
    done
}

wait_for_healthy nginx 180
wait_for_healthy scheduler 300

health_body="$(docker compose exec -T nginx wget -q -O - http://127.0.0.1/healthz | tr -d '\r\n')"
[ "$health_body" = "ok" ] || {
    printf 'ERROR: health endpoint returned %s.\n' "${health_body:-empty}" >&2
    exit 1
}

latest_migration="$(
    find db/migrations -maxdepth 1 -type f -name '[0-9]*-*.php' -printf '%f\n' \
        | sed 's/-.*//' \
        | sort -n \
        | tail -n 1
)"
database_version="$(
    docker compose exec -T mariadb sh -c \
        'exec mariadb --batch --raw --skip-column-names -u root --password="$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE" -e "
            SELECT value FROM config WHERE item = '\''db_version'\'' LIMIT 1;
        "' \
        | tr -d '[:space:]'
)"
[ "$database_version" = "$latest_migration" ] || {
    printf 'ERROR: database version %s does not match latest migration %s.\n' \
        "${database_version:-empty}" "${latest_migration:-empty}" >&2
    exit 1
}

owner_count="$(
    docker compose exec -T mariadb sh -c \
        'exec mariadb --batch --raw --skip-column-names -u root --password="$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE" -e "
            SELECT COUNT(*) FROM \`user\`
            WHERE is_admin = 1 AND is_banned = 0 AND admin_role = '\''owner'\'';
        "' \
        | tr -d '[:space:]'
)"
[ "$owner_count" -gt 0 ] || {
    printf 'ERROR: no active owner administrator was created.\n' >&2
    exit 1
}

SMOKE_FAILED="false"
printf 'production Docker/MariaDB smoke test passed.\n'
