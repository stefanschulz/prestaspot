<?php

declare(strict_types=1);

namespace PrestaSpot\Tests;

use Presta_Spot_Api;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Covers Presta_Spot_Api::localized_value() - PrestaShop returns a
 * multilingual field as a plain string when the request is language-scoped,
 * or as an array of {id, value} pairs when it isn't; this normalizes both
 * shapes to a plain string.
 */
final class LocalizedValueTest extends TestCase
{
    #[Test]
    public function passesThroughAPlainScopedString(): void
    {
        $this->assertSame('Hummingbird printed t-shirt', Presta_Spot_Api::localized_value('Hummingbird printed t-shirt'));
    }

    #[Test]
    public function unwrapsTheFirstEntryOfAnUnscopedMultilingualArray(): void
    {
        $unscoped = [
            ['id' => '1', 'value' => 'Hummingbird printed t-shirt'],
            ['id' => '2', 'value' => 'Kolibri-bedrucktes T-Shirt'],
        ];

        $this->assertSame('Hummingbird printed t-shirt', Presta_Spot_Api::localized_value($unscoped));
    }

    #[Test]
    public function returnsAnEmptyStringForAMissingOrEmptyValue(): void
    {
        $this->assertSame('', Presta_Spot_Api::localized_value(''));
        $this->assertSame('', Presta_Spot_Api::localized_value([]));
    }
}
