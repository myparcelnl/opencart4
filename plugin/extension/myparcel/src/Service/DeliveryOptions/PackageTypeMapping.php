<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Service\DeliveryOptions;

use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\RefShipmentPackageTypeV2;

/**
 * Single package-type boundary between Delivery Options widget slugs and the
 * generated Core API enum values.
 */
final class PackageTypeMapping
{
    public const DEFAULT_WIDGET_TYPE = 'package';

    /**
     * Widget slug => generated SDK value. Explicit names cover the two values
     * whose widget spelling does not match the Core API enum spelling.
     *
     * @var array<string, string>
     */
    private const WIDGET_TO_SDK = [
        'package' => RefShipmentPackageTypeV2::PACKAGE,
        'mailbox' => RefShipmentPackageTypeV2::MAILBOX,
        'letter' => RefShipmentPackageTypeV2::UNFRANKED,
        'digital_stamp' => RefShipmentPackageTypeV2::DIGITAL_STAMP,
        'pallet' => RefShipmentPackageTypeV2::PALLET,
        'package_small' => RefShipmentPackageTypeV2::SMALL_PACKAGE,
        'envelope' => RefShipmentPackageTypeV2::ENVELOPE,
    ];

    /** Resolve a widget slug or SDK value to its generated Core API value. */
    public static function toSdk(string $packageType): ?string
    {
        $normalised = strtolower(trim($packageType));
        $mapped = self::WIDGET_TO_SDK[$normalised] ?? null;

        if ($mapped !== null) {
            return $mapped;
        }

        foreach (RefShipmentPackageTypeV2::getAllowableEnumValues() as $value) {
            if ($normalised === strtolower($value)) {
                return $value;
            }
        }

        return null;
    }

    /** Resolve a widget slug or SDK value to a package type accepted by the widget. */
    public static function toWidget(string $packageType): ?string
    {
        $normalised = strtolower(trim($packageType));

        if (isset(self::WIDGET_TO_SDK[$normalised])) {
            return $normalised;
        }

        foreach (self::WIDGET_TO_SDK as $slug => $sdkValue) {
            if ($normalised === strtolower($sdkValue)) {
                return $slug;
            }
        }

        return null;
    }

    /**
     * Package-type slugs accepted for export. Newly generated enum values are
     * included by convention until an explicit widget alias is required.
     *
     * @return list<string>
     */
    public static function exportSlugs(): array
    {
        $slugs = array_keys(self::WIDGET_TO_SDK);

        foreach (RefShipmentPackageTypeV2::getAllowableEnumValues() as $value) {
            if (!in_array($value, self::WIDGET_TO_SDK, true)) {
                $slugs[] = strtolower($value);
            }
        }

        return array_values(array_unique($slugs));
    }
}
