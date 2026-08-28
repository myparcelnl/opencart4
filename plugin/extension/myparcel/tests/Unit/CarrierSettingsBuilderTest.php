<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Tests\Unit;

use MyParcelNL\OpenCart\Core\Service\DeliveryOptions\CarrierSettingsBuilder;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\RefCapabilitiesSharedCarrierV2;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\RefShipmentPackageTypeV2;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\RefTypesDeliveryTypeV2;
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

    public function testUsesConfiguredWidgetPackageTypeAndFiltersUnsupportedCarriers(): void
    {
        $builder = new CarrierSettingsBuilder();
        $carrierSettings = $builder->build(
            $this->capabilities(),
            [
                'postnl' => ['enabled' => true, 'services' => ['standard']],
                'upsstandard' => ['enabled' => true, 'services' => ['standard']],
            ],
            'mailbox'
        );

        self::assertSame(['postnl'], array_keys($carrierSettings));
        self::assertSame('mailbox', $carrierSettings['postnl']['packageType']);
        self::assertTrue($carrierSettings['postnl']['allowStandardDelivery']);
    }

    public function testScopesServicesToContractsForTheConfiguredPackageType(): void
    {
        $builder = new CarrierSettingsBuilder();
        $carrierSettings = $builder->build(
            [
                'contract_definitions' => [
                    [
                        'carrier' => RefCapabilitiesSharedCarrierV2::POSTNL,
                        'packageTypes' => [RefShipmentPackageTypeV2::PACKAGE],
                        'deliveryTypes' => [RefTypesDeliveryTypeV2::STANDARD],
                        'options' => ['requiresSignature' => []],
                    ],
                    [
                        'carrier' => RefCapabilitiesSharedCarrierV2::POSTNL,
                        'packageTypes' => [RefShipmentPackageTypeV2::MAILBOX],
                        'deliveryTypes' => [RefTypesDeliveryTypeV2::STANDARD],
                        'options' => [],
                    ],
                ],
            ],
            [
                'postnl' => ['enabled' => true, 'services' => ['standard', 'signature']],
            ],
            'mailbox'
        );

        self::assertTrue($carrierSettings['postnl']['allowStandardDelivery']);
        self::assertFalse($carrierSettings['postnl']['allowSignature']);
    }

    public function testNormalisesSdkAndWidgetPackageTypeNames(): void
    {
        $builder = new CarrierSettingsBuilder();

        self::assertSame('mailbox', $builder->widgetPackageType(RefShipmentPackageTypeV2::MAILBOX));
        self::assertSame('letter', $builder->widgetPackageType(RefShipmentPackageTypeV2::UNFRANKED));
        self::assertSame('letter', $builder->widgetPackageType('letter'));
        self::assertSame('pallet', $builder->widgetPackageType(RefShipmentPackageTypeV2::PALLET));
        self::assertSame('package_small', $builder->widgetPackageType(RefShipmentPackageTypeV2::SMALL_PACKAGE));
        self::assertSame('package_small', $builder->widgetPackageType('package_small'));
        self::assertSame('envelope', $builder->widgetPackageType(RefShipmentPackageTypeV2::ENVELOPE));
    }

    public function testFallsBackForUnknownPackageTypes(): void
    {
        self::assertSame(
            CarrierSettingsBuilder::DEFAULT_PACKAGE_TYPE,
            (new CarrierSettingsBuilder())->widgetPackageType('does_not_exist')
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function capabilities(): array
    {
        return [
            'contract_definitions' => [
                [
                    'carrier' => RefCapabilitiesSharedCarrierV2::POSTNL,
                    'packageTypes' => [
                        RefShipmentPackageTypeV2::PACKAGE,
                        RefShipmentPackageTypeV2::MAILBOX,
                    ],
                    'deliveryTypes' => [RefTypesDeliveryTypeV2::STANDARD],
                    'options' => [],
                ],
                [
                    'carrier' => RefCapabilitiesSharedCarrierV2::UPS_STANDARD,
                    'packageTypes' => [RefShipmentPackageTypeV2::PACKAGE],
                    'deliveryTypes' => [RefTypesDeliveryTypeV2::STANDARD],
                    'options' => [],
                ],
            ],
        ];
    }
}
