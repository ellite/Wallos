#!/bin/sh
# Runs the test suite in a throwaway PHP container, so no local PHP is needed.
#
#   dev/test.sh              every case
#   dev/test.sh currency     cases matching "currency"
#
# The container is php:8.3-cli plus the intl extension, which Wallos requires
# and the date formatting cases depend on. The image is built on the first run
# and cached by the container engine afterwards.

set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
ENGINE=${CONTAINER_ENGINE:-podman}
IMAGE=wallos-tests-php

if ! BUILD_LOG=$("$ENGINE" build -q -t "$IMAGE" - 2>&1 <<'DOCKERFILE'
FROM docker.io/library/php:8.3-cli
RUN apt-get update -qq \
    && apt-get install -y -qq libicu-dev \
    && docker-php-ext-install intl \
    && rm -rf /var/lib/apt/lists/*
DOCKERFILE
); then
    printf '%s\n' "$BUILD_LOG" >&2
    exit 1
fi

exec "$ENGINE" run --rm \
    -v "$ROOT":/var/www/html:Z \
    -w /var/www/html \
    "$IMAGE" \
    php tests/run.php "$@"
