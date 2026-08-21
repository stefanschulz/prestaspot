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

    public function get_products(int $limit, int $category_id = 0, bool $on_sale = false): array
    {
        $settings = $this->settings->get_all();
        $shop_url = $settings[Presta_Spot_Settings::SHOP_URL];
        $api_key = $settings[Presta_Spot_Settings::API_KEY];

        if (empty($shop_url) || empty($api_key)) {
            return array();
        }

        $limit = max(1, $limit);
        $language_id = $this->resolve_language_id($shop_url, $api_key);
        $cache_key = 'prestaspot_products_' . md5($shop_url . '|' . $limit . '|' . $category_id . '|' . $language_id . '|' . ($on_sale ? '1' : '0'));
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $products = $this->fetch_products($shop_url, $api_key, $limit, $category_id, $language_id, $on_sale);

        set_transient($cache_key, $products, $settings[Presta_Spot_Settings::CACHE_DURATION]);

        return $products;
    }

    private function fetch_products(string $shop_url, string $api_key, int $limit, int $category_id, int $language_id, bool $on_sale): array
    {
        $args = array(
            // "price" here is always tax-excluded - PrestaShop's tax/reduction
            // -aware computed price (the "price[alias][use_tax]=..." bracket
            // syntax) only works when fetching a single product by id, not on
            // this list endpoint, so it can't be used for a product listing.
            'display' => '[id,name,description_short,link_rewrite,id_default_image,price]',
            'output_format' => 'JSON',
            'filter[active]' => '1',
            'limit' => '0,' . $limit,
        );
        if ($category_id > 0) {
            $args['filter[id_category_default]'] = (string)$category_id;
        }
        if ($on_sale) {
            $args['filter[on_sale]'] = '1';
        }
        if ($language_id > 0) {
            // "language" (not filter[]) makes multilingual fields come back
            // as plain strings in that language instead of an {id,value}[] array.
            $args['language'] = (string)$language_id;
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
        $currency = $this->get_shop_currency($shop_url, $api_key);

        return array_map(fn($product) => $this->normalize_product($product, $shop_url, $api_key, $currency), $raw_products);
    }

    private function normalize_product(array $product, string $shop_url, string $api_key, array $currency): array
    {
        $id = absint($product['id'] ?? 0);

        return array(
            'id' => $id,
            'name' => wp_strip_all_tags($this->localized_value($product['name'] ?? '')),
            'description' => wp_strip_all_tags($this->localized_value($product['description_short'] ?? '')),
            'image_url' => $this->build_image_url($shop_url, $api_key, $id, $product['id_default_image'] ?? ''),
            'permalink' => trailingslashit($shop_url) . 'index.php?controller=product&id_product=' . $id,
            'price' => isset($product['price']) ? $this->format_price((float)$product['price'], $currency) : '',
        );
    }

    private function format_price(float $amount, array $currency): string
    {
        $formatted = number_format($amount, $currency['precision'], '.', '');
        return '' !== $currency['symbol'] ? trim($formatted . ' ' . $currency['symbol']) : $formatted;
    }

    /**
     * PrestaShop returns multilingual fields as a plain string (scoped
     * request) or an array of {id, value} pairs (unscoped) - normalize both.
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

    /**
     * Maps the current site language (Polylang or WPML) to a matching
     * PrestaShop language id, or 0 if neither is active or nothing matches.
     */
    private function resolve_language_id(string $shop_url, string $api_key): int
    {
        $iso_code = $this->get_current_language_iso_code();
        if ('' === $iso_code) {
            return 0;
        }

        foreach ($this->get_shop_languages($shop_url, $api_key) as $language) {
            if ($iso_code === $language['iso_code']) {
                return $language['id'];
            }
        }

        return 0;
    }

    // Polylang first, then WPML; both active at once isn't realistic outside a migration.
    private function get_current_language_iso_code(): string
    {
        $iso_code = $this->get_current_polylang_iso_code();
        if ('' !== $iso_code) {
            return $iso_code;
        }

        return $this->get_current_wpml_iso_code();
    }

    // Polylang's "slug" is admin-editable and not guaranteed ISO 639-1
    // (could be renamed to "deutsch"), so the locale is used instead.
    private function get_current_polylang_iso_code(): string
    {
        if (!function_exists('pll_current_language')) {
            return '';
        }

        $locale = pll_current_language('locale');
        return $locale ? strtolower(substr($locale, 0, 2)) : '';
    }

    // Same issue as Polylang's slug: WPML's own codes aren't always ISO
    // 639-1 (e.g. "zh-hans"), so its per-language default_locale is used instead.
    private function get_current_wpml_iso_code(): string
    {
        if (!defined('ICL_SITEPRESS_VERSION')) {
            return '';
        }

        $current_code = apply_filters('wpml_current_language', null);
        if (empty($current_code)) {
            return '';
        }

        $languages = apply_filters('wpml_active_languages', null, array('skip_missing' => 0));
        $locale = $languages[$current_code]['default_locale'] ?? '';

        return $locale ? strtolower(substr($locale, 0, 2)) : '';
    }

    /**
     * @return array<int, array{id: int, iso_code: string}>
     */
    private function get_shop_languages(string $shop_url, string $api_key): array
    {
        $cache_key = 'prestaspot_languages_' . md5($shop_url);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $url = add_query_arg(array(
            'display' => '[id,iso_code]',
            'output_format' => 'JSON',
        ), trailingslashit($shop_url) . 'api/languages');

        $response = wp_remote_get($url, array(
            'headers' => array('Authorization' => 'Basic ' . base64_encode($api_key . ':')),
            'timeout' => 10,
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return array();
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $languages = array_map(
            fn($language) => array(
                'id' => absint($language['id'] ?? 0),
                'iso_code' => strtolower((string)($language['iso_code'] ?? '')),
            ),
            $body['languages'] ?? array()
        );

        // Shop languages change rarely, so this is cached longer than products.
        set_transient($cache_key, $languages, DAY_IN_SECONDS);

        return $languages;
    }

    /**
     * The webservice doesn't expose which currency is the shop's default, so
     * this picks the first active one - correct for the common single-currency
     * case, best-effort otherwise. Requires GET access to the "currencies"
     * resource; falls back to a bare number (no symbol) if that's missing.
     *
     * @return array{symbol: string, precision: int}
     */
    private function get_shop_currency(string $shop_url, string $api_key): array
    {
        $cache_key = 'prestaspot_currency_' . md5($shop_url);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $currency = array('symbol' => '', 'precision' => 2);

        $args = array(
            'display' => '[symbol,precision]',
            'output_format' => 'JSON',
            'filter[active]' => '1',
            'limit' => '0,1',
        );
        // Without a "language" scope, a shop with more than one language but
        // an untranslated currency symbol in one of them makes the webservice
        // choke on its own multilingual JSON and return a 500, even though
        // the (empty-translation) payload underneath is otherwise fine.
        $languages = $this->get_shop_languages($shop_url, $api_key);
        if (!empty($languages)) {
            $args['language'] = (string)$languages[0]['id'];
        }

        $url = add_query_arg($args, trailingslashit($shop_url) . 'api/currencies');

        $response = wp_remote_get($url, array(
            'headers' => array('Authorization' => 'Basic ' . base64_encode($api_key . ':')),
            'timeout' => 10,
        ));

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $first = $body['currencies'][0] ?? null;
            if ($first) {
                $currency = array(
                    'symbol' => $this->localized_value($first['symbol'] ?? ''),
                    'precision' => absint($first['precision'] ?? 2),
                );
            }
        }

        set_transient($cache_key, $currency, DAY_IN_SECONDS);

        return $currency;
    }
}
