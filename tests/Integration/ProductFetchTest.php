<?php

declare(strict_types=1);

/**
 * Exercises Presta_Spot_Api::get_products() against the real, disposable
 * PrestaShop instance (see bootstrap-integration.php) - the same round trip
 * verified by hand (curl + browser) throughout this project's development.
 *
 * Assumes the fixture data documented in .doc/DEVELOPER_GUIDE.md: category 4
 * ("Men") contains only product 1 ("Hummingbird printed t-shirt", on sale,
 * in stock), and product 1 has a German translation ("Kolibri-bedrucktes
 * T-Shirt") while products 2/3 don't.
 */
final class ProductFetchTest extends IntegrationTestCase
{
    private Presta_Spot_Api $api;

    public function set_up(): void
    {
        parent::set_up();
        $this->api = new Presta_Spot_Api(new Presta_Spot_Settings());
    }

    public function test_fetches_the_one_product_in_a_category(): void
    {
        $products = $this->api->get_products(5, 4);

        $this->assertCount(1, $products);
        $this->assertSame('Hummingbird printed t-shirt', $products[0]['name']);
        $this->assertSame('23.90 €', $products[0]['price']);
        $this->assertTrue($products[0]['on_sale']);
        $this->assertSame('in_stock', $products[0]['stock_status']);
    }

    public function test_language_override_returns_the_german_translation(): void
    {
        $products = $this->api->get_products(5, 4, false, '', 2);

        $this->assertSame('Kolibri-bedrucktes T-Shirt', $products[0]['name']);
    }

    public function test_language_id_1_returns_english_regardless_of_site_language(): void
    {
        $products = $this->api->get_products(5, 4, false, '', 1);

        $this->assertSame('Hummingbird printed t-shirt', $products[0]['name']);
    }

    public function test_returns_no_products_without_shop_credentials(): void
    {
        update_option('prestaspot_shop_url', '');

        $this->assertSame([], $this->api->get_products(5));
    }
}
