<?php
/**
 * Bootstrap for the Unit test suite - no WordPress, no database.
 *
 * Plugin files start with `if (!defined('ABSPATH')) exit;` as a direct-access
 * guard; defining a dummy ABSPATH here satisfies that without loading
 * WordPress. Only require files whose classes are actually exercised by
 * Unit tests - the methods under test must not call any WP function.
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

require_once __DIR__ . '/../prestaspot/includes/class-presta-spot-settings.php';
require_once __DIR__ . '/../prestaspot/includes/class-presta-spot-api.php';
require_once __DIR__ . '/../prestaspot/includes/class-presta-spot-renderer.php';
