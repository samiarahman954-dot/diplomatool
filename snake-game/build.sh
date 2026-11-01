#!/usr/bin/env bash
# Cross-compiles Snake.exe and Snake-Setup.exe for Windows from Linux.
# Needs: gcc-mingw-w64-x86-64, nsis   (apt-get install gcc-mingw-w64-x86-64 nsis)
set -euo pipefail
cd "$(dirname "$0")"
CC=${CC:-x86_64-w64-mingw32-gcc}
WINDRES=${WINDRES:-x86_64-w64-mingw32-windres}
mkdir -p dist build

$WINDRES -I src src/snake.rc -O coff -o build/snake_res.o
$CC -O2 -s -std=c11 -Wall -Wextra -municode -mwindows -static \
    src/snake.c build/snake_res.o -o dist/Snake.exe -lgdi32 -luser32
echo "built dist/Snake.exe"

if command -v makensis >/dev/null; then
  (cd installer && makensis -V2 snake.nsi)
  echo "built dist/Snake-Setup.exe"
else
  echo "makensis not found - skipping installer"
fi
