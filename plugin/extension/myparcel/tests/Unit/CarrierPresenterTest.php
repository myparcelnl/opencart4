<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Tests\Unit;

use MyParcelNL\OpenCart\Core\Dto\CarrierCatalog;
use MyParcelNL\OpenCart\Core\Service\Carrier\CarrierPresenter;
use PHPUnit\Framework\TestCase;

final class CarrierPresenterTest extends TestCase
{
    public function testKnownCarrierUsesCatalogMetadata(): void
    {
        $catalog = CarrierCatalog::fromApiResponse([
            'data' => ['carriers' => [[
                'id' => 8,
                'name' => 'ups',
                'human' => 'UPS',
                'meta' => ['logo_svg' => '/skin/general-images/carrier-logos/svg/ups.svg'],
            ]]],
        ]);

        self::assertSame(
            'https://assets.myparcel.nl/skin/general-images/carrier-logos/svg/ups.svg',
            (new CarrierPresenter($catalog))->logoUrl('ups')
        );
        self::assertSame('UPS', (new CarrierPresenter($catalog))->nameForSlug('ups'));
    }

    public function testUnknownCarrierFallsBackWithoutBrokenImageUrl(): void
    {
        self::assertSame('', (new CarrierPresenter())->logoUrl('future-carrier'));
    }

    public function testUnknownCarrierHasAReadableFallback(): void
    {
        $presenter = new CarrierPresenter();

        self::assertSame('Future Carrier', $presenter->nameForSlug('future-carrier'));
    }
}
