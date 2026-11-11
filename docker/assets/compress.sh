#!/bin/sh
# Build-time .gz siblings for nginx gzip_static.
set -eu
find "$1" -type f \( -name '*.css' -o -name '*.js' -o -name '*.svg' -o -name '*.json' \) -size +256c -exec gzip -9 -k -n -f {} +
