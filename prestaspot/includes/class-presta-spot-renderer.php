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
     * @param array{product_count?: ?int, category_id?: ?int, columns?: ?int, show_image?: bool, show_name?: bool, show_description?: bool, layout?: ?string} $args
     */
    public function render(array $args): string
    {
        $settings = $this->settings->get_all();

        $product_count = !empty($args['product_count']) ? absint($args['product_count']) : $settings[Presta_Spot_Settings::PRODUCT_COUNT];
        $category_id = absint($args['category_id'] ?? 0);
        $columns = !empty($args['columns']) ? absint($args['columns']) : $settings[Presta_Spot_Settings::COLUMNS];
        $show_image = array_key_exists('show_image', $args) ? (bool)$args['show_image'] : $settings[Presta_Spot_Settings::SHOW_IMAGE];
        $show_name = array_key_exists('show_name', $args) ? (bool)$args['show_name'] : $settings[Presta_Spot_Settings::SHOW_NAME];
        $show_description = array_key_exists('show_description', $args) ? (bool)$args['show_description'] : $settings[Presta_Spot_Settings::SHOW_DESCRIPTION];
        $layout = !empty($args['layout']) ? $args['layout'] : $settings[Presta_Spot_Settings::LAYOUT];
        $element_order = Presta_Spot_Settings::get_layout_element_order($layout);

        $products = $this->api->get_products($product_count, $category_id);

        ob_start();
        include PRESTASPOT_PLUGIN_DIR . 'templates/product-cards.php';
        return ob_get_clean();
    }
}
