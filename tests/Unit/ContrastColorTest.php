<?php

declare(strict_types=1);

namespace PrestaSpot\Tests;

use Presta_Spot_Renderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Covers Presta_Spot_Renderer::get_contrasting_text_color() - picks
 * readable button/sale-badge text over an admin-configured background,
 * so contrast can't regress no matter what color is set (see the "Automatic
 * contrast text" note in .doc/ARCHITECTURE.md).
 */
final class ContrastColorTest extends TestCase
{
    public static function colorProvider(): array
    {
        return [
            'PrestaSpot default button blue (#2271b1) gets white text' => ['#2271b1', '#ffffff'],
            'PrestaSpot default sale-badge red (#e63946) gets white text' => ['#e63946', '#ffffff'],
            'pure white gets black text' => ['#ffffff', '#000000'],
            'pure black gets white text' => ['#000000', '#ffffff'],
            'dark navy gets white text' => ['#1a1a2e', '#ffffff'],
            'saturated red gets white text' => ['#ff0000', '#ffffff'],
            'saturated green gets black text' => ['#00ff00', '#000000'],
        ];
    }

    #[Test]
    #[DataProvider('colorProvider')]
    public function picksReadableTextColor(string $background, string $expectedTextColor): void
    {
        $this->assertSame($expectedTextColor, Presta_Spot_Renderer::get_contrasting_text_color($background));
    }

    #[Test]
    public function acceptsAHexColorWithoutTheLeadingHash(): void
    {
        $this->assertSame(
            Presta_Spot_Renderer::get_contrasting_text_color('#2271b1'),
            Presta_Spot_Renderer::get_contrasting_text_color('2271b1')
        );
    }
}
