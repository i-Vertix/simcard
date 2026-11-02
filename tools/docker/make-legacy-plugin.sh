#!/usr/bin/env bash
# Extracts simcard 1.3.1 (commit 456450c) into tools/docker/.legacy/simcard
# for the glpi11 service (upgrade-path tests).
set -euo pipefail
cd "$(dirname "$0")/../.."
dest="tools/docker/.legacy/simcard"
rm -rf "$dest"
mkdir -p "$dest"
git archive 456450c | tar -x -C "$dest"
echo "simcard 1.3.1 extracted to $dest"
