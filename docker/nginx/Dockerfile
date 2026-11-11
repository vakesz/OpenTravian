# The repository stores readable assets only. This stage minifies and losslessly
# recompresses a copy of them for the image; development bind-mounts the
# readable checkout instead and never runs it.
FROM node:24-alpine@sha256:a34ab6d7f6f4813f4d0c5469d74685604b962fbdbf5793a7bc7b4998ecb867c8 AS assets
# Lossless image tools. oxipng comes from a pinned GitHub release verified by
# SHA-256 (release assets are permanent, unlike Alpine package revisions);
# jpegtran comes from the exact Alpine package of the digest-pinned base. See
# docs/development.md "Refreshing image-build pins" if the mirror drops that revision.
ARG TARGETARCH
ARG OXIPNG_VERSION=9.1.5
ARG OXIPNG_SHA256_AMD64=571e69fcb06a3675fe247998d845619075631b01ac2246e8a88206c723a472e2
ARG OXIPNG_SHA256_ARM64=5c6c1ed8d0b2817a5800c89acdf16d64874b6a001634aea26edc5a2b7dbef29a
RUN apk add --no-cache libjpeg-turbo-utils=3.1.3-r0 \
    && case "$TARGETARCH" in \
         amd64) arch=x86_64; sha=$OXIPNG_SHA256_AMD64 ;; \
         arm64) arch=aarch64; sha=$OXIPNG_SHA256_ARM64 ;; \
         *) echo "unsupported TARGETARCH: $TARGETARCH" >&2; exit 1 ;; \
       esac \
    && file="oxipng-${OXIPNG_VERSION}-${arch}-unknown-linux-musl.tar.gz" \
    && wget -q -O "/tmp/$file" "https://github.com/oxipng/oxipng/releases/download/v${OXIPNG_VERSION}/$file" \
    && echo "$sha  /tmp/$file" | sha256sum -c - \
    && tar -xzf "/tmp/$file" -C /tmp \
    && install -m 0755 "/tmp/oxipng-${OXIPNG_VERSION}-${arch}-unknown-linux-musl/oxipng" /usr/local/bin/oxipng \
    && rm -rf /tmp/oxipng-* /tmp/"$file"
WORKDIR /build
COPY docker/assets/package.json docker/assets/package-lock.json ./
RUN npm ci --ignore-scripts
COPY docker/assets/build.mjs docker/assets/optimize-images.sh docker/assets/compress.sh ./
COPY assets/gpack/ /out/gpack/
COPY game/deployment/public/ /out/public/
RUN node build.mjs /out && sh optimize-images.sh /out && sh compress.sh /out

FROM nginx:stable-alpine@sha256:0e52bd054126cdc040b1276c0af347aeb02a41b9cfe305d6094767ac30e97949

COPY docker/nginx/default.conf.template /etc/nginx/templates/default.conf.template
COPY docker/nginx/15-travian-allowed-hosts.envsh /docker-entrypoint.d/15-travian-allowed-hosts.envsh
COPY --from=assets /out/public/ /travian/game/deployment/public/
COPY --from=assets /out/gpack/ /travian/assets/gpack/
