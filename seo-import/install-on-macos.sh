#!/usr/bin/env bash
# Run ON YOUR MAC — creates ~/Projects/zxpress-claude-descriptions/seo-import/
# Usage: bash install-on-macos.sh
set -euo pipefail

TARGET="${1:-$HOME/Projects/zxpress-claude-descriptions/seo-import}"
mkdir -p "$TARGET"

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cp "$SCRIPT_DIR/package.json" "$SCRIPT_DIR/import-articles-meta.mjs" "$SCRIPT_DIR/.env.example" "$SCRIPT_DIR/.gitignore" "$TARGET/"
chmod +x "$TARGET/import-articles-meta.mjs"

if [[ ! -f "$TARGET/.env" ]]; then
  cp "$TARGET/.env.example" "$TARGET/.env"
fi

echo "Installed to $TARGET"
echo "  cd $TARGET && npm install && npm run dry-run"
