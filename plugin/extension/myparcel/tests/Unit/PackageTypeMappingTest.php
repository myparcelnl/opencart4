<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Tests\Unit;

use MyParcelNL\OpenCart\Core\Service\DeliveryOptions\PackageTypeMapping;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\RefShipmentPackageTypeV2;
use PHPUnit\Framework\TestCase;

class PackageTypeMappingTest extends TestCase
{
    public function testMapsLegacyWidgetNamesToSdkValues(): void
    {
        self::assertSame(RefShipmentPackageTypeV2::UNFRANKED, PackageTypeMapping::toSdk('letter'));
        self::assertSame(RefShipmentPackageTypeV2::SMALL_PACKAGE, PackageTypeMapping::toSdk('package_small'));
    }

    public function testMapsSdkValuesBackToWidgetNames(): void
    {
        self::assertSame('letter', PackageTypeMapping::toWidget(RefShipmentPackageTypeV2::UNFRANKED));
        self::assertSame('package_small', PackageTypeMapping::toWidget(RefShipmentPackageTypeV2::SMALL_PACKAGE));
        self::assertSame('pallet', PackageTypeMapping::toWidget(RefShipmentPackageTypeV2::PALLET));
    }

    public function testRejectsUnknownWidgetType(): void
    {
        self::assertNull(PackageTypeMapping::toWidget('does_not_exist'));
        self::assertNull(PackageTypeMapping::toSdk('does_not_exist'));
    }

    public function testExportSlugsUseWidgetAliasesWithoutSdkDuplicates(): void
    {
        $slugs = PackageTypeMapping::exportSlugs();

        self::assertContains('letter', $slugs);
        self::assertContains('package_small', $slugs);
        self::assertNotContains('unfranked', $slugs);
        self::assertNotContains('small_package', $slugs);
    }
}
