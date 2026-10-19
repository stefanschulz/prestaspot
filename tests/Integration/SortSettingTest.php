<?php

declare(strict_types=1);

/**
 * Regression: the settings-page "Sort By" field wasn't registered with
 * register_setting(), so options.php silently dropped it on save.
 */
final class SortSettingTest extends IntegrationTestCase
{
    public function test_sort_is_registered_and_sanitized(): void
    {
        ( new Presta_Spot_Admin(new Presta_Spot_Settings()) )->register_settings();

        $this->assertArrayHasKey('prestaspot_sort', get_registered_settings());
        $this->assertSame('price_asc', sanitize_option('prestaspot_sort', 'price_asc'));
        $this->assertSame('', sanitize_option('prestaspot_sort', 'bogus'));
    }
}
