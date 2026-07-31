<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Tests\Unit;

use MyParcelNL\OpenCart\Core\Helper\CountryOptions;
use PHPUnit\Framework\TestCase;

class CountryOptionsTest extends TestCase
{
    public function testMapsIsoCodeToName(): void
    {
        $options = CountryOptions::fromOcCountries([
            ['iso_code_2' => 'NL', 'name' => 'Netherlands'],
            ['iso_code_2' => 'DE', 'name' => 'Germany'],
        ]);

        self::assertSame(['NL' => 'Netherlands', 'DE' => 'Germany'], $options);
    }

    public function testSkipsCountriesWithoutIsoCode(): void
    {
        $options = CountryOptions::fromOcCountries([
            ['iso_code_2' => '', 'name' => 'No code'],
            ['iso_code_2' => 'BE', 'name' => 'Belgium'],
        ]);

        self::assertSame(['BE' => 'Belgium'], $options);
    }
}
