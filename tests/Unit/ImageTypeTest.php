<?php

declare(strict_types=1);

namespace PrestaSpot\Tests;

use Presta_Spot_Settings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Covers the image-type setting helpers. The type name ends up in a URL
 * path, so the validation is the security-relevant part.
 */
final class ImageTypeTest extends TestCase
{
    #[Test]
    #[DataProvider('standardTypes')]
    public function standardChoiceResolvesToItself(string $type): void
    {
        $this->assertSame($type, Presta_Spot_Settings::resolve_image_type($type, ''));
    }

    public static function standardTypes(): array
    {
        return array_map(fn(string $type) => [$type], Presta_Spot_Settings::IMAGE_TYPES);
    }

    #[Test]
    public function unknownChoiceFallsBackToDefault(): void
    {
        $this->assertSame('medium_default', Presta_Spot_Settings::resolve_image_type('bogus', ''));
        $this->assertSame('medium_default', Presta_Spot_Settings::resolve_image_type('', ''));
    }

    #[Test]
    public function customChoiceUsesTheCustomName(): void
    {
        $this->assertSame('my_card-1', Presta_Spot_Settings::resolve_image_type('custom', ' my_card-1 '));
    }

    #[Test]
    public function customChoiceWithoutValidNameFallsBackToDefault(): void
    {
        $this->assertSame('medium_default', Presta_Spot_Settings::resolve_image_type('custom', ''));
        $this->assertSame('medium_default', Presta_Spot_Settings::resolve_image_type('custom', '../x'));
    }

    #[Test]
    public function customNameIsIgnoredUnlessCustomIsChosen(): void
    {
        $this->assertSame('home_default', Presta_Spot_Settings::resolve_image_type('home_default', 'my_card'));
    }

    #[Test]
    #[DataProvider('invalidNames')]
    public function invalidNamesAreRejected(string $name): void
    {
        $this->assertSame('', Presta_Spot_Settings::sanitize_image_type_name($name));
    }

    public static function invalidNames(): array
    {
        return [
            'path traversal' => ['../../etc/passwd'],
            'slash' => ['a/b'],
            'dot' => ['a.jpg'],
            'space inside' => ['a b'],
            'query' => ['a?x=1'],
            'newline' => ["a\nb"],
            'empty' => [''],
        ];
    }

    #[Test]
    public function choiceSanitizerAcceptsStandardTypesAndCustomOnly(): void
    {
        $this->assertSame('large_default', Presta_Spot_Settings::sanitize_image_type_choice('large_default'));
        $this->assertSame('custom', Presta_Spot_Settings::sanitize_image_type_choice('custom'));
        $this->assertSame('medium_default', Presta_Spot_Settings::sanitize_image_type_choice('../x'));
    }
}
