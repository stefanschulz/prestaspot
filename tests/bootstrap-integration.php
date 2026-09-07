<?php
/**
 * Bootstrap for the Integration test suite - boots a real, disposable
 * WordPress instance (via the WP core PHPUnit test library, see
 * bin/install-wp-tests.sh) against a real, disposable test database, and
 * loads PrestaSpot as an active plugin.
 *
 * Unlike a plugin with its own DB tables, PrestaSpot's Integration tests
 * also need a real PrestaShop instance to talk to - the shop_url/api_key
 * options are seeded below to point at the prestaspot-docker dev stack's
 * `prestashop` service (see .doc/DEVELOPER_GUIDE.md for the fixture data
 * these tests assume - the Hummingbird t-shirt, product 3's zeroed stock,
 * the German translation on product 1, etc.). Tests in this suite are not
 * hermetic: they need that stack up and its fixtures intact.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Must be defined before the WP core bootstrap file runs.
define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', __DIR__ . '/../vendor/yoast/phpunit-polyfills');

$wp_tests_dir = getenv('WP_TESTS_DIR') ?: '/wp-tests-lib/tests-lib';

if (!file_exists("$wp_tests_dir/includes/functions.php")) {
    fwrite(STDERR, "Could not find $wp_tests_dir/includes/functions.php - run bin/install-wp-tests.sh first." . PHP_EOL);
    exit(1);
}

require_once "$wp_tests_dir/includes/functions.php";

tests_add_filter('muplugins_loaded', function () {
    require dirname(__DIR__) . '/prestaspot/prestaspot.php';

    // shop_url intentionally has no trailing /api - see sanitize_shop_url();
    // the API client appends it. host.docker.internal is the one address
    // that resolves identically from inside this container and the host.
    update_option('prestaspot_shop_url', getenv('PRESTASPOT_TEST_SHOP_URL') ?: 'http://host.docker.internal:8093');
    update_option('prestaspot_api_key', getenv('PRESTASPOT_TEST_API_KEY') ?: 'GTT75HR58LMXV22EKVRVWAKDLVGXLIR9');
});

// Same "install.php output pollutes stdout" issue DinkyChat's own
// bootstrap-integration.php works around - buffered away rather than left
// to cause "headers already sent" warnings later in the run.
ob_start();
require "$wp_tests_dir/includes/bootstrap.php";
ob_end_clean();

// PHPUnit's directory-based test discovery only requires files matching its
// default *Test.php suffix, so the shared IntegrationTestCase base (which
// doesn't match that pattern on purpose - it isn't itself a test) needs
// loading explicitly, before PHPUnit processes tests/Integration.
require_once __DIR__ . '/Integration/IntegrationTestCase.php';
