<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Settings;

use MyParcelNL\OpenCart\Core\Enum\Environment;
use MyParcelNL\OpenCart\Core\Service\DeliveryOptions\CarrierSettingsBuilder;
use MyParcelNL\OpenCart\Core\Service\DeliveryOptions\PackageTypeMapping;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\RefShipmentLabelPrintingPosition;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\ShipmentDefsCustomsShipmentType;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\ShipmentParametersPaperSize;

/**
 * Typed read-model over the module's OpenCart settings. Built from the raw setting
 * array so it stays free of OpenCart's Config/Registry and is unit-testable.
 */
final class Settings
{
    /** A6 (one label per page) is the common single-shipment default. */
    public const DEFAULT_LABEL_FORMAT = ShipmentParametersPaperSize::A6;

    /** Top-left is the first label position on an A4 sheet. */
    public const DEFAULT_LABEL_POSITION = RefShipmentLabelPrintingPosition::TOP_LEFT_QUADRANT;

    /** Generic customs classification used by the other MyParcel plugins. */
    public const DEFAULT_CUSTOMS_CODE = '000000';

    /** Commercial goods is the PDK-compatible default for customs declarations. */
    public const DEFAULT_CUSTOMS_CONTENTS_TYPE = ShipmentDefsCustomsShipmentType::COMMERCIAL_GOODS;

    public bool $enabled;

    public string $apiKey;

    public string $environment;

    public string $defaultPackageType;

    public string $labelFormat;

    public int $labelPosition;

    public int $fallbackLength;

    public int $fallbackWidth;

    public int $fallbackHeight;

    /** Grams; 0 means no configured fallback. */
    public int $fallbackWeight;

    public bool $productFieldsEnabled;

    public string $defaultCountryOfOrigin;

    public string $defaultCustomsCode;

    public int $customsContentsType;

    public CheckoutSettings $checkout;

    /** Require callers to use a factory that validates the stored values. */
    private function __construct()
    {
    }

    /**
     * Build validated module settings from OpenCart's stored setting values.
     *
     * @param array<string, mixed> $raw The module setting group as OpenCart stores it.
     */
    public static function fromOcSettings(array $raw): self
    {
        $settings = new self();

        $settings->enabled = (bool) ($raw[SettingKeys::STATUS] ?? false);
        $settings->apiKey = trim((string) ($raw[SettingKeys::API_KEY] ?? ''));
        $settings->environment = Environment::normalize($raw[SettingKeys::ENVIRONMENT] ?? null);

        $packageType = trim((string) ($raw[SettingKeys::DEFAULT_PACKAGE_TYPE] ?? ''));
        $settings->defaultPackageType = $packageType !== ''
            ? (PackageTypeMapping::toWidget($packageType) ?? strtolower($packageType))
            : CarrierSettingsBuilder::DEFAULT_PACKAGE_TYPE;

        $format = strtoupper(trim((string) ($raw[SettingKeys::LABEL_FORMAT] ?? '')));
        $settings->labelFormat = in_array($format, self::labelFormats(), true)
            ? $format
            : self::DEFAULT_LABEL_FORMAT;

        $position = (int) ($raw[SettingKeys::LABEL_POSITION] ?? self::DEFAULT_LABEL_POSITION);
        $settings->labelPosition = in_array($position, self::labelPositions(), true)
            ? $position
            : self::DEFAULT_LABEL_POSITION;

        $settings->fallbackLength = max(0, (int) ($raw[SettingKeys::DEFAULT_LENGTH] ?? 0));
        $settings->fallbackWidth = max(0, (int) ($raw[SettingKeys::DEFAULT_WIDTH] ?? 0));
        $settings->fallbackHeight = max(0, (int) ($raw[SettingKeys::DEFAULT_HEIGHT] ?? 0));
        $settings->fallbackWeight = max(0, (int) ($raw[SettingKeys::DEFAULT_WEIGHT] ?? 0));

        $settings->productFieldsEnabled = (bool) ($raw[SettingKeys::CUSTOMS_PRODUCT_FIELDS] ?? false);
        $settings->defaultCountryOfOrigin = strtoupper(trim((string) ($raw[SettingKeys::CUSTOMS_DEFAULT_COUNTRY] ?? '')));
        $customsCode = trim((string) ($raw[SettingKeys::CUSTOMS_DEFAULT_HS_CODE] ?? ''));
        $settings->defaultCustomsCode = $customsCode !== ''
            ? $customsCode
            : self::DEFAULT_CUSTOMS_CODE;
        $contentsType = (int) ($raw[SettingKeys::CUSTOMS_CONTENTS_TYPE] ?? self::DEFAULT_CUSTOMS_CONTENTS_TYPE);
        $settings->customsContentsType = in_array($contentsType, self::customsContentsTypes(), true)
            ? $contentsType
            : self::DEFAULT_CUSTOMS_CONTENTS_TYPE;

        $checkout = $raw[SettingKeys::CHECKOUT] ?? null;
        $settings->checkout = CheckoutSettings::fromArray(is_array($checkout) ? $checkout : []);

        return $settings;
    }

    /**
     * Build from an OpenCart config object (anything exposing get(string)).
     *
     * @param object $config OpenCart\System\Engine\Config
     */
    public static function fromConfig(object $config): self
    {
        return self::fromOcSettings([
            SettingKeys::STATUS => $config->get(SettingKeys::STATUS),
            SettingKeys::API_KEY => $config->get(SettingKeys::API_KEY),
            SettingKeys::ENVIRONMENT => $config->get(SettingKeys::ENVIRONMENT),
            SettingKeys::DEFAULT_PACKAGE_TYPE => $config->get(SettingKeys::DEFAULT_PACKAGE_TYPE),
            SettingKeys::LABEL_FORMAT => $config->get(SettingKeys::LABEL_FORMAT),
            SettingKeys::LABEL_POSITION => $config->get(SettingKeys::LABEL_POSITION),
            SettingKeys::DEFAULT_LENGTH => $config->get(SettingKeys::DEFAULT_LENGTH),
            SettingKeys::DEFAULT_WIDTH => $config->get(SettingKeys::DEFAULT_WIDTH),
            SettingKeys::DEFAULT_HEIGHT => $config->get(SettingKeys::DEFAULT_HEIGHT),
            SettingKeys::DEFAULT_WEIGHT => $config->get(SettingKeys::DEFAULT_WEIGHT),
            SettingKeys::CUSTOMS_PRODUCT_FIELDS => $config->get(SettingKeys::CUSTOMS_PRODUCT_FIELDS),
            SettingKeys::CUSTOMS_DEFAULT_COUNTRY => $config->get(SettingKeys::CUSTOMS_DEFAULT_COUNTRY),
            SettingKeys::CUSTOMS_DEFAULT_HS_CODE => $config->get(SettingKeys::CUSTOMS_DEFAULT_HS_CODE),
            SettingKeys::CUSTOMS_CONTENTS_TYPE => $config->get(SettingKeys::CUSTOMS_CONTENTS_TYPE),
            SettingKeys::CHECKOUT => $config->get(SettingKeys::CHECKOUT),
        ]);
    }

    /**
     * The selectable label formats.
     *
     * @return list<string>
     */
    public static function labelFormats(): array
    {
        return ShipmentParametersPaperSize::getAllowableEnumValues();
    }

    /**
     * The label positions accepted by the generated shipment API model.
     *
     * @return list<int>
     */
    public static function labelPositions(): array
    {
        return RefShipmentLabelPrintingPosition::getAllowableEnumValues();
    }

    /**
     * The customs contents categories accepted by the generated shipment model.
     *
     * @return list<int>
     */
    public static function customsContentsTypes(): array
    {
        return array_map('intval', ShipmentDefsCustomsShipmentType::getAllowableEnumValues());
    }

    /** True when all three fallback dimensions are set, i.e. a usable default box. */
    public function hasFallbackDimensions(): bool
    {
        return $this->fallbackLength > 0 && $this->fallbackWidth > 0 && $this->fallbackHeight > 0;
    }
}
