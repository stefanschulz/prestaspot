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
            'layout' => '',
            'view_mode' => '',
            'show_image' => '',
            'show_name' => '',
            'show_description' => '',
        ), $attributes, 'prestaspot');

        $render_args = array(
            'product_count' => (int)$attributes['product_count'],
            'category_id' => (int)$attributes['category_id'],
            'columns' => (int)$attributes['columns'],
            'layout' => $attributes['layout'],
            'view_mode' => $attributes['view_mode'],
        );

        foreach (array('show_image', 'show_name', 'show_description') as $flag) {
            if ($attributes[$flag] !== '') {
                $render_args[$flag] = $this->parse_bool($attributes[$flag]);
            }
        }

        return $this->renderer->render($render_args);
    }

    private function parse_bool(string $value): bool
    {
        return !in_array(strtolower($value), array('no', 'false', '0'), true);
    }
}
