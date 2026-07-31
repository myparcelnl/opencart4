<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Tests\Unit;

use MyParcelNL\OpenCart\Core\Service\Shipment\ShipmentApiService;
use PHPUnit\Framework\TestCase;

final class ShipmentApiServiceTest extends TestCase
{
    public function testUsesTheAcceptanceCoreHostForEveryShipmentSdkClient(): void
    {
        self::assertSame(
            'https://api.acceptance.myparcel.nl',
            $this->hostFor(new ShipmentApiService('test-key', true))
        );
    }

    public function testKeepsTheSdkDefaultHostInProduction(): void
    {
        self::assertNull($this->hostFor(new ShipmentApiService('test-key', false)));
    }

    private function hostFor(ShipmentApiService $service): ?string
    {
        $host = new \ReflectionProperty($service, 'host');

        return $host->getValue($service);
    }
}
