<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Tests\Unit;

use MyParcelNL\OpenCart\Core\Service\DeliveryOptions\CarrierSettingsBuilder;
use MyParcelNL\OpenCart\Core\Settings\SettingKeys;
use MyParcelNL\OpenCart\Core\Settings\Settings;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\RefShipmentLabelPrintingPosition;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\ShipmentDefsCustomsShipmentType;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\ShipmentParametersPaperSize;
use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase
{
    public function testReadsValidValues(): void
    {
        $settings = Settings::fromOcSettings([
            SettingKeys::STATUS => '1',
            SettingKeys::API_KEY => '  key123  ',
            SettingKeys::ENVIRONMENT => 'acceptance',
            SettingKeys::DEFAULT_PACKAGE_TYPE => 'mailbox',
            SettingKeys::LABEL_FORMAT => 'A4',
            SettingKeys::LABEL_POSITION => '3',
            SettingKeys::DEFAULT_LENGTH => '30',
            SettingKeys::DEFAULT_WIDTH => '20',
            SettingKeys::DEFAULT_HEIGHT => '10',
            SettingKeys::DEFAULT_WEIGHT => '1000',
            SettingKeys::CUSTOMS_PRODUCT_FIELDS => '1',
            SettingKeys::CUSTOMS_DEFAULT_COUNTRY => 'nl',
            SettingKeys::CUSTOMS_DEFAULT_HS_CODE => '  6109  ',
            SettingKeys::CUSTOMS_CONTENTS_TYPE => (string) ShipmentDefsCustomsShipmentType::GIFT,
        ]);

        self::assertTrue($settings->enabled);
        self::assertSame('key123', $settings->apiKey);
        self::assertSame('acceptance', $settings->environment);
        self::assertSame('mailbox', $settings->defaultPackageType);
        self::assertSame('A4', $settings->labelFormat);
        self::assertSame(3, $settings->labelPosition);
        self::assertTrue($settings->hasFallbackDimensions());
        self::assertSame(1000, $settings->fallbackWeight);
        self::assertTrue($settings->productFieldsEnabled);
        self::assertSame('NL', $settings->defaultCountryOfOrigin);
        self::assertSame('6109', $settings->defaultCustomsCode);
        self::assertSame(ShipmentDefsCustomsShipmentType::GIFT, $settings->customsContentsType);
    }

    public function testAppliesDefaultsForEmptyInput(): void
    {
        $settings = Settings::fromOcSettings([]);

        self::assertFalse($settings->enabled);
        self::assertSame('', $settings->apiKey);
        self::assertSame('production', $settings->environment);
        self::assertSame(CarrierSettingsBuilder::DEFAULT_PACKAGE_TYPE, $settings->defaultPackageType);
        self::assertSame(Settings::DEFAULT_LABEL_FORMAT, $settings->labelFormat);
        self::assertSame(Settings::DEFAULT_LABEL_POSITION, $settings->labelPosition);
        self::assertFalse($settings->hasFallbackDimensions());
        self::assertSame(0, $settings->fallbackWeight);
        self::assertFalse($settings->productFieldsEnabled);
        self::assertSame('', $settings->defaultCountryOfOrigin);
        self::assertSame(Settings::DEFAULT_CUSTOMS_CODE, $settings->defaultCustomsCode);
        self::assertSame(Settings::DEFAULT_CUSTOMS_CONTENTS_TYPE, $settings->customsContentsType);
    }

    public function testNormalisesLegacyPackageTypesWithoutDroppingFutureSdkValues(): void
    {
        $unfranked = Settings::fromOcSettings([SettingKeys::DEFAULT_PACKAGE_TYPE => 'UNFRANKED']);
        $smallPackage = Settings::fromOcSettings([SettingKeys::DEFAULT_PACKAGE_TYPE => 'SMALL_PACKAGE']);
        $future = Settings::fromOcSettings([SettingKeys::DEFAULT_PACKAGE_TYPE => 'FUTURE_TYPE']);

        self::assertSame('letter', $unfranked->defaultPackageType);
        self::assertSame('package_small', $smallPackage->defaultPackageType);
        self::assertSame('future_type', $future->defaultPackageType);
    }

    public function testRejectsInvalidLabelFormatAndPosition(): void
    {
        $settings = Settings::fromOcSettings([
            SettingKeys::LABEL_FORMAT => 'a5',
            SettingKeys::LABEL_POSITION => '9',
        ]);

        self::assertSame(Settings::DEFAULT_LABEL_FORMAT, $settings->labelFormat);
        self::assertSame(Settings::DEFAULT_LABEL_POSITION, $settings->labelPosition);
    }

    public function testLabelChoicesComeFromGeneratedApiModels(): void
    {
        self::assertSame(ShipmentParametersPaperSize::getAllowableEnumValues(), Settings::labelFormats());
        self::assertSame(RefShipmentLabelPrintingPosition::getAllowableEnumValues(), Settings::labelPositions());
    }

    public function testCustomsContentsChoicesComeFromGeneratedApiModel(): void
    {
        self::assertSame(
            ShipmentDefsCustomsShipmentType::getAllowableEnumValues(),
            Settings::customsContentsTypes()
        );
    }

    public function testUnknownCustomsContentsTypeFallsBackToCommercialGoods(): void
    {
        $settings = Settings::fromOcSettings([SettingKeys::CUSTOMS_CONTENTS_TYPE => '999']);

        self::assertSame(Settings::DEFAULT_CUSTOMS_CONTENTS_TYPE, $settings->customsContentsType);
    }

    public function testCustomsContentsSettingIsRenderedPersistedAndUsedForExport(): void
    {
        $root = dirname(__DIR__, 2);
        $moduleController = (string) file_get_contents($root . '/admin/controller/module/myparcel.php');
        $shipmentController = (string) file_get_contents($root . '/admin/controller/shipment/myparcel.php');
        $template = (string) file_get_contents($root . '/admin/view/template/module/myparcel.twig');

        self::assertStringContainsString('SettingKeys::CUSTOMS_CONTENTS_TYPE =>', $moduleController);
        self::assertStringContainsString('contentsType: $settings->customsContentsType', $shipmentController);
        self::assertStringContainsString('name="module_myparcel_customs_contents_type"', $template);
    }

    public function testUnknownEnvironmentFallsBackToProduction(): void
    {
        $settings = Settings::fromOcSettings([SettingKeys::ENVIRONMENT => 'staging']);

        self::assertSame('production', $settings->environment);
    }

    public function testPartialFallbackDimensionsAreNotUsable(): void
    {
        $settings = Settings::fromOcSettings([
            SettingKeys::DEFAULT_LENGTH => '30',
            SettingKeys::DEFAULT_WIDTH => '0',
            SettingKeys::DEFAULT_HEIGHT => '10',
        ]);

        self::assertFalse($settings->hasFallbackDimensions());
    }

    public function testNegativeFallbackWeightIsClampedToZero(): void
    {
        $settings = Settings::fromOcSettings([SettingKeys::DEFAULT_WEIGHT => '-100']);

        self::assertSame(0, $settings->fallbackWeight);
    }
}
