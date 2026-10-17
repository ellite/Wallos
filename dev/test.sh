#!/bin/sh
# Runs the test suite in a throwaway PHP container, so no local PHP is needed.
#
#   dev/test.sh              every case
#   dev/test.sh currency     cases matching "currency"

set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
ENGINE=${CONTAINER_ENGINE:-podman}

"$ENGINE" build -q -t wallos-tests -f "$ROOT/dev/Dockerfile.test" "$ROOT/dev"

exec "$ENGINE" run --rm \
    -v "$ROOT":/var/www/html:Z \
    -w /var/www/html \
    wallos-tests \
    php tests/run.php "$@"
