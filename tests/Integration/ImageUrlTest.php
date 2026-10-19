<?php

declare(strict_types=1);

/**
 * Guards against the webservice key reaching public HTML (it used to be
 * appended to every image URL as ?ws_key=) and against stale cached lists
 * still carrying it after an update.
 */
final class ImageUrlTest extends IntegrationTestCase
{
    private Presta_Spot_Api $api;

    public function set_up(): void
    {
        parent::set_up();
        $this->api = new Presta_Spot_Api(new Presta_Spot_Settings());
        Presta_Spot_Upgrade::delete_product_transients();
    }

    public function test_image_urls_are_keyless_public_img_paths(): void
    {
        $products = $this->api->get_products(20);

        $this->assertNotEmpty($products);
        foreach ($products as $product) {
            if ('' === $product['image_url']) {
                continue;
            }
            $this->assertStringNotContainsString('ws_key', $product['image_url']);
            $this->assertMatchesRegularExpression('#/img/p/(\d/)+\d+-medium_default\.jpg$#', $product['image_url']);
        }
    }

    public function test_rendered_html_never_contains_the_api_key(): void
    {
        $renderer = new Presta_Spot_Renderer(new Presta_Spot_Settings(), $this->api);
        $html = $renderer->render(['product_count' => 20]);

        $this->assertStringContainsString('<img', $html);
        $this->assertStringNotContainsString('ws_key', $html);
        $this->assertStringNotContainsString((string)get_option('prestaspot_api_key'), $html);
    }

    public function test_image_urls_load_without_any_credentials(): void
    {
        $product = $this->api->get_products(1, 4)[0];
        $response = wp_remote_head($product['image_url']);

        $this->assertSame(200, wp_remote_retrieve_response_code($response));
        $this->assertSame('image/jpeg', wp_remote_retrieve_header($response, 'content-type'));
    }

    public function test_changing_the_image_type_is_not_served_from_the_old_cache(): void
    {
        $this->api->get_products(1, 4);
        update_option('prestaspot_image_type', 'home_default');

        $this->assertStringContainsString('-home_default.jpg', $this->api->get_products(1, 4)[0]['image_url']);
    }

    public function test_upgrade_drops_cached_lists_that_still_hold_the_key(): void
    {
        set_transient('prestaspot_products_legacy', array('image_url' => 'x?ws_key=secret'), HOUR_IN_SECONDS);
        update_option('prestaspot_installed_version', '0.0.1');

        Presta_Spot_Upgrade::maybe_run();

        $this->assertFalse(get_transient('prestaspot_products_legacy'));
        $this->assertSame(PRESTASPOT_VERSION, get_option('prestaspot_installed_version'));
    }
}
