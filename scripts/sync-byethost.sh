#!/usr/bin/env bash
# One-click sync: myjknote repo → byethost FTP backup
set -euo pipefail
cd "$(dirname "$0")/.."
exec python3 scripts/sync_byethost.py "$@"
