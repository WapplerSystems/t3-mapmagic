#!/usr/bin/env bash
# Builds the frontend renderer (submodule Resources/Public/Lib/MapMagicJs)
# and copies the bundle to Resources/Public/Frontend/, where the plugin and
# the backend editor load it from. Run after changing the renderer, commit
# the copied files together with the submodule pointer.
#
#   Build/build-renderer.sh          check (lint, types, tests) + build + copy
#   Build/build-renderer.sh --fast   build + copy only
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
renderer="$root/Resources/Public/Lib/MapMagicJs"
target="$root/Resources/Public/Frontend"

if [[ ! -f "$renderer/package.json" ]]; then
    echo "Renderer submodule missing, run: git submodule update --init" >&2
    exit 1
fi
command -v bun >/dev/null || { echo "bun is required (https://bun.sh)" >&2; exit 1; }

cd "$renderer"
bun install --frozen-lockfile
if [[ "${1:-}" != "--fast" ]]; then
    make check
fi
bun run build
cp dist/lib/mapmagic.js dist/lib/mapmagic.css "$target/"
echo "Copied renderer bundle to ${target#"$root"/}"
