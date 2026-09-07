#!/usr/bin/env bash
# Installs the WordPress core PHPUnit test library and a disposable test
# database, for the Integration suite (WP_UnitTestCase).
#
# Downloads via curl+tar (a wordpress.org release tarball for WP core, and a
# GitHub tarball of wordpress-develop for the PHPUnit test library) and
# creates the test database via PHP's mysqli extension - the classic
# community install-wp-tests.sh normally uses svn/git and the
# mysql/mysqladmin CLI, none of which exist in the wordpress:php8.4-apache
# image this project's Docker stack runs; curl, tar and mysqli already do.
# (Same script as DinkyChat's own bin/install-wp-tests.sh - copied verbatim
# apart from the WP_TESTS_DOMAIN/WP_TESTS_TITLE strings below.)
#
# Usage: install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]
#
# Safe to re-run: skips the download steps if the target directories already
# exist. Delete $WP_TESTS_LIB_DIR (default /wp-tests-lib) to force a fresh
# download, e.g. after a WordPress release you want to test against.
set -euo pipefail

DB_NAME=${1:?Usage: install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]}
DB_USER=${2:?}
DB_PASS=${3:?}
DB_HOST=${4:-localhost}
WP_VERSION=${5:-latest}

WP_TESTS_LIB_DIR=${WP_TESTS_LIB_DIR:-/wp-tests-lib}
WP_TESTS_DIR="$WP_TESTS_LIB_DIR/tests-lib"
WP_CORE_DIR="$WP_TESTS_LIB_DIR/wordpress"

if [ "$WP_VERSION" = "latest" ]; then
    WP_VERSION=$(curl -sS https://api.wordpress.org/core/version-check/1.7/ \
        | grep -o '"version":"[^"]*"' | head -1 | cut -d'"' -f4)
    echo "Resolved 'latest' to WordPress $WP_VERSION"
fi

if [ -f "$WP_CORE_DIR/wp-load.php" ]; then
    echo "WordPress core already present at $WP_CORE_DIR - skipping download"
else
    echo "Downloading WordPress $WP_VERSION core..."
    mkdir -p "$WP_CORE_DIR"
    curl -sSL "https://wordpress.org/wordpress-${WP_VERSION}.tar.gz" | tar -xz -C "$WP_CORE_DIR" --strip-components=1
fi

if [ -d "$WP_TESTS_DIR/includes" ]; then
    echo "WP PHPUnit test library already present at $WP_TESTS_DIR - skipping download"
else
    # wordpress.org release tarballs accept a two-part version (e.g. "7.1"),
    # but wordpress-develop's git tags are always three-part (e.g. "7.1.0").
    develop_tag="$WP_VERSION"
    case "$develop_tag" in
        *.*.*) ;;
        *) develop_tag="${develop_tag}.0" ;;
    esac
    echo "Downloading the WordPress $develop_tag PHPUnit test library..."
    tmp_dir=$(mktemp -d)
    curl -sSL "https://github.com/WordPress/wordpress-develop/archive/refs/tags/${develop_tag}.tar.gz" | tar -xz -C "$tmp_dir" --strip-components=1
    mkdir -p "$WP_TESTS_DIR"
    cp -r "$tmp_dir/tests/phpunit/includes" "$WP_TESTS_DIR/includes"
    cp -r "$tmp_dir/tests/phpunit/data" "$WP_TESTS_DIR/data"
    rm -rf "$tmp_dir"
fi

echo "Writing wp-tests-config.php..."
cat > "$WP_TESTS_DIR/wp-tests-config.php" <<PHP
<?php
define( 'ABSPATH', '${WP_CORE_DIR}/' );
define( 'WP_DEFAULT_THEME', 'default' );
define( 'WP_DEBUG', true );

// Disposable test database - WP_UnitTestCase truncates tables between tests.
define( 'DB_NAME', '${DB_NAME}' );
define( 'DB_USER', '${DB_USER}' );
define( 'DB_PASSWORD', '${DB_PASS}' );
define( 'DB_HOST', '${DB_HOST}' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

define( 'AUTH_KEY',         'prestaspot-test-suite' );
define( 'SECURE_AUTH_KEY',  'prestaspot-test-suite' );
define( 'LOGGED_IN_KEY',    'prestaspot-test-suite' );
define( 'NONCE_KEY',        'prestaspot-test-suite' );
define( 'AUTH_SALT',        'prestaspot-test-suite' );
define( 'SECURE_AUTH_SALT', 'prestaspot-test-suite' );
define( 'LOGGED_IN_SALT',   'prestaspot-test-suite' );
define( 'NONCE_SALT',       'prestaspot-test-suite' );

\$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', 'prestaspot.test' );
define( 'WP_TESTS_EMAIL', 'admin@prestaspot.test' );
define( 'WP_TESTS_TITLE', 'PrestaSpot Test Suite' );
define( 'WP_PHP_BINARY', 'php' );
PHP

echo "Creating database '${DB_NAME}' if it doesn't already exist..."
php -r '
$mysqli = @new mysqli($argv[3], $argv[1], $argv[2]);
if ($mysqli->connect_errno) {
    fwrite(STDERR, "DB connection failed: " . $mysqli->connect_error . PHP_EOL);
    exit(1);
}
$dbName = $mysqli->real_escape_string($argv[4]);
if (!$mysqli->query("CREATE DATABASE IF NOT EXISTS `$dbName`")) {
    fwrite(STDERR, "CREATE DATABASE failed: " . $mysqli->error . PHP_EOL);
    exit(1);
}
echo "OK" . PHP_EOL;
' -- "$DB_USER" "$DB_PASS" "$DB_HOST" "$DB_NAME"

echo "Done."
echo "  WP_TESTS_DIR=$WP_TESTS_DIR"
echo "  WP_CORE_DIR=$WP_CORE_DIR"
