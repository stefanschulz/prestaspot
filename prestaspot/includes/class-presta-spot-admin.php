<?php
/**
 * PrestaSpot Admin Controller
 *
 * Registers the settings page under wp-admin and the WordPress Settings API
 * options backing it. This class is responsible ONLY for admin integration -
 * it holds no PrestaShop or rendering logic.
 *
 * @package PrestaSpot
 */

if (!defined('ABSPATH')) {
    exit;
}

class Presta_Spot_Admin
{
    private Presta_Spot_Settings $settings;

    public function __construct(Presta_Spot_Settings $settings)
    {
        $this->settings = $settings;
    }

    public function init(): void
    {
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_admin_menu(): void
    {
        add_menu_page(
            __('PrestaSpot', 'prestaspot'),
            __('PrestaSpot', 'prestaspot'),
            'manage_options',
            'prestaspot-settings',
            array($this, 'render_settings_page'),
            'dashicons-store',
            65
        );
    }

    public function register_settings(): void
    {
        register_setting('prestaspot_settings_group', 'prestaspot_shop_url', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_shop_url'),
            'default' => Presta_Spot_Settings::PRESTASPOT_SETTINGS_DEFAULTS[Presta_Spot_Settings::SHOP_URL],
        ));

        register_setting('prestaspot_settings_group', 'prestaspot_api_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => Presta_Spot_Settings::PRESTASPOT_SETTINGS_DEFAULTS[Presta_Spot_Settings::API_KEY],
        ));

        register_setting('prestaspot_settings_group', 'prestaspot_product_count', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => Presta_Spot_Settings::PRESTASPOT_SETTINGS_DEFAULTS[Presta_Spot_Settings::PRODUCT_COUNT],
        ));

        register_setting('prestaspot_settings_group', 'prestaspot_columns', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => Presta_Spot_Settings::PRESTASPOT_SETTINGS_DEFAULTS[Presta_Spot_Settings::COLUMNS],
        ));

        register_setting('prestaspot_settings_group', 'prestaspot_cache_duration', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => Presta_Spot_Settings::PRESTASPOT_SETTINGS_DEFAULTS[Presta_Spot_Settings::CACHE_DURATION],
        ));
    }

    public function sanitize_shop_url(string $input): string
    {
        return untrailingslashit(esc_url_raw(trim($input)));
    }

    public function render_settings_page(): void
    {
        $settings = $this->settings->get_all();

        $template_path = PRESTASPOT_PLUGIN_DIR . 'templates/settings-page.php';
        if (file_exists($template_path)) {
            require $template_path;
        }
    }
}
