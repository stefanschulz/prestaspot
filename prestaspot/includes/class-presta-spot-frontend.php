<?php
/**
 * PrestaSpot Frontend Asset Loader
 *
 * @package PrestaSpot
 */

if (!defined('ABSPATH')) {
    exit;
}

class Presta_Spot_Frontend
{
    public static function setup(): Presta_Spot_Frontend
    {
        $frontend = new Presta_Spot_Frontend();
        add_action('wp_enqueue_scripts', array($frontend, 'enqueue_styles'));
        return $frontend;
    }

    public function enqueue_styles(): void
    {
        wp_register_style(
            'prestaspot-css',
            PRESTASPOT_PLUGIN_URL . 'assets/css/prestaspot.css',
            array(),
            prestaspot_get_asset_version('assets/css/prestaspot.css')
        );
        wp_enqueue_style('prestaspot-css');
    }
}
