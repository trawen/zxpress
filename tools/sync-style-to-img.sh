#!/usr/bin/env bash
# Copy canonical CSS from repo-tracked path to site/img (served as /img/style.css).
# Run from repo root after editing site/style.css.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cp "${ROOT}/site/style.css" "${ROOT}/site/img/style.css"
echo "Synced site/style.css -> site/img/style.css"
