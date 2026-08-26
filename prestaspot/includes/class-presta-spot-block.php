<?php
/**
 * PrestaSpot Gutenberg Block Handler
 *
 * Registers the dynamic "prestaspot/product-list" block (block.json lives in
 * blocks/product-list/) and renders it server-side via the shared renderer,
 * mirroring what the shortcode does.
 *
 * @package PrestaSpot
 */

if (!defined('ABSPATH')) {
    exit;
}

class Presta_Spot_Block
{
    private Presta_Spot_Renderer $renderer;
    private Presta_Spot_Api $api;

    public function __construct(Presta_Spot_Renderer $renderer, Presta_Spot_Api $api)
    {
        $this->renderer = $renderer;
        $this->api = $api;
    }

    public static function setup(Presta_Spot_Renderer $renderer, Presta_Spot_Api $api): Presta_Spot_Block
    {
        $block = new Presta_Spot_Block($renderer, $api);
        add_action('init', array($block, 'register'));
        add_action('rest_api_init', array($block, 'register_rest_routes'));
        return $block;
    }

    public function register(): void
    {
        register_block_type(PRESTASPOT_PLUGIN_DIR . 'blocks/product-list', array(
            'render_callback' => array($this, 'render'),
        ));
    }

    /**
     * Backs the block editor's category and language pickers (see index.js).
     */
    public function register_rest_routes(): void
    {
        register_rest_route('prestaspot/v1', '/categories', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_categories_route'),
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ));
        register_rest_route('prestaspot/v1', '/languages', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_languages_route'),
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ));
    }

    public function get_categories_route(): WP_REST_Response
    {
        return new WP_REST_Response($this->api->get_categories());
    }

    public function get_languages_route(): WP_REST_Response
    {
        return new WP_REST_Response($this->api->get_languages());
    }

    public function render(array $attributes): string
    {
        return $this->renderer->render(array(
            'product_count' => (int)($attributes['productCount'] ?? 0),
            'category_id' => (int)($attributes['categoryId'] ?? 0),
            'language_id' => (int)($attributes['languageId'] ?? 0),
            'on_sale' => (bool)($attributes['onSale'] ?? false),
            'columns' => (int)($attributes['columns'] ?? 0),
            'show_image' => (bool)($attributes['showImage'] ?? true),
            'show_name' => (bool)($attributes['showName'] ?? true),
            'show_description' => (bool)($attributes['showDescription'] ?? true),
            'show_price' => (bool)($attributes['showPrice'] ?? true),
            'show_stock_status' => (bool)($attributes['showStockStatus'] ?? true),
            'price_position' => (string)($attributes['pricePosition'] ?? ''),
            'sort' => (string)($attributes['sort'] ?? ''),
            'layout' => (string)($attributes['layout'] ?? ''),
            'view_mode' => (string)($attributes['viewMode'] ?? ''),
            'link_text' => (string)($attributes['linkText'] ?? ''),
            'link_style' => (string)($attributes['linkStyle'] ?? ''),
            'button_color' => (string)($attributes['buttonColor'] ?? ''),
            'sale_badge_color' => (string)($attributes['saleBadgeColor'] ?? ''),
        ));
    }
}
