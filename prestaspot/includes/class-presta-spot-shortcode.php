<?php
/**
 * PrestaSpot Shortcode Handler
 *
 * @package PrestaSpot
 */

if (!defined('ABSPATH')) {
    exit;
}

class Presta_Spot_Shortcode
{
    private Presta_Spot_Renderer $renderer;

    public function __construct(Presta_Spot_Renderer $renderer)
    {
        $this->renderer = $renderer;
    }

    public static function setup(Presta_Spot_Renderer $renderer): Presta_Spot_Shortcode
    {
        $shortcode = new Presta_Spot_Shortcode($renderer);
        add_shortcode('prestaspot', array($shortcode, 'display_products'));
        return $shortcode;
    }

    public function display_products($attributes): string
    {
        $attributes = shortcode_atts(array(
            'product_count' => 0,
            'category_id' => 0,
            'columns' => 0,
        ), $attributes, 'prestaspot');

        return $this->renderer->render(array(
            'product_count' => (int)$attributes['product_count'],
            'category_id' => (int)$attributes['category_id'],
            'columns' => (int)$attributes['columns'],
        ));
    }
}
