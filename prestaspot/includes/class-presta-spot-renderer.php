<?php
/**
 * PrestaSpot Card Renderer
 *
 * Shared rendering used by both the shortcode and the Gutenberg block, so
 * the two entry points never drift out of sync.
 *
 * @package PrestaSpot
 */

if (!defined('ABSPATH')) {
    exit;
}

class Presta_Spot_Renderer
{
    private Presta_Spot_Settings $settings;
    private Presta_Spot_Api $api;

    public function __construct(Presta_Spot_Settings $settings, Presta_Spot_Api $api)
    {
        $this->settings = $settings;
        $this->api = $api;
    }

    /**
     * @param array{product_count?: ?int, category_id?: ?int, category_name?: ?string, columns?: ?int, show_image?: bool, show_name?: bool, show_description?: bool, show_price?: bool, price_position?: ?string, on_sale?: bool, sort?: ?string, layout?: ?string, view_mode?: ?string, link_text?: ?string, link_style?: ?string, button_color?: ?string, sale_badge_color?: ?string} $args
     */
    public function render(array $args): string
    {
        $settings = $this->settings->get_all();

        $product_count = !empty($args['product_count']) ? absint($args['product_count']) : $settings[Presta_Spot_Settings::PRODUCT_COUNT];
        $category_id = absint($args['category_id'] ?? 0);
        // category_id wins if both are given.
        if (0 === $category_id && !empty($args['category_name'])) {
            $category_id = $this->api->resolve_category_id_by_name((string)$args['category_name']);
        }
        $on_sale = !empty($args['on_sale']);
        $sort = !empty($args['sort']) && in_array($args['sort'], Presta_Spot_Settings::SORTS, true)
            ? $args['sort']
            : $settings[Presta_Spot_Settings::SORT];
        $columns = !empty($args['columns']) ? absint($args['columns']) : $settings[Presta_Spot_Settings::COLUMNS];
        $show_image = array_key_exists('show_image', $args) ? (bool)$args['show_image'] : $settings[Presta_Spot_Settings::SHOW_IMAGE];
        $show_name = array_key_exists('show_name', $args) ? (bool)$args['show_name'] : $settings[Presta_Spot_Settings::SHOW_NAME];
        $show_description = array_key_exists('show_description', $args) ? (bool)$args['show_description'] : $settings[Presta_Spot_Settings::SHOW_DESCRIPTION];
        $show_price = array_key_exists('show_price', $args) ? (bool)$args['show_price'] : $settings[Presta_Spot_Settings::SHOW_PRICE];
        $price_position = !empty($args['price_position']) && in_array($args['price_position'], Presta_Spot_Settings::PRICE_POSITIONS, true)
            ? $args['price_position']
            : $settings[Presta_Spot_Settings::PRICE_POSITION];
        $layout = !empty($args['layout']) ? $args['layout'] : $settings[Presta_Spot_Settings::LAYOUT];
        $element_order = Presta_Spot_Settings::get_layout_element_order($layout);
        // Price isn't part of the layout picker; spliced in after name/description instead.
        $price_anchor = Presta_Spot_Settings::PRICE_POSITION_AFTER_DESCRIPTION === $price_position ? 'description' : 'name';
        array_splice($element_order, array_search($price_anchor, $element_order, true) + 1, 0, array('price'));
        $view_mode = !empty($args['view_mode']) && in_array($args['view_mode'], Presta_Spot_Settings::VIEW_MODES, true)
            ? $args['view_mode']
            : $settings[Presta_Spot_Settings::VIEW_MODE];
        // '' (both instance and setting unset) is resolved to the built-in
        // translated label by the template, not here - see product-cards.php.
        $link_text = !empty($args['link_text']) ? $args['link_text'] : $settings[Presta_Spot_Settings::LINK_TEXT];
        $link_style = !empty($args['link_style']) && in_array($args['link_style'], Presta_Spot_Settings::LINK_STYLES, true)
            ? $args['link_style']
            : $settings[Presta_Spot_Settings::LINK_STYLE];
        $button_color = !empty($args['button_color'])
            ? (sanitize_hex_color($args['button_color']) ?: $settings[Presta_Spot_Settings::BUTTON_COLOR])
            : $settings[Presta_Spot_Settings::BUTTON_COLOR];
        $sale_badge_color = !empty($args['sale_badge_color'])
            ? (sanitize_hex_color($args['sale_badge_color']) ?: $settings[Presta_Spot_Settings::SALE_BADGE_COLOR])
            : $settings[Presta_Spot_Settings::SALE_BADGE_COLOR];

        $products = $this->api->get_products($product_count, $category_id, $on_sale, $sort);

        ob_start();
        include PRESTASPOT_PLUGIN_DIR . 'templates/product-cards.php';
        return ob_get_clean();
    }

    /**
     * Picks readable button text color (black/white) for an admin-configured
     * background, so contrast can't regress no matter what color is set.
     */
    public static function get_contrasting_text_color(string $hex_color): string
    {
        $hex = ltrim($hex_color, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $brightness = ($r * 299 + $g * 587 + $b * 114) / 1000;
        return $brightness > 128 ? '#000000' : '#ffffff';
    }
}
