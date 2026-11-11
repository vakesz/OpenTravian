#!/bin/sh
# Lossless image recompression for the web image. Repository originals are
# untouched; only the copies in <out-root> are rewritten.
#   PNG  oxipng (pixel-identical; safe metadata chunks stripped, colour chunks kept)
#   JPEG jpegtran (Huffman optimisation + progressive scan; ICC kept; same DCT data)
# WebP and GIF are deliberately skipped: no lossless-safe recompressor is shipped.
set -eu
root=$1
before=$(du -sk "$root" | cut -f1)
find "$root" -type f -name '*.png' -print0 | xargs -0 -n 64 -P "$(nproc)" oxipng -q -o 2 --strip safe
find "$root" -type f \( -name '*.jpg' -o -name '*.jpeg' \) -print0 | xargs -0 -n 1 -P "$(nproc)" sh -c '
    jpegtran -optimize -progressive -copy icc -outfile "$0.tmp" "$0" && [ "$(wc -c <"$0.tmp")" -lt "$(wc -c <"$0")" ] && mv "$0.tmp" "$0" || rm -f "$0.tmp"'
after=$(du -sk "$root" | cut -f1)
echo "images: ${before} KiB -> ${after} KiB"
