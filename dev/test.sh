#!/bin/sh
# Runs the test suite in a throwaway PHP container, so no local PHP is needed.
#
#   dev/test.sh              every case
#   dev/test.sh currency     cases matching "currency"

set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
ENGINE=${CONTAINER_ENGINE:-podman}

exec "$ENGINE" run --rm \
    -v "$ROOT":/var/www/html:Z \
    -w /var/www/html \
    docker.io/library/php:8.3-cli \
    php tests/run.php "$@"
