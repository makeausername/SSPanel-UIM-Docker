#!/usr/bin/env bash
set -Eeuo pipefail

DEFAULT_CONFIG="${1:-config/.config.example.php}"
TARGET_CONFIG="${2:-config/.config.php}"

read_php_string() {
    local key="$1"
    local file="$2"

    sed -n "s/^[[:space:]]*\\\$_ENV\['${key}'\][[:space:]]*=[[:space:]]*'\([^']*\)'.*/\1/p" "$file" \
        | tail -n 1
}

[ -f "$DEFAULT_CONFIG" ] || {
    printf 'ERROR: GeoIP default config does not exist: %s\n' "$DEFAULT_CONFIG" >&2
    exit 1
}
[ -f "$TARGET_CONFIG" ] || {
    printf 'ERROR: GeoIP target config does not exist: %s\n' "$TARGET_CONFIG" >&2
    exit 1
}

default_account_id="$(read_php_string maxmind_account_id "$DEFAULT_CONFIG")"
default_license_key="$(read_php_string maxmind_license_key "$DEFAULT_CONFIG")"
default_locale="$(read_php_string geoip_locale "$DEFAULT_CONFIG")"

[[ "$default_account_id" =~ ^[0-9]+$ ]] || {
    printf 'ERROR: GeoIP default account ID is invalid.\n' >&2
    exit 1
}
[[ "$default_license_key" =~ ^[A-Za-z0-9_]+$ ]] || {
    printf 'ERROR: GeoIP default license key is invalid.\n' >&2
    exit 1
}
[[ "$default_locale" =~ ^[A-Za-z][A-Za-z0-9-]*$ ]] || {
    printf 'ERROR: GeoIP default locale is invalid.\n' >&2
    exit 1
}

current_account_id="$(read_php_string maxmind_account_id "$TARGET_CONFIG")"
current_license_key="$(read_php_string maxmind_license_key "$TARGET_CONFIG")"
current_locale="$(read_php_string geoip_locale "$TARGET_CONFIG")"

replace_account_id=false
replace_license_key=false
replace_locale=false
[ -n "$current_account_id" ] || replace_account_id=true
[ -n "$current_license_key" ] || replace_license_key=true
if [ -z "$current_locale" ] || [ "$current_locale" = 'en' ]; then
    replace_locale=true
fi

if [ "$replace_account_id" = false ] \
    && [ "$replace_license_key" = false ] \
    && [ "$replace_locale" = false ]; then
    printf 'GeoIP configuration already contains deployment values.\n'
    exit 0
fi

target_dir="$(dirname "$TARGET_CONFIG")"
target_name="$(basename "$TARGET_CONFIG")"
target_mode="$(stat -c '%a' "$TARGET_CONFIG")"
temporary="$(mktemp "${target_dir}/.${target_name}.geoip.XXXXXX")"
trap 'rm -f -- "$temporary"' EXIT

while IFS= read -r line || [ -n "$line" ]; do
    case "$line" in
        "\$_ENV['maxmind_account_id'] ="*)
            if [ "$replace_account_id" = true ]; then
                printf "\$_ENV['maxmind_account_id'] = '%s';\n" "$default_account_id"
            else
                printf '%s\n' "$line"
            fi
            ;;
        "\$_ENV['maxmind_license_key'] ="*)
            if [ "$replace_license_key" = true ]; then
                printf "\$_ENV['maxmind_license_key'] = '%s';\n" "$default_license_key"
            else
                printf '%s\n' "$line"
            fi
            ;;
        "\$_ENV['geoip_locale'] ="*)
            if [ "$replace_locale" = true ]; then
                printf "\$_ENV['geoip_locale'] = '%s';\n" "$default_locale"
            else
                printf '%s\n' "$line"
            fi
            ;;
        *) printf '%s\n' "$line" ;;
    esac
done < "$TARGET_CONFIG" > "$temporary"

chmod "$target_mode" "$temporary"
mv -f -- "$temporary" "$TARGET_CONFIG"
trap - EXIT
printf 'GeoIP configuration synchronized from repository defaults.\n'
