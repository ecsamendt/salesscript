#!/usr/bin/env bash
#
# Builds the installable WordPress plugin zip.
#
# WordPress expects the archive to contain exactly one top-level directory
# whose name matches the plugin folder, with the main plugin file directly
# inside it. Run from the repo root:  ./build.sh
#
set -euo pipefail

SLUG="sales-script-builder"
OUT_DIR="dist"

cd "$(dirname "$0")"

# Read the version straight out of the plugin header so the zip name and the
# plugin can never disagree about which release this is.
VERSION="$(grep -m1 '^ \* Version:' "${SLUG}/${SLUG}.php" | awk '{print $3}')"

if [ -z "${VERSION}" ]; then
  echo "Could not read Version from ${SLUG}/${SLUG}.php" >&2
  exit 1
fi

ZIP_PATH="${OUT_DIR}/${SLUG}-${VERSION}.zip"

mkdir -p "${OUT_DIR}"
rm -f "${ZIP_PATH}"

zip -r -q "${ZIP_PATH}" "${SLUG}" \
  -x '*.DS_Store' \
  -x '*/.git/*' \
  -x '*/.idea/*' \
  -x '*__MACOSX*'

echo "Built ${ZIP_PATH}"
unzip -l "${ZIP_PATH}"