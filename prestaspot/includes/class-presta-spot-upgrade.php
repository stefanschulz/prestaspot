<?php
/**
 * One-time housekeeping when the plugin version changes.
 *
 * @package PrestaSpot
 */

if (!defined('ABSPATH')) {
    exit;
}

class Presta_Spot_Upgrade
{
    private const VERSION_OPTION = 'prestaspot_installed_version';

    public static function maybe_run(): void
    {
        if (get_option(self::VERSION_OPTION) === PRESTASPOT_VERSION) {
            return;
        }

        // 0.16.3 and earlier cached image URLs containing the webservice key.
        self::delete_product_transients();
        update_option(self::VERSION_OPTION, PRESTASPOT_VERSION);
    }

    public static function delete_product_transients(): void
    {
        global $wpdb;

        // delete_transient() per row rather than one raw DELETE, so the
        // options cache doesn't keep serving the removed values.
        $names = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_prestaspot_products_') . '%'
        ));
        foreach ($names as $name) {
            delete_transient(substr($name, strlen('_transient_')));
        }
    }
}
