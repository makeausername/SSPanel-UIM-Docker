#!/usr/bin/env bash

set -Eeuo pipefail

if [[ $# -ne 0 ]]; then
    echo "update.sh no longer accepts branch or release arguments." >&2
    echo "Use: sudo bash bootstrap.sh --upgrade" >&2
    exit 2
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "update.sh is deprecated; forwarding to the safe Docker upgrade workflow."

if [[ ${EUID} -eq 0 ]]; then
    exec bash "${SCRIPT_DIR}/bootstrap.sh" --upgrade
fi

exec sudo bash "${SCRIPT_DIR}/bootstrap.sh" --upgrade
