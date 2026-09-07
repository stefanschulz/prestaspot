<?php

declare(strict_types=1);

namespace PrestaSpot\Tests;

use Presta_Spot_Settings;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Covers Presta_Spot_Settings::get_layout_element_order() - the element
 * order a card's `image`/`name`/`description` render in, before the
 * renderer splices in `price`/`stock` (see .doc/ARCHITECTURE.md).
 */
final class LayoutElementOrderTest extends TestCase
{
    #[Test]
    public function returnsTheOrderForARecognizedLayout(): void
    {
        $this->assertSame(
            ['name', 'image', 'description'],
            Presta_Spot_Settings::get_layout_element_order('name_image_description')
        );
    }

    #[Test]
    public function fallsBackToTheDefaultLayoutForAnUnrecognizedValue(): void
    {
        $default = Presta_Spot_Settings::get_layout_element_order(
            Presta_Spot_Settings::PRESTASPOT_SETTINGS_DEFAULTS[Presta_Spot_Settings::LAYOUT]
        );

        $this->assertSame($default, Presta_Spot_Settings::get_layout_element_order('not-a-real-layout'));
        $this->assertSame($default, Presta_Spot_Settings::get_layout_element_order(''));
    }
}
