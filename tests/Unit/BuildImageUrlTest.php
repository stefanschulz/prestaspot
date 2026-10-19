<?php

declare(strict_types=1);

namespace PrestaSpot\Tests;

use Presta_Spot_Api;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Covers Presta_Spot_Api::build_image_url() - the public /img/p/ path that
 * replaced the webservice route (which needed the API key in the page HTML).
 */
final class BuildImageUrlTest extends TestCase
{
    #[Test]
    public function splitsTheImageIdIntoOneFolderPerDigit(): void
    {
        $this->assertSame(
            'https://shop.example/img/p/3/5/3/4/3534-medium_default.jpg',
            Presta_Spot_Api::build_image_url('https://shop.example', '3534', 'medium_default')
        );
        $this->assertSame(
            'https://shop.example/img/p/1/1-home_default.jpg',
            Presta_Spot_Api::build_image_url('https://shop.example', 1, 'home_default')
        );
    }

    #[Test]
    public function acceptsTheIdValueWrapperThatUnscopedRequestsReturn(): void
    {
        $this->assertSame(
            'https://shop.example/img/p/2/1/21-large_default.jpg',
            Presta_Spot_Api::build_image_url('https://shop.example', ['value' => '21'], 'large_default')
        );
    }

    #[Test]
    public function neverContainsAKeyOrTheWebserviceRoute(): void
    {
        $url = Presta_Spot_Api::build_image_url('https://shop.example/', '1791', 'medium_default');

        $this->assertStringNotContainsString('ws_key', $url);
        $this->assertStringNotContainsString('/api/', $url);
        $this->assertStringNotContainsString('?', $url);
    }

    #[Test]
    public function returnsEmptyWithoutAUsableImageId(): void
    {
        foreach (['', '0', 0, null, 'abc', '12/../3', '-5', '1.5'] as $id) {
            $this->assertSame('', Presta_Spot_Api::build_image_url('https://shop.example', $id, 'medium_default'), var_export($id, true));
        }
    }

    #[Test]
    public function returnsEmptyForAnInvalidImageType(): void
    {
        $this->assertSame('', Presta_Spot_Api::build_image_url('https://shop.example', '5', '../x'));
        $this->assertSame('', Presta_Spot_Api::build_image_url('https://shop.example', '5', ''));
    }
}
