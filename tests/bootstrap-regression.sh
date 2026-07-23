#!/usr/bin/env bash
set -Eeuo pipefail

TEST_SOURCE="${BASH_SOURCE[0]:-}"
[ -n "$TEST_SOURCE" ] || {
    printf 'ERROR: this regression test must be executed from its file path.\n' >&2
    exit 1
}

ROOT_DIR="$(cd "$(dirname "$TEST_SOURCE")/.." && pwd -P)"
cd "$ROOT_DIR"

assert_help_output() {
    local label="$1"
    local output="$2"

    if [[ "$output" == *"unbound variable"* ]]; then
        printf 'ERROR: %s reported an unbound variable.\n%s\n' "$label" "$output" >&2
        return 1
    fi
    if [[ "$output" != *"Usage:"* ]]; then
        printf 'ERROR: %s did not print bootstrap help.\n%s\n' "$label" "$output" >&2
        return 1
    fi
    if [[ "$output" != *"--resume"* ]]; then
        printf 'ERROR: %s did not document --resume.\n%s\n' "$label" "$output" >&2
        return 1
    fi
}

assert_file_contains() {
    local file="$1"
    local expected="$2"

    grep -Fq -- "$expected" "$file" || {
        printf 'ERROR: %s does not contain required text: %s\n' "$file" "$expected" >&2
        return 1
    }
}

function_body() {
    local function_name="$1"
    local file="$2"

    sed -n "/^${function_name}() {$/,/^}$/p" "$file"
}

assert_function_contains() {
    local function_name="$1"
    local file="$2"
    local expected="$3"
    local body

    body="$(function_body "$function_name" "$file")"
    [[ "$body" == *"$expected"* ]] || {
        printf 'ERROR: %s in %s does not contain required text: %s\n' "$function_name" "$file" "$expected" >&2
        return 1
    }
}

assert_function_excludes() {
    local function_name="$1"
    local file="$2"
    local forbidden="$3"
    local body

    body="$(function_body "$function_name" "$file")"
    [[ "$body" != *"$forbidden"* ]] || {
        printf 'ERROR: %s in %s contains forbidden text: %s\n' "$function_name" "$file" "$forbidden" >&2
        return 1
    }
}

assert_function_order() {
    local function_name="$1"
    local file="$2"
    local first="$3"
    local second="$4"
    local body
    local first_line
    local second_line

    body="$(function_body "$function_name" "$file")"
    first_line="$(printf '%s\n' "$body" | grep -nF -- "$first" | head -n 1 | cut -d: -f1)"
    second_line="$(printf '%s\n' "$body" | grep -nF -- "$second" | head -n 1 | cut -d: -f1)"

    [ -n "$first_line" ] && [ -n "$second_line" ] && [ "$first_line" -lt "$second_line" ] || {
        printf 'ERROR: %s in %s does not place %s before %s.\n' \
            "$function_name" "$file" "$first" "$second" >&2
        return 1
    }
}

assert_mode() {
    local expected="$1"
    local path="$2"
    local actual

    actual="$(stat -c '%a' "$path")"
    [ "$actual" = "$expected" ] || {
        printf 'ERROR: expected mode %s for %s, got %s.\n' "$expected" "$path" "$actual" >&2
        return 1
    }
}

assert_git_mode() {
    local expected="$1"
    local path="$2"
    local actual

    actual="$(git ls-files -s -- "$path" | sed -n 's/^\([^ ]*\).*/\1/p')"
    [ "$actual" = "$expected" ] || {
        printf 'ERROR: expected Git mode %s for %s, got %s.\n' "$expected" "$path" "$actual" >&2
        return 1
    }
}

assert_php_config_nonempty() {
    local key="$1"
    local file="$2"

    grep -Eq "^[[:space:]]*\\\$_ENV\['${key}'\][[:space:]]*=[[:space:]]*'[^']+';" "$file" || {
        printf 'ERROR: %s must provide a non-empty %s default.\n' "$file" "$key" >&2
        return 1
    }
}

test_geoip_config_sync() {
    local temp_dir
    local defaults
    local target

    temp_dir="$(mktemp -d)"
    defaults="${temp_dir}/defaults.php"
    target="${temp_dir}/target.php"
    trap 'rm -rf -- "$temp_dir"' RETURN

    printf '%s\n' \
        "\$_ENV['maxmind_account_id'] = '12345';" \
        "\$_ENV['maxmind_license_key'] = 'test_key_mmk';" \
        "\$_ENV['geoip_locale'] = 'zh-CN';" > "$defaults"
    printf '%s\n' \
        "\$_ENV['maxmind_account_id'] = '';" \
        "\$_ENV['maxmind_license_key'] = '';" \
        "\$_ENV['geoip_locale'] = 'en';" > "$target"

    bash docker/geoip/sync-config.sh "$defaults" "$target" >/dev/null
    assert_file_contains "$target" "\$_ENV['maxmind_account_id'] = '12345';"
    assert_file_contains "$target" "\$_ENV['maxmind_license_key'] = 'test_key_mmk';"
    assert_file_contains "$target" "\$_ENV['geoip_locale'] = 'zh-CN';"

    printf '%s\n' \
        "\$_ENV['maxmind_account_id'] = '67890';" \
        "\$_ENV['maxmind_license_key'] = 'custom_key_mmk';" \
        "\$_ENV['geoip_locale'] = 'ja';" > "$target"
    bash docker/geoip/sync-config.sh "$defaults" "$target" >/dev/null
    assert_file_contains "$target" "\$_ENV['maxmind_account_id'] = '67890';"
    assert_file_contains "$target" "\$_ENV['maxmind_license_key'] = 'custom_key_mmk';"
    assert_file_contains "$target" "\$_ENV['geoip_locale'] = 'ja';"

    rm -rf -- "$temp_dir"
    trap - RETURN
}

create_strict_clone() {
    local target="$1"
    local scheduler_mode
    local public_mode
    local public_file_mode

    (
        umask 077
        git clone --quiet --no-local "$ROOT_DIR" "$target"
        printf 'secret\n' > "${target}/.env"
        printf '<?php // secret\n' > "${target}/config/.config.php"
        printf 'recovery secret\n' > "${target}/eziplc-panel-recovery-test.txt"
    )

    scheduler_mode="$(stat -c '%a' "${target}/docker/cron/scheduler")"
    public_mode="$(stat -c '%a' "${target}/public")"
    public_file_mode="$(stat -c '%a' "${target}/public/index.php")"
    if { [ "$scheduler_mode" != "600" ] && [ "$scheduler_mode" != "700" ]; } \
        || [ "$public_mode" != "700" ] \
        || [ "$public_file_mode" != "600" ]; then
        STRICT_UMASK_SUPPORTED="false"
        printf 'SKIP: filesystem does not expose strict umask clone modes (scheduler=%s, public=%s, public/index.php=%s).\n' \
            "$scheduler_mode" "$public_mode" "$public_file_mode" >&2
        return 0
    fi

    assert_mode 600 "${target}/.env"
    assert_mode 600 "${target}/config/.config.php"
    assert_mode 600 "${target}/eziplc-panel-recovery-test.txt"
}

assert_normalized_clone() {
    local target="$1"

    assert_mode 755 "${target}/docker/cron/scheduler"
    assert_mode 755 "${target}/docker/entrypoint.sh"
    assert_mode 755 "${target}/public"
    assert_mode 644 "${target}/public/index.php"
    assert_mode 600 "${target}/.env"
    assert_mode 600 "${target}/config/.config.php"
    assert_mode 600 "${target}/eziplc-panel-recovery-test.txt"

    test -r "${target}/docker/cron/scheduler"
    test -r "${target}/public/index.php"
}

test_strict_umask_normalization() (
    local temp_dir
    local bootstrap_clone
    local install_clone
    local STRICT_UMASK_SUPPORTED="true"

    temp_dir="$(mktemp -d)"
    chmod 0755 "$temp_dir"
    bootstrap_clone="${temp_dir}/bootstrap-repo"
    install_clone="${temp_dir}/install-repo"
    trap 'rm -rf -- "$temp_dir"' EXIT

    create_strict_clone "$bootstrap_clone"
    [ "$STRICT_UMASK_SUPPORTED" = "true" ] || return 0
    (
        # shellcheck disable=SC1091
        source "${ROOT_DIR}/bootstrap.sh"
        trap - EXIT
        INSTALL_DIR="$bootstrap_clone"
        normalize_repository_permissions
    )
    assert_normalized_clone "$bootstrap_clone"

    create_strict_clone "$install_clone"
    [ "$STRICT_UMASK_SUPPORTED" = "true" ] || return 0
    (
        # shellcheck disable=SC1091
        source "${ROOT_DIR}/install.sh"
        trap - EXIT
        INSTALL_DIR="$install_clone"
        normalize_repository_permissions
    )
    assert_normalized_clone "$install_clone"
)

test_reader() {
    local variable_name="$1"
    local input_value="EzIPLC"
    printf -v "$variable_name" '%s' "$input_value"
}

test_caller() {
    local value
    test_reader value
    test "$value" = "EzIPLC"
}

test_other_return_targets() {
    local first
    local second
    local answer
    local confirmation

    test_reader first
    test_reader second
    test_reader answer
    test_reader confirmation

    test "$first" = "EzIPLC"
    test "$second" = "EzIPLC"
    test "$answer" = "EzIPLC"
    test "$confirmation" = "EzIPLC"
}

assert_read_tty_shape() {
    local script="$1"
    local body

    body="$(sed -n '/^read_tty() {$/,/^}$/p' "$script")"
    [[ "$body" == *'local input_value=""'* ]] || {
        printf 'ERROR: %s does not initialize the read_tty input variable.\n' "$script" >&2
        return 1
    }
    [[ "$body" == *'IFS= read -r -s -p "$prompt" input_value </dev/tty'* ]] || {
        printf 'ERROR: %s secret input does not use input_value.\n' "$script" >&2
        return 1
    }
    [[ "$body" == *'IFS= read -r -p "$prompt" input_value </dev/tty'* ]] || {
        printf 'ERROR: %s plain input does not use input_value.\n' "$script" >&2
        return 1
    }
    [[ "$body" == *'printf -v "$variable_name" '\''%s'\'' "$input_value"'* ]] || {
        printf 'ERROR: %s does not return input_value to the caller.\n' "$script" >&2
        return 1
    }
}

if ! file_output="$(bash bootstrap.sh --help 2>&1)"; then
    printf 'ERROR: bash bootstrap.sh --help failed.\n%s\n' "$file_output" >&2
    exit 1
fi
assert_help_output "file execution" "$file_output"

if ! pipe_output="$(cat bootstrap.sh | bash -s -- --help 2>&1)"; then
    printf 'ERROR: piped bootstrap.sh --help failed.\n%s\n' "$pipe_output" >&2
    exit 1
fi
assert_help_output "pipeline execution" "$pipe_output"

test_caller
test_other_return_targets
assert_git_mode 100755 bootstrap.sh
assert_git_mode 100755 install.sh
assert_git_mode 100755 docker/entrypoint.sh
assert_git_mode 100755 docker/cron/scheduler
test_strict_umask_normalization
test_geoip_config_sync
assert_read_tty_shape install.sh
assert_read_tty_shape bootstrap.sh
assert_file_contains docker-compose.yml '      - bash'
assert_file_contains docker-compose.yml '      - /var/www/html/docker/cron/scheduler'
assert_file_contains docker-compose.yml 'scheduler.last_success'
assert_file_contains docker-compose.yml 'SCHEDULER_HEARTBEAT_MAX_AGE_SECONDS'
assert_file_contains docker-compose.yml 'geoip_city:/var/www/html/storage/GeoLite2-City'
assert_file_contains docker-compose.yml 'geoip_country:/var/www/html/storage/GeoLite2-Country'
assert_file_contains docker-compose.yml 'test \"$$(wget -q -O - http://127.0.0.1/healthz)\" = \"ok\"'
assert_file_contains app/routes.php "\$app->get('/healthz', App\\Controllers\\HealthController::class . ':index');"
assert_file_contains docker/cron/scheduler 'HEARTBEAT_FILE='
assert_file_contains docker/cron/scheduler 'php xcat Cron'
assert_file_contains docker/cron/scheduler 'php xcat Tool updateGeoIP2'
assert_file_contains docker/cron/scheduler 'GEOIP_UPDATE_INTERVAL_SECONDS'
assert_php_config_nonempty maxmind_account_id config/.config.example.php
assert_php_config_nonempty maxmind_license_key config/.config.example.php
assert_file_contains config/.config.example.php "\$_ENV['geoip_locale'] = 'zh-CN';"
assert_file_contains Dockerfile 'bash -n docker/entrypoint.sh; \'
assert_file_contains Dockerfile 'bash -n docker/cron/scheduler; \'
assert_file_contains Dockerfile 'test -r docker/cron/scheduler; \'
assert_file_contains Dockerfile 'gosu www-data test -r /var/www/html/docker/cron/scheduler; \'
assert_file_contains Dockerfile 'gosu www-data test -x /var/www/html/public; \'
assert_file_contains Dockerfile 'gosu www-data test -r /var/www/html/src/Utils/Tools.php; \'
assert_file_contains Dockerfile 'gosu www-data test ! -w /var/www/html/src/Utils/Tools.php; \'
assert_function_contains clone_repository bootstrap.sh 'normalize_repository_permissions'
assert_function_contains update_repository bootstrap.sh 'normalize_repository_permissions'
assert_function_contains run_upgrade bootstrap.sh 'docker compose stop scheduler'
assert_function_contains run_upgrade bootstrap.sh 'docker compose stop caddy nginx app'
assert_function_contains run_upgrade bootstrap.sh 'docker compose up -d mariadb redis'
assert_function_contains run_upgrade bootstrap.sh 'create_upgrade_backup'
assert_function_contains run_upgrade bootstrap.sh 'sync_geoip_defaults'
assert_function_contains run_upgrade bootstrap.sh 'docker compose run --rm -T app php xcat Migration latest'
assert_function_contains run_upgrade bootstrap.sh 'docker compose up -d app nginx caddy'
assert_function_contains run_upgrade bootstrap.sh 'update_geoip_database'
assert_function_contains run_upgrade bootstrap.sh 'docker compose up -d scheduler'
assert_function_contains restart_service_bounded bootstrap.sh 'timeout --foreground --kill-after=5s'
assert_function_contains restart_service_bounded bootstrap.sh 'docker compose restart --no-deps --timeout'
assert_function_contains restart_service_bounded bootstrap.sh 'docker compose logs --tail=100 "$service"'
assert_function_contains run_upgrade bootstrap.sh 'restart_service_bounded nginx 45 10'
assert_function_excludes run_upgrade bootstrap.sh 'docker compose restart nginx'
assert_function_contains run_upgrade bootstrap.sh 'wait_for_service_ready nginx 180'
assert_function_contains run_upgrade bootstrap.sh 'wait_for_service_ready scheduler 300'
assert_function_order run_upgrade bootstrap.sh 'create_upgrade_backup' 'php xcat Migration latest'
assert_function_order run_upgrade bootstrap.sh 'php xcat Migration latest' 'docker compose up -d scheduler'
assert_function_order run_upgrade bootstrap.sh 'docker compose build' 'docker compose stop caddy nginx app'
assert_function_order run_upgrade bootstrap.sh 'docker compose stop caddy nginx app' 'php xcat Migration latest'
assert_function_order run_upgrade bootstrap.sh 'update_geoip_database' 'docker compose up -d scheduler'
assert_function_order run_upgrade bootstrap.sh 'docker compose up -d scheduler' 'restart_service_bounded nginx 45 10'
assert_function_contains build_images install.sh 'normalize_repository_permissions'
assert_function_contains build_images install.sh 'verify_repository_permissions'
assert_function_contains require_runtime install.sh 'require_command git'
assert_function_contains verify_containers install.sh 'local timeout_seconds=60'
assert_function_contains verify_containers install.sh 'sleep 3'
assert_function_contains verify_containers install.sh 'docker compose logs --tail=100 "$service"'
assert_function_contains verify_containers install.sh 'ExitCode=${exit_code:-unknown}'
assert_function_contains resume_installation install.sh 'run_init_command Migration latest'
assert_function_contains resume_installation install.sh 'sync_geoip_defaults'
assert_function_contains resume_installation install.sh 'update_geoip_database'
assert_function_contains resume_installation install.sh 'docker_compose_up scheduler'
assert_function_order resume_installation install.sh 'run_init_command Migration latest' 'docker_compose_up scheduler'
assert_function_order resume_installation install.sh 'update_geoip_database' 'docker_compose_up scheduler'
assert_function_contains main install.sh 'docker_compose_up scheduler'
assert_function_contains main install.sh 'update_geoip_database'
assert_function_order main install.sh 'run_init_command Migration latest' 'docker_compose_up scheduler'
assert_function_order main install.sh 'update_geoip_database' 'docker_compose_up scheduler'
assert_function_excludes resume_installation install.sh 'Migration new'
assert_function_contains resume_installation install.sh 'ensure_admin_for_resume'
assert_function_contains resume_installation install.sh 'verify_https'
assert_function_contains resume_installation install.sh 'write_install_lock'
assert_function_excludes resume_installation install.sh 'down -v'
assert_function_excludes write_env_file install.sh 'ADMIN_PASSWORD'
assert_function_excludes write_credentials_document install.sh 'GITHUB_TOKEN'

printf 'installer regression tests passed.\n'
