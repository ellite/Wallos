# syntax=docker/dockerfile:1.7

ARG PHP_VERSION=8.3
ARG BASE_IMAGE=php:${PHP_VERSION}-fpm-alpine

# ---------------------------------------------------------------------------
# builder: compile PHP extensions (dev headers stay in this stage only)
# ---------------------------------------------------------------------------
FROM ${BASE_IMAGE} AS builder

# hadolint ignore=DL3018,SC2086
RUN --mount=type=cache,target=/var/cache/apk \
    apk add --cache-dir /var/cache/apk --virtual .build-deps \
        $PHPIZE_DEPS \
        freetype-dev \
        icu-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
        sqlite-dev \
    && \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp && \
    docker-php-ext-install -j"$(nproc)" \
        calendar \
        gd \
        intl \
        pdo_sqlite \
        zip

# ---------------------------------------------------------------------------
# src: prepare application files (no layers leak into the runtime image)
# ---------------------------------------------------------------------------
FROM ${BASE_IMAGE} AS src

WORKDIR /src
COPY . .
RUN rm -f nginx.conf nginx.default.conf php-fpm.wallos.conf php.wallos.ini && \
    dos2unix cronjobs startup.sh && \
    chmod +x startup.sh

# ---------------------------------------------------------------------------
# runtime: final image
# ---------------------------------------------------------------------------
FROM ${BASE_IMAGE} AS runtime

LABEL org.opencontainers.image.title="Wallos" \
      org.opencontainers.image.description="Open-source, self-hostable personal subscription tracker" \
      org.opencontainers.image.url="https://github.com/ellite/Wallos" \
      org.opencontainers.image.source="https://github.com/ellite/Wallos" \
      org.opencontainers.image.documentation="https://github.com/ellite/Wallos#readme" \
      org.opencontainers.image.licenses="GPL-3.0-only" \
      org.opencontainers.image.vendor="ellite"

WORKDIR /var/www/html

# Runtime libraries only (no -dev packages, no autoconf)
# hadolint ignore=DL3018
RUN --mount=type=cache,target=/var/cache/apk \
    apk upgrade --cache-dir /var/cache/apk && \
    apk add --cache-dir /var/cache/apk \
        curl \
        dcron \
        dumb-init \
        freetype \
        icu-data-full \
        icu-libs \
        libgomp \
        libjpeg-turbo \
        libpng \
        libwebp \
        libzip \
        nginx \
        shadow \
        sqlite \
        sqlite-libs \
        tzdata \
    && \
    mkdir -p /var/log/cron

# Compiled extensions and their ini files from the builder stage
COPY --link --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --link --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Static configuration
COPY --link nginx.conf /etc/nginx/nginx.conf
COPY --link nginx.default.conf /etc/nginx/http.d/default.conf
COPY --link php-fpm.wallos.conf /usr/local/etc/php-fpm.d/zz-wallos.conf
COPY --link php.wallos.ini /usr/local/etc/php/conf.d/wallos-uploads.ini

# Cron
COPY --link --chmod=0644 --from=src /src/cronjobs /etc/cron.d/cronjobs
RUN /usr/bin/crontab /etc/cron.d/cronjobs

# Application (last: most frequently changing layer)
COPY --link --from=src --chown=www-data:www-data /src /var/www/html

EXPOSE 80

ENTRYPOINT ["dumb-init", "--"]

# Requires docker engine 25+ for the --start-interval flag
HEALTHCHECK --interval=2m --timeout=2s --start-period=20s --start-interval=5s --retries=3 \
    CMD ["curl", "-fsS", "http://127.0.0.1/health.php"]

CMD ["/var/www/html/startup.sh"]

# Per-build metadata last so it never invalidates the cache above
ARG VERSION=dev
ARG REVISION=unknown
ARG CREATED=
ARG BASE_IMAGE
LABEL org.opencontainers.image.version="${VERSION}" \
      org.opencontainers.image.revision="${REVISION}" \
      org.opencontainers.image.created="${CREATED}" \
      org.opencontainers.image.base.name="${BASE_IMAGE}"
