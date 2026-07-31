<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Tests\Unit;

use MyParcelNL\OpenCart\Core\Service\ApiConfigFactory;
use MyParcelNL\OpenCart\Core\Service\DefaultCarrierService;
use PHPUnit\Framework\TestCase;

class DefaultCarrierServiceTest extends TestCase
{
    public function testConfiguresPrivateCoreApiForAcceptance(): void
    {
        $config = (new ApiConfigFactory())->forCorePrivate('test-key', true);

        self::assertSame(base64_encode('test-key'), $config->getAccessToken());
        self::assertSame('https://api.acceptance.myparcel.nl', $config->getHost());
    }

    public function testUsesResolvedCarrierWhenItIsAvailable(): void
    {
        self::assertSame('UPS_STANDARD', DefaultCarrierService::resolveAvailable(
            'UPS_STANDARD',
            'POSTNL',
            [['carrier' => 'POSTNL'], ['carrier' => 'UPS_STANDARD']]
        ));
    }

    public function testPreservesAvailablePreviousCarrierWhenApiResultIsMissing(): void
    {
        self::assertSame('POSTNL', DefaultCarrierService::resolveAvailable(
            null,
            'POSTNL',
            [['carrier' => 'POSTNL']]
        ));
    }

    public function testPreservesPreviousCarrierWhenApiResultIsNotContracted(): void
    {
        self::assertSame('POSTNL', DefaultCarrierService::resolveAvailable(
            'UPS_STANDARD',
            'POSTNL',
            [['carrier' => 'POSTNL']]
        ));
    }

    public function testRejectsCarriersWithoutACurrentContract(): void
    {
        self::assertNull(DefaultCarrierService::resolveAvailable(
            'UPS_STANDARD',
            'POSTNL',
            [['carrier' => 'DPD']]
        ));
    }
}
