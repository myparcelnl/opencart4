<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Tests\Unit;

use MyParcelNL\OpenCart\Core\Dto\CarrierCatalog;
use PHPUnit\Framework\TestCase;

final class CarrierCatalogTest extends TestCase
{
    public function testBuildsIdSlugNameAndLogoMappingsFromThePublicResponse(): void
    {
        $catalog = CarrierCatalog::fromApiResponse([
            'data' => ['carriers' => [[
                'id' => 1,
                'name' => 'postnl',
                'human' => 'PostNL',
                'meta' => [
                    'logo_svg' => '/skin/general-images/carrier-logos/svg/24/postnl.svg',
                ],
            ]]],
        ]);

        self::assertSame('postnl', $catalog->slugForId(1));
        self::assertSame('PostNL', $catalog->nameForSlug('postnl'));
        self::assertSame(
            'https://assets.myparcel.nl/skin/general-images/carrier-logos/svg/24/postnl.svg',
            $catalog->logoUrlForSlug('postnl')
        );
    }

    public function testAcceptsTheDotUsedByTheBolComSlugAndAsset(): void
    {
        $catalog = CarrierCatalog::fromApiResponse([
            'data' => ['carriers' => [[
                'id' => 7,
                'name' => 'bol.com',
                'human' => 'Bol.com',
                'meta' => ['logo_svg' => '/skin/general-images/carrier-logos/svg/bol.com.svg'],
            ]]],
        ]);

        self::assertSame('Bol.com', $catalog->nameForSlug('bol.com'));
    }

    /** @dataProvider invalidRows */
    public function testRejectsUnsafeCatalogRows(array $row): void
    {
        $this->expectException(\UnexpectedValueException::class);

        CarrierCatalog::fromApiResponse(['data' => ['carriers' => [$row]]]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidRows(): iterable
    {
        yield 'non-positive id' => [[
            'id' => 0,
            'name' => 'postnl',
            'human' => 'PostNL',
            'meta' => ['logo_svg' => '/skin/general-images/carrier-logos/svg/postnl.svg'],
        ]];
        yield 'unsafe slug' => [[
            'id' => 1,
            'name' => '../postnl',
            'human' => 'PostNL',
            'meta' => ['logo_svg' => '/skin/general-images/carrier-logos/svg/postnl.svg'],
        ]];
        yield 'unsafe logo path' => [[
            'id' => 1,
            'name' => 'postnl',
            'human' => 'PostNL',
            'meta' => ['logo_svg' => 'https://example.test/postnl.svg'],
        ]];
    }
}
