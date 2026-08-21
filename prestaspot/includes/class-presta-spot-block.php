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

    public function __construct(Presta_Spot_Renderer $renderer)
    {
        $this->renderer = $renderer;
    }

    public static function setup(Presta_Spot_Renderer $renderer): Presta_Spot_Block
    {
        $block = new Presta_Spot_Block($renderer);
        add_action('init', array($block, 'register'));
        return $block;
    }

    public function register(): void
    {
        register_block_type(PRESTASPOT_PLUGIN_DIR . 'blocks/product-list', array(
            'render_callback' => array($this, 'render'),
        ));
    }

    public function render(array $attributes): string
    {
        return $this->renderer->render(array(
            'product_count' => (int)($attributes['productCount'] ?? 0),
            'category_id' => (int)($attributes['categoryId'] ?? 0),
            'columns' => (int)($attributes['columns'] ?? 0),
            'show_image' => (bool)($attributes['showImage'] ?? true),
            'show_name' => (bool)($attributes['showName'] ?? true),
            'show_description' => (bool)($attributes['showDescription'] ?? true),
        ));
    }
}
