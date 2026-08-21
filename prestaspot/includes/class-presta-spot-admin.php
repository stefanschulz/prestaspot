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
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    public function enqueue_admin_scripts(string $hook): void
    {
        if (str_contains($hook, 'prestaspot-settings')) {
            wp_enqueue_style(
                'prestaspot-layout-picker',
                PRESTASPOT_PLUGIN_URL . 'assets/css/layout-picker.css',
                array(),
                prestaspot_get_asset_version('assets/css/layout-picker.css')
            );
        }
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

        register_setting('prestaspot_settings_group', 'prestaspot_show_image', array(
            'type' => 'boolean',
            'sanitize_callback' => array($this, 'sanitize_boolean'),
            'default' => Presta_Spot_Settings::PRESTASPOT_SETTINGS_DEFAULTS[Presta_Spot_Settings::SHOW_IMAGE],
        ));

        register_setting('prestaspot_settings_group', 'prestaspot_show_name', array(
            'type' => 'boolean',
            'sanitize_callback' => array($this, 'sanitize_boolean'),
            'default' => Presta_Spot_Settings::PRESTASPOT_SETTINGS_DEFAULTS[Presta_Spot_Settings::SHOW_NAME],
        ));

        register_setting('prestaspot_settings_group', 'prestaspot_show_description', array(
            'type' => 'boolean',
            'sanitize_callback' => array($this, 'sanitize_boolean'),
            'default' => Presta_Spot_Settings::PRESTASPOT_SETTINGS_DEFAULTS[Presta_Spot_Settings::SHOW_DESCRIPTION],
        ));

        register_setting('prestaspot_settings_group', 'prestaspot_layout', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_layout'),
            'default' => Presta_Spot_Settings::PRESTASPOT_SETTINGS_DEFAULTS[Presta_Spot_Settings::LAYOUT],
        ));
    }

    public function sanitize_shop_url(string $input): string
    {
        return untrailingslashit(esc_url_raw(trim($input)));
    }

    public function sanitize_boolean(mixed $input): bool
    {
        return !empty($input);
    }

    public function sanitize_layout(string $input): string
    {
        return array_key_exists($input, Presta_Spot_Settings::LAYOUT_ELEMENT_ORDER)
            ? $input
            : Presta_Spot_Settings::PRESTASPOT_SETTINGS_DEFAULTS[Presta_Spot_Settings::LAYOUT];
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
