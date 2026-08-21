<?php
/**
 * PrestaSpot Settings Model
 *
 * Pure data access for plugin settings. No business logic, no hooks.
 *
 * @package PrestaSpot
 */

if (!defined('ABSPATH')) {
    exit;
}

class Presta_Spot_Settings
{
    public const SHOP_URL = 'shop_url';
    public const API_KEY = 'api_key';
    public const PRODUCT_COUNT = 'product_count';
    public const COLUMNS = 'columns';
    public const CACHE_DURATION = 'cache_duration';

    const PRESTASPOT_SETTINGS_DEFAULTS = array(
        self::SHOP_URL => '',
        self::API_KEY => '',
        self::PRODUCT_COUNT => 8,
        self::COLUMNS => 4,
        self::CACHE_DURATION => 900,
    );

    public function __construct() {}

    public function get_all(): array
    {
        $settings = array(
            self::SHOP_URL => $this->get_prestaspot_option(self::SHOP_URL),
            self::API_KEY => $this->get_prestaspot_option(self::API_KEY),
            self::PRODUCT_COUNT => $this->get_prestaspot_option(self::PRODUCT_COUNT),
            self::COLUMNS => $this->get_prestaspot_option(self::COLUMNS),
            self::CACHE_DURATION => $this->get_prestaspot_option(self::CACHE_DURATION),
        );

        $settings[self::SHOP_URL] = untrailingslashit(esc_url_raw($settings[self::SHOP_URL]));
        $settings[self::API_KEY] = sanitize_text_field($settings[self::API_KEY]);
        $settings[self::PRODUCT_COUNT] = max(1, absint($settings[self::PRODUCT_COUNT]));
        $settings[self::COLUMNS] = max(1, absint($settings[self::COLUMNS]));
        $settings[self::CACHE_DURATION] = max(60, absint($settings[self::CACHE_DURATION]));

        return $settings;
    }

    public function get(string $key): mixed
    {
        $all = $this->get_all();
        return $all[$key] ?? null;
    }

    private function get_prestaspot_option(string $key): mixed
    {
        return get_option('prestaspot_' . $key, self::PRESTASPOT_SETTINGS_DEFAULTS[$key]);
    }
}
