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

    public function get_products(int $limit, int $category_id = 0, bool $on_sale = false, string $sort = '', int $language_override_id = 0): array
    {
        $settings = $this->settings->get_all();
        $shop_url = $settings[Presta_Spot_Settings::SHOP_URL];
        $api_key = $settings[Presta_Spot_Settings::API_KEY];

        if (empty($shop_url) || empty($api_key)) {
            return array();
        }

        $limit = max(1, $limit);
        if ($language_override_id > 0) {
            $language_id = $language_override_id;
        } else {
            $language_id = $this->resolve_language_id($shop_url, $api_key);
            if (0 === $language_id) {
                // Unscoped multilingual fields can 500 the webservice - same fix as get_shop_currency() below.
                $shop_languages = $this->get_shop_languages($shop_url, $api_key);
                if (!empty($shop_languages)) {
                    $language_id = $shop_languages[0]['id'];
                }
            }
        }
        $cache_key = 'prestaspot_products_' . md5($shop_url . '|' . $limit . '|' . $category_id . '|' . $language_id . '|' . ($on_sale ? '1' : '0') . '|' . $sort);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $products = $this->fetch_products($shop_url, $api_key, $limit, $category_id, $language_id, $on_sale, $sort);

        set_transient($cache_key, $products, $settings[Presta_Spot_Settings::CACHE_DURATION]);

        return $products;
    }

    private function fetch_products(string $shop_url, string $api_key, int $limit, int $category_id, int $language_id, bool $on_sale, string $sort): array
    {
        $args = array(
            // "price" is tax-excluded - the tax-aware computed-price mechanism only works per-product, not on this list endpoint.
            // Stock is deliberately NOT requested here - "quantity" has no getter on Product's webservice
            // mapping and always comes back 0; real stock is fetched separately via get_stock_quantities().
            'display' => '[id,name,description_short,link_rewrite,id_default_image,price,on_sale]',
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
        $args = array_merge($args, $this->build_sort_args($sort));

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
        $quantities = $this->is_stock_managed($shop_url, $api_key)
            ? $this->get_stock_quantities($shop_url, $api_key, array_map(fn($product) => absint($product['id'] ?? 0), $raw_products))
            : array();

        return array_map(fn($product) => $this->normalize_product($product, $shop_url, $api_key, $currency, $quantities), $raw_products);
    }

    private function build_sort_args(string $sort): array
    {
        $fields = array(
            Presta_Spot_Settings::SORT_NAME_ASC => array('name', 'ASC'),
            Presta_Spot_Settings::SORT_NAME_DESC => array('name', 'DESC'),
            Presta_Spot_Settings::SORT_PRICE_ASC => array('price', 'ASC'),
            Presta_Spot_Settings::SORT_PRICE_DESC => array('price', 'DESC'),
            Presta_Spot_Settings::SORT_DATE_ASC => array('date_add', 'ASC'),
            Presta_Spot_Settings::SORT_DATE_DESC => array('date_add', 'DESC'),
        );
        if (!isset($fields[$sort])) {
            return array();
        }

        [$field, $direction] = $fields[$sort];
        $args = array('sort' => $field . '_' . $direction);
        if ('date_add' === $field) {
            // date_add needs this flag or the webservice rejects it outright.
            $args['date'] = '1';
        }

        return $args;
    }

    private function normalize_product(array $product, string $shop_url, string $api_key, array $currency, array $quantities): array
    {
        $id = absint($product['id'] ?? 0);

        return array(
            'id' => $id,
            'name' => wp_strip_all_tags($this->localized_value($product['name'] ?? '')),
            'description' => wp_strip_all_tags($this->localized_value($product['description_short'] ?? '')),
            'image_url' => $this->build_image_url($shop_url, $api_key, $id, $product['id_default_image'] ?? ''),
            'permalink' => trailingslashit($shop_url) . 'index.php?controller=product&id_product=' . $id,
            'price' => isset($product['price']) ? $this->format_price((float)$product['price'], $currency) : '',
            'on_sale' => !empty($product['on_sale']),
            // '' when the shop doesn't track stock, or this product's quantity couldn't be fetched.
            'stock_status' => isset($quantities[$id]) ? ($quantities[$id] > 0 ? 'in_stock' : 'out_of_stock') : '',
        );
    }

    /**
     * Bulk-fetches real stock quantities for the given product ids in one request.
     * Not from /api/products - its "quantity" field has no getter and always
     * reads back 0 (confirmed against PrestaShop's own Product class source).
     * id_product_attribute=0 is PrestaShop's own maintained aggregate row -
     * a plain product's own stock, or the sum across all its combinations.
     *
     * @param int[] $product_ids
     * @return array<int, int> id_product => quantity
     */
    private function get_stock_quantities(string $shop_url, string $api_key, array $product_ids): array
    {
        $product_ids = array_values(array_unique(array_filter($product_ids)));
        if (empty($product_ids)) {
            return array();
        }

        $url = add_query_arg(array(
            'display' => '[id_product,quantity]',
            'output_format' => 'JSON',
            'filter[id_product]' => '[' . implode('|', $product_ids) . ']',
            'filter[id_product_attribute]' => '0',
        ), trailingslashit($shop_url) . 'api/stock_availables');

        $response = wp_remote_get($url, array(
            'headers' => array('Authorization' => 'Basic ' . base64_encode($api_key . ':')),
            'timeout' => 10,
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return array();
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $quantities = array();
        foreach ($body['stock_availables'] ?? array() as $stock) {
            $quantities[absint($stock['id_product'] ?? 0)] = (int)($stock['quantity'] ?? 0);
        }

        return $quantities;
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
     * @return array<int, array{id: int, name: string, iso_code: string}>
     */
    private function get_shop_languages(string $shop_url, string $api_key): array
    {
        $cache_key = 'prestaspot_languages_' . md5($shop_url);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $url = add_query_arg(array(
            'display' => '[id,name,iso_code]',
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
        // Unlike product/category names, a language's own "name" isn't itself
        // translated per-language - it's already a single plain string.
        $languages = array_map(
            fn($language) => array(
                'id' => absint($language['id'] ?? 0),
                'name' => (string)($language['name'] ?? ''),
                'iso_code' => strtolower((string)($language['iso_code'] ?? '')),
            ),
            $body['languages'] ?? array()
        );

        // Shop languages change rarely, so this is cached longer than products.
        set_transient($cache_key, $languages, DAY_IN_SECONDS);

        return $languages;
    }

    /**
     * Public (unlike get_shop_languages()) - backs the block editor's language
     * picker (see index.js) and the shortcode's language-code resolution below.
     *
     * @return array<int, array{id: int, name: string, iso_code: string}>
     */
    public function get_languages(): array
    {
        $settings = $this->settings->get_all();
        $shop_url = $settings[Presta_Spot_Settings::SHOP_URL];
        $api_key = $settings[Presta_Spot_Settings::API_KEY];

        if (empty($shop_url) || empty($api_key)) {
            return array();
        }

        return $this->get_shop_languages($shop_url, $api_key);
    }

    /**
     * Case-insensitive match against PrestaShop's own iso_code (e.g. "de", or
     * "gb" for English - not always the ISO code you'd expect); 0 (= automatic
     * detection) if nothing matches.
     */
    public function resolve_language_id_by_code(string $code): int
    {
        $code = strtolower($code);
        foreach ($this->get_languages() as $language) {
            if ($code === $language['iso_code']) {
                return $language['id'];
            }
        }

        return 0;
    }

    /**
     * No "default currency" flag exists on this resource, so this just picks the first active one.
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
        // Unscoped + untranslated symbol can 500 the webservice.
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

    /**
     * Shop-wide "is stock even tracked" flag (PS_STOCK_MANAGEMENT) - a demo/import
     * shop can have every product's quantity sitting at an unpopulated 0, so
     * this is checked before treating quantity as meaningful. Defaults to
     * false (don't show stock status) on any failure, since a false "out of
     * stock" is worse than not showing the indicator at all.
     */
    private function is_stock_managed(string $shop_url, string $api_key): bool
    {
        $cache_key = 'prestaspot_stock_managed_' . md5($shop_url);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return '1' === $cached;
        }

        $managed = '0';

        $url = add_query_arg(array(
            'display' => '[name,value]',
            'output_format' => 'JSON',
            'filter[name]' => 'PS_STOCK_MANAGEMENT',
        ), trailingslashit($shop_url) . 'api/configurations');

        $response = wp_remote_get($url, array(
            'headers' => array('Authorization' => 'Basic ' . base64_encode($api_key . ':')),
            'timeout' => 10,
        ));

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $first = $body['configurations'][0] ?? null;
            if ($first && !empty($first['value'])) {
                $managed = '1';
            }
        }

        set_transient($cache_key, $managed, DAY_IN_SECONDS);

        return '1' === $managed;
    }

    /**
     * Public (unlike the other shop-data getters) - also used by the REST route in Presta_Spot_Block.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function get_categories(): array
    {
        $settings = $this->settings->get_all();
        $shop_url = $settings[Presta_Spot_Settings::SHOP_URL];
        $api_key = $settings[Presta_Spot_Settings::API_KEY];

        if (empty($shop_url) || empty($api_key)) {
            return array();
        }

        $cache_key = 'prestaspot_categories_' . md5($shop_url);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $args = array(
            'display' => '[id,name]',
            'output_format' => 'JSON',
            'filter[active]' => '1',
        );
        // Same untranslated-field 500 as get_shop_currency() above.
        $languages = $this->get_shop_languages($shop_url, $api_key);
        if (!empty($languages)) {
            $args['language'] = (string)$languages[0]['id'];
        }

        $url = add_query_arg($args, trailingslashit($shop_url) . 'api/categories');

        $response = wp_remote_get($url, array(
            'headers' => array('Authorization' => 'Basic ' . base64_encode($api_key . ':')),
            'timeout' => 10,
        ));

        $categories = array();
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $categories = array_map(
                fn($category) => array(
                    'id' => absint($category['id'] ?? 0),
                    'name' => $this->localized_value($category['name'] ?? ''),
                ),
                $body['categories'] ?? array()
            );
            usort($categories, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
        }

        // Shop categories change rarely, cached like languages/currency.
        set_transient($cache_key, $categories, DAY_IN_SECONDS);

        return $categories;
    }

    /**
     * Case-insensitive exact match; 0 (= no filter) if nothing matches.
     */
    public function resolve_category_id_by_name(string $name): int
    {
        foreach ($this->get_categories() as $category) {
            if (strtolower($name) === strtolower($category['name'])) {
                return $category['id'];
            }
        }

        return 0;
    }
}
