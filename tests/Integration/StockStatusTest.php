<?php

declare(strict_types=1);

/**
 * Exercises the stock-status gating documented in .doc/ARCHITECTURE.md's
 * "Stock status" section - product 3 is a persisted fixture with quantity=0
 * (see .doc/DEVELOPER_GUIDE.md's "Testing stock status" section).
 *
 * Deliberately doesn't toggle PS_STOCK_MANAGEMENT off here to cover the
 * "shop doesn't track stock" fallback - that mutates PrestaShop's own
 * database (a different system than the one this test suite owns), and
 * DEVELOPER_GUIDE.md already documents it as a manual, deliberately
 * temporary step rather than a standing fixture. Read-only with respect to
 * the shop's data, same as the other Integration tests in this suite.
 */
final class StockStatusTest extends IntegrationTestCase
{
    private Presta_Spot_Api $api;

    public function set_up(): void
    {
        parent::set_up();
        $this->api = new Presta_Spot_Api(new Presta_Spot_Settings());
    }

    public function test_a_product_with_zero_quantity_is_out_of_stock(): void
    {
        // Category 0 = no filter, so this pulls in product 3 (the poster)
        // alongside 1 and 2 - limit high enough to see all three fixtures.
        $products = $this->api->get_products(10);
        $by_name = array_column($products, null, 'name');

        $this->assertArrayHasKey("The best is yet to come' Framed poster", $by_name);
        $this->assertSame('out_of_stock', $by_name["The best is yet to come' Framed poster"]['stock_status']);
    }

    public function test_products_with_real_stock_are_in_stock(): void
    {
        $products = $this->api->get_products(10);
        $by_name = array_column($products, null, 'name');

        $this->assertSame('in_stock', $by_name['Hummingbird printed t-shirt']['stock_status']);
        $this->assertSame('in_stock', $by_name['Hummingbird printed sweater']['stock_status']);
    }
}
