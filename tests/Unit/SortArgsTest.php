<?php

declare(strict_types=1);

namespace PrestaSpot\Tests;

use Presta_Spot_Api;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Covers Presta_Spot_Api::build_sort_args() - maps a `sort` setting/attribute
 * value to the webservice's own `sort=field_DIRECTION` query args.
 */
final class SortArgsTest extends TestCase
{
    #[Test]
    public function mapsNameAscending(): void
    {
        $this->assertSame(['sort' => 'name_ASC'], Presta_Spot_Api::build_sort_args('name_asc'));
    }

    #[Test]
    public function mapsPriceDescending(): void
    {
        $this->assertSame(['sort' => 'price_DESC'], Presta_Spot_Api::build_sort_args('price_desc'));
    }

    #[Test]
    public function dateSortAddsTheDateFlagTheWebserviceOtherwiseRejects(): void
    {
        $this->assertSame(
            ['sort' => 'date_add_DESC', 'date' => '1'],
            Presta_Spot_Api::build_sort_args('date_desc')
        );
        $this->assertSame(
            ['sort' => 'date_add_ASC', 'date' => '1'],
            Presta_Spot_Api::build_sort_args('date_asc')
        );
    }

    #[Test]
    public function unrecognizedOrEmptySortReturnsNoArgs(): void
    {
        $this->assertSame([], Presta_Spot_Api::build_sort_args(''));
        $this->assertSame([], Presta_Spot_Api::build_sort_args('not-a-real-sort'));
    }
}
