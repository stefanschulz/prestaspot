<?php

declare(strict_types=1);

namespace PrestaSpot\Tests;

use Presta_Spot_Api;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Covers Presta_Spot_Api::format_price() - amount + currency shape (as
 * returned by get_shop_currency()) to the display string shown on a card.
 */
final class FormatPriceTest extends TestCase
{
    #[Test]
    public function formatsWithSymbolAndPrecision(): void
    {
        $this->assertSame('23.90 €', Presta_Spot_Api::format_price(23.9, ['symbol' => '€', 'precision' => 2]));
    }

    #[Test]
    public function omitsTheSymbolWhenTheCurrencyLookupFailed(): void
    {
        // get_shop_currency() falls back to '' on any failure - format_price()
        // must still produce a plain number rather than a dangling space.
        $this->assertSame('23.90', Presta_Spot_Api::format_price(23.9, ['symbol' => '', 'precision' => 2]));
    }

    #[Test]
    public function respectsAZeroPrecisionCurrency(): void
    {
        $this->assertSame('24 ¥', Presta_Spot_Api::format_price(23.9, ['symbol' => '¥', 'precision' => 0]));
    }
}
