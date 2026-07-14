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
}

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
assert_read_tty_shape install.sh
assert_read_tty_shape bootstrap.sh

printf 'installer regression tests passed.\n'
