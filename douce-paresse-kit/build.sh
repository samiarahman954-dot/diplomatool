#!/bin/sh
# Builds the installable plugin zip next to this folder (tests and build files excluded).
set -e
cd "$(dirname "$0")/.."
rm -f douce-paresse-kit.zip
zip -rq douce-paresse-kit.zip douce-paresse-kit -x 'douce-paresse-kit/tests/*' 'douce-paresse-kit/build.sh'
echo "built $(pwd)/douce-paresse-kit.zip"
