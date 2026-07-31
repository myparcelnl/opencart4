<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Tests\Unit;

use MyParcelNL\OpenCart\Core\Dto\CarrierCatalog;
use MyParcelNL\OpenCart\Core\Service\Carrier\CarrierResolver;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\RefCapabilitiesSharedCarrierV2;
use PHPUnit\Framework\TestCase;

final class CarrierResolverTest extends TestCase
{
    public function testResolvesAConfiguredCatalogIdThroughTheSdkSlugBridge(): void
    {
        $catalog = CarrierCatalog::fromApiResponse([
            'data' => ['carriers' => [[
                'id' => 12,
                'name' => 'upsstandard',
                'human' => 'UPS Standard',
                'meta' => ['logo_svg' => '/skin/general-images/carrier-logos/svg/upsstandard.svg'],
            ]]],
        ]);

        self::assertSame(
            RefCapabilitiesSharedCarrierV2::UPS_STANDARD,
            (new CarrierResolver())->valueForLegacyId(12, $catalog)
        );
    }

    public function testGeneratedEnumsKeepDefaultResolutionWorkingDuringCatalogOutage(): void
    {
        self::assertSame(
            RefCapabilitiesSharedCarrierV2::POSTNL,
            (new CarrierResolver())->valueForLegacyId(1, CarrierCatalog::empty())
        );
        self::assertSame(
            RefCapabilitiesSharedCarrierV2::VIA_TIM,
            (new CarrierResolver())->valueForLegacyId(20, CarrierCatalog::empty())
        );
    }

    public function testUnknownLegacyIdDoesNotProduceAnUntypedCarrier(): void
    {
        self::assertNull((new CarrierResolver())->valueForLegacyId(999, CarrierCatalog::empty()));
    }
}
