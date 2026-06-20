#!/bin/bash
# Mirror packages/core into wp-schemable-validator/vendor for WP Playground.
#
# Playground mounts the plugin directory but cannot follow symlinks outside
# the mount boundary.  This script replaces the composer path-repo symlink
# with a real copy so Playground can access core classes.
#
# Usage:
#   ./playground/mirror-core.sh          # from project root
#   pnpm run mirror-core                 # via playground package.json

set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "$0")/../packages/wp-schemable-validator" && pwd)"
CORE_DIR="$(cd "$(dirname "$0")/../packages/core" && pwd)"
LINK_PATH="$PLUGIN_DIR/vendor/uuki/schemable-validator-core"

# Ensure composer deps are installed first
composer install --no-dev --working-dir="$PLUGIN_DIR" 2>/dev/null

# Replace symlink with a real copy
if [ -L "$LINK_PATH" ]; then
  rm "$LINK_PATH"
  cp -R "$CORE_DIR" "$LINK_PATH"
  echo "Mirrored core → $LINK_PATH"
elif [ -d "$LINK_PATH" ]; then
  rm -rf "$LINK_PATH"
  cp -R "$CORE_DIR" "$LINK_PATH"
  echo "Refreshed core mirror → $LINK_PATH"
else
  echo "Warning: $LINK_PATH not found (run composer install first)"
  exit 1
fi
