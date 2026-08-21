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
    public const SHOW_IMAGE = 'show_image';
    public const SHOW_NAME = 'show_name';
    public const SHOW_DESCRIPTION = 'show_description';
    public const LAYOUT = 'layout';

    // Card layouts: which order image/name/description render in. The
    // "View in shop" link always renders last, regardless of layout.
    public const LAYOUT_IMAGE_NAME_DESCRIPTION = 'image_name_description';
    public const LAYOUT_NAME_IMAGE_DESCRIPTION = 'name_image_description';
    public const LAYOUT_NAME_DESCRIPTION_IMAGE = 'name_description_image';

    const LAYOUT_ELEMENT_ORDER = array(
        self::LAYOUT_IMAGE_NAME_DESCRIPTION => array('image', 'name', 'description'),
        self::LAYOUT_NAME_IMAGE_DESCRIPTION => array('name', 'image', 'description'),
        self::LAYOUT_NAME_DESCRIPTION_IMAGE => array('name', 'description', 'image'),
    );

    public const VIEW_MODE = 'view_mode';

    // Display mode: 'grid' is the current card-grid layout, 'list' stacks
    // products as rows (smaller image, text-sized row height). Layout order
    // and element visibility (above) apply to both.
    public const VIEW_MODE_GRID = 'grid';
    public const VIEW_MODE_LIST = 'list';

    const VIEW_MODES = array(self::VIEW_MODE_GRID, self::VIEW_MODE_LIST);

    const PRESTASPOT_SETTINGS_DEFAULTS = array(
        self::SHOP_URL => '',
        self::API_KEY => '',
        self::PRODUCT_COUNT => 8,
        self::COLUMNS => 4,
        self::CACHE_DURATION => 900,
        self::SHOW_IMAGE => true,
        self::SHOW_NAME => true,
        self::SHOW_DESCRIPTION => true,
        self::LAYOUT => self::LAYOUT_IMAGE_NAME_DESCRIPTION,
        self::VIEW_MODE => self::VIEW_MODE_GRID,
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
            self::SHOW_IMAGE => $this->get_prestaspot_option(self::SHOW_IMAGE),
            self::SHOW_NAME => $this->get_prestaspot_option(self::SHOW_NAME),
            self::SHOW_DESCRIPTION => $this->get_prestaspot_option(self::SHOW_DESCRIPTION),
            self::LAYOUT => $this->get_prestaspot_option(self::LAYOUT),
            self::VIEW_MODE => $this->get_prestaspot_option(self::VIEW_MODE),
        );

        $settings[self::SHOP_URL] = untrailingslashit(esc_url_raw($settings[self::SHOP_URL]));
        $settings[self::API_KEY] = sanitize_text_field($settings[self::API_KEY]);
        $settings[self::PRODUCT_COUNT] = max(1, absint($settings[self::PRODUCT_COUNT]));
        $settings[self::COLUMNS] = max(1, absint($settings[self::COLUMNS]));
        $settings[self::CACHE_DURATION] = max(60, absint($settings[self::CACHE_DURATION]));
        $settings[self::SHOW_IMAGE] = (bool)$settings[self::SHOW_IMAGE];
        $settings[self::SHOW_NAME] = (bool)$settings[self::SHOW_NAME];
        $settings[self::SHOW_DESCRIPTION] = (bool)$settings[self::SHOW_DESCRIPTION];
        $settings[self::LAYOUT] = array_key_exists($settings[self::LAYOUT], self::LAYOUT_ELEMENT_ORDER)
            ? $settings[self::LAYOUT]
            : self::PRESTASPOT_SETTINGS_DEFAULTS[self::LAYOUT];
        $settings[self::VIEW_MODE] = in_array($settings[self::VIEW_MODE], self::VIEW_MODES, true)
            ? $settings[self::VIEW_MODE]
            : self::PRESTASPOT_SETTINGS_DEFAULTS[self::VIEW_MODE];

        return $settings;
    }

    public function get(string $key): mixed
    {
        $all = $this->get_all();
        return $all[$key] ?? null;
    }

    /**
     * Resolves a stored/requested layout value to its element render order,
     * falling back to the default layout for unknown/invalid values.
     *
     * @return string[]
     */
    public static function get_layout_element_order(string $layout): array
    {
        return self::LAYOUT_ELEMENT_ORDER[$layout] ?? self::LAYOUT_ELEMENT_ORDER[self::PRESTASPOT_SETTINGS_DEFAULTS[self::LAYOUT]];
    }

    private function get_prestaspot_option(string $key): mixed
    {
        return get_option('prestaspot_' . $key, self::PRESTASPOT_SETTINGS_DEFAULTS[$key]);
    }
}
