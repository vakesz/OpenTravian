# Extensions are compiled here so the toolchain and every -dev package stay out
# of the runtime image.
FROM php:8.5-fpm-bookworm@sha256:eec275cd99580e0a6e103dd343a7c160c86917003f6b9bba2d4c75d82419c683 AS extensions

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        gd \
        intl \
        mysqli \
        pcntl \
        pdo_mysql \
    && pecl install redis \
    && docker-php-ext-enable redis

FROM php:8.5-fpm-bookworm@sha256:eec275cd99580e0a6e103dd343a7c160c86917003f6b9bba2d4c75d82419c683 AS runtime

ARG APP_UID=1000
ARG APP_GID=1000

# Only the shared libraries the compiled extensions link against, plus the
# FastCGI health-check client and the MariaDB client the backup tooling uses.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype6 \
        libicu72 \
        libjpeg62-turbo \
        libpng16-16 \
        libwebp7 \
        libfcgi-bin \
        mariadb-client \
        gzip \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*

COPY --from=extensions /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=extensions /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Fail the build if an extension lost a shared library or does not load.
RUN ! ldd /usr/local/bin/php /usr/local/sbin/php-fpm /usr/local/lib/php/extensions/*/*.so | grep -q "not found" \
    && php -m | grep -qix redis \
    && php -m | grep -qix gd

# macOS commonly uses GID 20, which is already `dialout` in Debian. The build
# keeps the requested numeric primary group instead of failing because its name
# is already occupied in the base image.
RUN if getent group "$APP_GID" >/dev/null; then \
        usermod --uid "$APP_UID" --gid "$APP_GID" www-data; \
    else \
        groupmod --gid "$APP_GID" www-data; \
        usermod --uid "$APP_UID" --gid "$APP_GID" www-data; \
    fi

# Generated state (config, lock, logs, backups) lives in a named volume. The
# directory exists and is owned by www-data so a fresh volume inherits that.
RUN mkdir -p /var/lib/travian && chown www-data:www-data /var/lib/travian \
    && chmod 0750 /var/lib/travian
ENV TRAVIAN_STATE_DIR=/var/lib/travian

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-travian.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-travian.conf
# Pool sizing is generated at start-up by the entrypoint into /run/travian so the
# root filesystem can stay read-only.
RUN mkdir -p /run/travian \
    && ln -s /run/travian/fpm-tuning.conf /usr/local/etc/php-fpm.d/zz-travian-tuning.conf
COPY --chmod=0755 docker/php/entrypoint.sh /usr/local/bin/travian-entrypoint
COPY docker/php/engine-healthcheck.php /usr/local/bin/travian-engine-healthcheck.php

# Redefining ENTRYPOINT clears the base image's CMD, so restate it.
ENTRYPOINT ["travian-entrypoint"]
CMD ["php-fpm"]

# Deployment images contain exactly the runtime paths used by PHP. Development
# stops at the runtime stage and bind-mounts the checkout instead.
FROM runtime AS production

WORKDIR /travian
# Release builds record their version for the footer label (Game\LegalPages).
ARG TRAVIAN_VERSION=
RUN if [ -n "$TRAVIAN_VERSION" ]; then printf '%s\n' "$TRAVIAN_VERSION" > /travian/VERSION; fi
COPY --chown=www-data:www-data game/ /travian/game/
COPY --chown=www-data:www-data assets/ /travian/assets/
COPY --chown=www-data:www-data database/ /travian/database/
COPY --chown=www-data:www-data services/ /travian/services/
COPY --chown=www-data:www-data tools/ /travian/tools/
