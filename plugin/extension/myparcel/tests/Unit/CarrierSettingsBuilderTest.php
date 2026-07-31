<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Tests\Unit;

use MyParcelNL\OpenCart\Core\Service\DeliveryOptions\CarrierSettingsBuilder;
use PHPUnit\Framework\TestCase;

class CarrierSettingsBuilderTest extends TestCase
{
    public function testPostV1ServicesAreNotExposed(): void
    {
        $services = (new CarrierSettingsBuilder())->serviceKeys();

        self::assertNotContains('priority', $services);
        self::assertNotContains('saturday', $services);
    }

    public function testShipmentOptionKeysAreDerivedFromSupportedServices(): void
    {
        self::assertSame(
            ['signature', 'only_recipient'],
            (new CarrierSettingsBuilder())->shipmentOptionKeys()
        );
    }
}
