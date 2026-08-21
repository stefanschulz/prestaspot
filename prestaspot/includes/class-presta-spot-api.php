<?php
/**
 * PrestaSpot PrestaShop Webservice Client
 *
 * Talks to the PrestaShop Webservice REST API and normalizes the response
 * into the flat card shape the templates render (id, name, description,
 * image_url, permalink). Results are cached in a transient.
 *
 * @package PrestaSpot
 */

if (!defined('ABSPATH')) {
    exit;
}

class Presta_Spot_Api
{
    private Presta_Spot_Settings $settings;

    public function __construct(Presta_Spot_Settings $settings)
    {
        $this->settings = $settings;
    }

    public function get_products(int $limit, int $category_id = 0): array
    {
        $settings = $this->settings->get_all();
        $shop_url = $settings[Presta_Spot_Settings::SHOP_URL];
        $api_key = $settings[Presta_Spot_Settings::API_KEY];

        if (empty($shop_url) || empty($api_key)) {
            return array();
        }

        $limit = max(1, $limit);
        $cache_key = 'prestaspot_products_' . md5($shop_url . '|' . $limit . '|' . $category_id);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $products = $this->fetch_products($shop_url, $api_key, $limit, $category_id);

        set_transient($cache_key, $products, $settings[Presta_Spot_Settings::CACHE_DURATION]);

        return $products;
    }

    private function fetch_products(string $shop_url, string $api_key, int $limit, int $category_id): array
    {
        $args = array(
            'display' => '[id,name,description_short,link_rewrite,id_default_image]',
            'output_format' => 'JSON',
            'filter[active]' => '1',
            'limit' => '0,' . $limit,
        );
        if ($category_id > 0) {
            $args['filter[id_category_default]'] = (string)$category_id;
        }

        $url = add_query_arg($args, trailingslashit($shop_url) . 'api/products');

        $response = wp_remote_get($url, array(
            'headers' => array('Authorization' => 'Basic ' . base64_encode($api_key . ':')),
            'timeout' => 10,
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return array();
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $raw_products = $body['products'] ?? array();

        return array_map(fn($product) => $this->normalize_product($product, $shop_url, $api_key), $raw_products);
    }

    private function normalize_product(array $product, string $shop_url, string $api_key): array
    {
        $id = absint($product['id'] ?? 0);

        return array(
            'id' => $id,
            'name' => wp_strip_all_tags($this->localized_value($product['name'] ?? '')),
            'description' => wp_strip_all_tags($this->localized_value($product['description_short'] ?? '')),
            'image_url' => $this->build_image_url($shop_url, $api_key, $id, $product['id_default_image'] ?? ''),
            'permalink' => trailingslashit($shop_url) . 'index.php?controller=product&id_product=' . $id,
        );
    }

    /**
     * PrestaShop returns multilingual fields either as a plain string
     * (single-language shops) or as an array of {id, value} pairs keyed by
     * language id (multilang/multistore shops) - normalize both to a string.
     */
    private function localized_value(mixed $value): string
    {
        if (is_array($value)) {
            $first = reset($value);
            return is_array($first) ? (string)($first['value'] ?? '') : (string)$first;
        }
        return (string)$value;
    }

    private function build_image_url(string $shop_url, string $api_key, int $product_id, mixed $image_id): string
    {
        $image_id = is_array($image_id) ? ($image_id['value'] ?? '') : $image_id;
        if (empty($image_id) || $product_id <= 0) {
            return '';
        }
        return trailingslashit($shop_url) . "api/images/products/$product_id/$image_id?ws_key=" . rawurlencode($api_key);
    }
}
