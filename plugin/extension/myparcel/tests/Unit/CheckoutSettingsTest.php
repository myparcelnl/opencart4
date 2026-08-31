<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Tests\Unit;

use MyParcelNL\OpenCart\Core\Settings\CheckoutSettings;
use PHPUnit\Framework\TestCase;

class CheckoutSettingsTest extends TestCase
{
    public function testAppliesDefaults(): void
    {
        $checkout = CheckoutSettings::fromArray([]);

        self::assertTrue($checkout->deliveryOptionsEnabled);
        self::assertSame(0, $checkout->deliveryDaysWindow);
        self::assertSame(0, $checkout->dropOffDelay);
        self::assertSame(CheckoutSettings::VIEW_LIST, $checkout->pickupLocationsDefaultView);
        self::assertTrue($checkout->allowPickupLocationsViewSelection);
    }

    public function testReadsValues(): void
    {
        $checkout = CheckoutSettings::fromArray([
            'delivery_options_enabled' => '0',
            'delivery_days_window' => '5',
            'drop_off_delay' => '2',
            'pickup_locations_default_view' => 'map',
        ]);

        self::assertFalse($checkout->deliveryOptionsEnabled);
        self::assertSame(5, $checkout->deliveryDaysWindow);
        self::assertSame(2, $checkout->dropOffDelay);
        self::assertSame(CheckoutSettings::VIEW_MAP, $checkout->pickupLocationsDefaultView);
    }

    public function testInvalidViewFallsBackToList(): void
    {
        self::assertSame(
            CheckoutSettings::VIEW_LIST,
            CheckoutSettings::fromArray(['pickup_locations_default_view' => 'grid'])->pickupLocationsDefaultView
        );
    }

    public function testToWidgetConfigExcludesTheEnableFlag(): void
    {
        $config = CheckoutSettings::fromArray([])->toWidgetConfig();

        self::assertArrayNotHasKey('deliveryOptionsEnabled', $config);
        self::assertArrayNotHasKey('showDeliveryDate', $config);
        self::assertArrayHasKey('deliveryDaysWindow', $config);
        self::assertArrayHasKey('popUpMap', $config);
    }

    public function testToArrayRoundTrips(): void
    {
        $array = CheckoutSettings::fromArray([
            'delivery_options_enabled' => false,
            'delivery_days_window' => 3,
            'pickup_locations_default_view' => 'map',
        ])->toArray();

        self::assertFalse($array['delivery_options_enabled']);
        self::assertSame(3, $array['delivery_days_window']);
        self::assertSame('map', $array['pickup_locations_default_view']);
    }
}
