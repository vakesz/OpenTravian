#!/bin/sh
# Download the pinned PHPStan PHAR into dev/phpstan/ and verify it.
# Verifies (1) the SHA-256 recorded in phpstan.phar.sha256 and (2) the detached
# GPG signature against the PHPStan release key fingerprint below.
# Host requirements: curl, gpg, sha256sum or shasum. The PHAR is gitignored,
# lives outside every Dockerfile COPY, and is never part of a runtime image.
set -eu

KEY_FPR="CA7C2C7A30C8E8E1274A847651C67305FFC2E5C0"
KEYSERVER="hkps://keys.openpgp.org"

dir=$(cd "$(dirname "$0")" && pwd)
version=$(tr -d '[:space:]' < "$dir/VERSION")
base="https://github.com/phpstan/phpstan/releases/download/$version"
expected=$(cut -d' ' -f1 "$dir/phpstan.phar.sha256")

sha256_of() {
    if command -v sha256sum >/dev/null 2>&1; then sha256sum "$1" | cut -d' ' -f1
    else shasum -a 256 "$1" | cut -d' ' -f1; fi
}

if [ -f "$dir/phpstan.phar" ] && [ "$(sha256_of "$dir/phpstan.phar")" = "$expected" ]; then
    echo "phpstan.phar $version already present and verified."
    exit 0
fi

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT
chmod 700 "$work"

curl -fsSL -o "$work/phpstan.phar" "$base/phpstan.phar"
curl -fsSL -o "$work/phpstan.phar.asc" "$base/phpstan.phar.asc"

actual=$(sha256_of "$work/phpstan.phar")
if [ "$actual" != "$expected" ]; then
    echo "SHA-256 mismatch for phpstan.phar $version: expected $expected, got $actual" >&2
    exit 1
fi

export GNUPGHOME="$work/gnupg"
mkdir -m 700 "$GNUPGHOME"
gpg --batch --quiet --keyserver "$KEYSERVER" --recv-keys "$KEY_FPR"
if ! gpg --batch --status-fd 1 --verify "$work/phpstan.phar.asc" "$work/phpstan.phar" 2>/dev/null \
    | grep -q "^\[GNUPG:\] VALIDSIG .* $KEY_FPR\$"; then
    echo "GPG signature verification failed for phpstan.phar $version" >&2
    exit 1
fi

install -m 0644 "$work/phpstan.phar" "$dir/phpstan.phar"
echo "phpstan.phar $version downloaded; SHA-256 and GPG signature verified."
