#!/usr/bin/env bash
# Run the PHPUnit Integration suite inside the WordPress dev container.
#
# The repo is mounted read-only at /repo, so PHPUnit's own result-cache
# directory (normally .phpunit.cache next to phpunit-integration.xml.dist)
# has to be redirected to /tmp - mirrors the same constraint DinkyChat's own
# .docker/test.sh works around.
set -euo pipefail
cd "$(dirname "$0")"

# Git Bash on Windows rewrites arguments that look like Unix paths (/repo -> a
# Windows path) before Docker sees them. Harmless everywhere else.
export MSYS_NO_PATHCONV=1

docker exec prestaspot-docker-wordpress-1 \
    php /repo/vendor/bin/phpunit \
    --configuration /repo/phpunit-integration.xml.dist \
    --cache-directory /tmp/phpunit \
    "$@"
