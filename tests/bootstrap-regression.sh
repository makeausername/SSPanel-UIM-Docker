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

printf 'bootstrap regression tests passed.\n'
