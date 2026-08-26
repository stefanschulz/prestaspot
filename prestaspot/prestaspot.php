<?php
/**
 * Plugin Name: PrestaSpot
 * Description: Displays content from a connected PrestaShop store (starting with products) as cards, via a Gutenberg block or a shortcode.
 * Version: 0.14.1
 * Author: Stefan Schulz
 * Text Domain: prestaspot
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * License: Apache License 2.0
 * License URI: http://www.apache.org/licenses/LICENSE-2.0
 *
 * @package PrestaSpot
 */

if (!defined('ABSPATH')) {
    exit;
}

function prestaspot_get_version(): string
{
    $plugin_file = __FILE__;
    $content = file_get_contents($plugin_file);
    if (!preg_match('/Version:\s*([\d.]+)/', $content, $matches) || empty($matches[1])) {
        die('PrestaSpot plugin broken.');
    }
    return $matches[1];
}

define('PRESTASPOT_VERSION', prestaspot_get_version());
define('PRESTASPOT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PRESTASPOT_PLUGIN_URL', plugin_dir_url(__FILE__));

// Uses the file's own last-modified time as the cache-busting version for
// enqueued JS/CSS, so a changed file is always fetched fresh - no need to
// bump PRESTASPOT_VERSION just to force a browser to pick up an edit.
function prestaspot_get_asset_version(string $relative_path): string
{
    $full_path = PRESTASPOT_PLUGIN_DIR . $relative_path;
    $mtime = file_exists($full_path) ? filemtime($full_path) : false;
    return $mtime !== false ? (string)$mtime : PRESTASPOT_VERSION;
}

spl_autoload_register(function ($classname) {
    $class = str_replace('\\', DIRECTORY_SEPARATOR, str_replace('_', '-', strtolower($classname)));
    $classes = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'class-' . $class . '.php';

    if (file_exists($classes)) {
        require_once($classes);
    }
});

class Presta_Spot_Plugin
{
    private static ?Presta_Spot_Plugin $instance = null;
    private Presta_Spot_Settings $settings;
    private Presta_Spot_Api $api;
    private Presta_Spot_Admin $admin;

    public static function get_instance(): Presta_Spot_Plugin
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->init_hooks();
        $this->settings = new Presta_Spot_Settings();
        $this->api = new Presta_Spot_Api($this->settings);
        $this->admin = new Presta_Spot_Admin($this->settings);
        $this->admin->init();
    }

    private function __clone()
    {
    }

    /**
     * @throws Exception
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize a singleton.");
    }

    private function init_hooks(): void
    {
        add_action('plugins_loaded', [$this, 'setup_plugin']);
    }

    public function setup_plugin(): void
    {
        $renderer = new Presta_Spot_Renderer($this->settings, $this->api);
        Presta_Spot_Shortcode::setup($renderer);
        Presta_Spot_Block::setup($renderer, $this->api);
        Presta_Spot_Frontend::setup();
    }
}

function prestaspot_plugin(): Presta_Spot_Plugin
{
    return Presta_Spot_Plugin::get_instance();
}

prestaspot_plugin();
