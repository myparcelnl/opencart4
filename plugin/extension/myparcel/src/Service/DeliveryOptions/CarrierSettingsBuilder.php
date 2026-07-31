<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Service\DeliveryOptions;

use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\RefCapabilitiesContractDefinitionsResponseOptionsOptionsV2;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\RefCapabilitiesSharedCarrierV2;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\RefTypesDeliveryTypeV2;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\ShipmentParametersCarrierName;

/**
 * Turns the cached capabilities and the admin's carrier toggles into the
 * carrierSettings config that the Delivery Options widget expects.
 */
final class CarrierSettingsBuilder
{
    /** Delivery Options widget default package type; export defaults are configured in module settings. */
    public const DEFAULT_PACKAGE_TYPE = 'package';

    /**
     * Capabilities carrier value => Delivery Options widget slug. Derived from the SDK
     * (both enums join on their shared constant names) so a carrier added to the spec
     * flows through on the next SDK update without touching this class. Memoised per process.
     *
     * @var array<string, string>|null
     */
    private static ?array $carrierSlugs = null;

    /**
     * Service definitions. Delivery-type source keys are SDK enums; option
     * source keys are SDK local names resolved through attributeMap().
     */
    private const SERVICES = [
        'standard' => [
            'source' => 'deliveryTypes',
            'sourceKey' => RefTypesDeliveryTypeV2::STANDARD,
            'allowKey' => 'allowStandardDelivery',
            'priceKey' => 'priceStandardDelivery',
            'defaultEnabled' => true,
            'deliveryOption' => true,
        ],
        'pickup' => [
            'source' => 'deliveryTypes',
            'sourceKey' => RefTypesDeliveryTypeV2::PICKUP,
            'allowKey' => 'allowPickupLocations',
            'priceKey' => 'pricePickup',
            'defaultEnabled' => true,
            'deliveryOption' => false,
        ],
        'morning' => [
            'source' => 'deliveryTypes',
            'sourceKey' => RefTypesDeliveryTypeV2::MORNING,
            'allowKey' => 'allowMorningDelivery',
            'priceKey' => 'priceMorningDelivery',
            'defaultEnabled' => false,
            'deliveryOption' => true,
        ],
        'evening' => [
            'source' => 'deliveryTypes',
            'sourceKey' => RefTypesDeliveryTypeV2::EVENING,
            'allowKey' => 'allowEveningDelivery',
            'priceKey' => 'priceEveningDelivery',
            'defaultEnabled' => false,
            'deliveryOption' => true,
        ],
        'express' => [
            'source' => 'deliveryTypes',
            'sourceKey' => RefTypesDeliveryTypeV2::EXPRESS,
            'allowKey' => 'allowExpressDelivery',
            'priceKey' => 'priceExpressDelivery',
            'defaultEnabled' => false,
            'deliveryOption' => true,
        ],
        'same_day' => [
            'source' => 'deliveryTypes',
            'sourceKey' => RefTypesDeliveryTypeV2::SAME_DAY,
            'allowKey' => 'allowSameDayDelivery',
            'priceKey' => 'priceSameDayDelivery',
            'defaultEnabled' => false,
            'deliveryOption' => true,
        ],
        'signature' => [
            'source' => 'options',
            'sourceKey' => 'requires_signature',
            'allowKey' => 'allowSignature',
            'priceKey' => 'priceSignature',
            'defaultEnabled' => false,
            'deliveryOption' => false,
        ],
        'only_recipient' => [
            'source' => 'options',
            'sourceKey' => 'recipient_only_delivery',
            'allowKey' => 'allowOnlyRecipient',
            'priceKey' => 'priceOnlyRecipient',
            'defaultEnabled' => false,
            'deliveryOption' => false,
        ],
    ];

    private const PRICE_KEYS = [
        'priceStandardDelivery',
        'pricePickup',
        'priceMorningDelivery',
        'priceEveningDelivery',
        'priceExpressDelivery',
        'priceSameDayDelivery',
        'priceSignature',
        'priceOnlyRecipient',
        'priceMondayDelivery',
        'pricePackageTypeMailbox',
        'pricePackageTypeDigitalStamp',
        'pricePackageTypePackageSmall',
        'pricePackageTypeEnvelope',
    ];

    /**
     * Extract supported carriers and services from the imported contract definitions.
     *
     * @param array<string, mixed> $capabilitiesBlob
     *
     * @return array<string, array{carrier: string, slug: string, services: array<string, bool>}>
     */
    public function supportedCarriers(array $capabilitiesBlob): array
    {
        $carriers = [];

        foreach ($this->contracts($capabilitiesBlob) as $contract) {
            $carrier = (string) ($contract['carrier'] ?? '');
            $slug = $this->carrierSlugs()[$carrier] ?? null;

            if ($slug === null) {
                continue;
            }

            if (!isset($carriers[$slug])) {
                $carriers[$slug] = [
                    'carrier' => $carrier,
                    'slug' => $slug,
                    'services' => array_fill_keys(array_keys(self::SERVICES), false),
                ];
            }

            foreach (self::SERVICES as $service => $definition) {
                if ($this->contractSupports($contract, $definition)) {
                    $carriers[$slug]['services'][$service] = true;
                }
            }
        }

        return array_filter($carriers, static function (array $carrier): bool {
            return in_array(true, $carrier['services'], true);
        });
    }

    /**
     * Build the initial admin selection for each supported carrier.
     *
     * @param array<string, mixed> $capabilitiesBlob
     *
     * @return array<string, array{enabled: bool, services: list<string>}>
     */
    public function defaultAdminSettings(array $capabilitiesBlob): array
    {
        $settings = [];

        foreach ($this->supportedCarriers($capabilitiesBlob) as $slug => $carrier) {
            $services = [];

            foreach ($carrier['services'] as $service => $supported) {
                if ($supported && self::SERVICES[$service]['defaultEnabled']) {
                    $services[] = $service;
                }
            }

            $settings[$slug] = [
                'enabled' => $services !== [],
                'services' => $services,
            ];
        }

        return $settings;
    }

    /**
     * Merge saved admin choices with the carriers the account currently supports.
     *
     * @param array<string, mixed> $capabilitiesBlob
     * @param array<array-key, mixed> $currentSettings
     *
     * @return array<string, array{enabled: bool, services: list<string>}>
     */
    public function mergeAdminSettings(array $capabilitiesBlob, array $currentSettings): array
    {
        $supported = $this->supportedCarriers($capabilitiesBlob);
        $defaults = $this->defaultAdminSettings($capabilitiesBlob);
        $current = $this->normaliseAdminSettings($currentSettings);
        $merged = [];

        foreach ($supported as $slug => $carrier) {
            $row = $current[$slug] ?? $defaults[$slug] ?? ['enabled' => false, 'services' => []];
            $allowedServices = array_keys(array_filter($carrier['services']));

            $merged[$slug] = [
                'enabled' => (bool) ($row['enabled'] ?? false),
                'services' => array_values(array_intersect($row['services'] ?? [], $allowedServices)),
            ];
        }

        return $merged;
    }

    /**
     * Build the carrierSettings payload consumed by the Delivery Options widget.
     *
     * @param array<string, mixed> $capabilitiesBlob
     * @param array<array-key, mixed> $adminSettings
     *
     * @return array<string, array<string, bool|int|string>>
     */
    public function build(array $capabilitiesBlob, array $adminSettings): array
    {
        $supported = $this->supportedCarriers($capabilitiesBlob);
        $settings = $this->mergeAdminSettings($capabilitiesBlob, $adminSettings);
        $carrierSettings = [];

        foreach ($supported as $slug => $carrier) {
            $admin = $settings[$slug] ?? null;

            if (!$admin || empty($admin['enabled'])) {
                continue;
            }

            $enabledServices = array_flip($admin['services'] ?? []);
            $row = [
                'allowDeliveryOptions' => false,
                'packageType' => self::DEFAULT_PACKAGE_TYPE,
            ];

            // Every allow key explicit: the widget falls back to its global defaults
            // (mostly true) for keys that are absent, which would re-enable services
            // the admin disabled.
            foreach (self::SERVICES as $definition) {
                $row[$definition['allowKey']] = false;
            }

            foreach (self::PRICE_KEYS as $priceKey) {
                $row[$priceKey] = 0;
            }

            $hasEnabledFeature = false;

            foreach ($carrier['services'] as $service => $isSupported) {
                if (!$isSupported || !isset($enabledServices[$service])) {
                    continue;
                }

                $definition = self::SERVICES[$service];
                $row[$definition['allowKey']] = true;
                $hasEnabledFeature = true;

                if ($definition['deliveryOption']) {
                    $row['allowDeliveryOptions'] = true;
                }
            }

            if ($hasEnabledFeature) {
                $carrierSettings[$slug] = $row;
            }
        }

        return $carrierSettings;
    }

    /**
     * Return the service keys administrators can enable per carrier.
     *
     * @return list<string>
     */
    public function serviceKeys(): array
    {
        return array_keys(self::SERVICES);
    }

    /**
     * Shipment-option keys shared by the widget payload and SDK model. Deriving
     * these from the central service definitions keeps export support aligned
     * with the options exposed at checkout.
     *
     * @return list<string>
     */
    public function shipmentOptionKeys(): array
    {
        $keys = [];

        foreach (self::SERVICES as $key => $definition) {
            if ($definition['source'] === 'options') {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * Global allow-flags for the widget: a service is on when at least one carrier
     * enables it. The widget hides a whole section when its global flag is off.
     *
     * @param array<string, array<string, bool|int|string>> $carrierSettings
     *
     * @return array<string, bool>
     */
    public function globalAllowFlags(array $carrierSettings): array
    {
        // The widget defaults Monday delivery on, but it is not a capability we get back.
        $flags = ['allowMondayDelivery' => false];

        foreach (self::SERVICES as $definition) {
            $flags[$definition['allowKey']] = false;
        }

        foreach ($carrierSettings as $row) {
            foreach ($flags as $key => $enabled) {
                if (!$enabled && !empty($row[$key])) {
                    $flags[$key] = true;
                }
            }
        }

        return $flags;
    }

    /**
     * Normalise untrusted stored or posted carrier selections.
     *
     * @param array<array-key, mixed> $settings
     *
     * @return array<string, array{enabled: bool, services: list<string>}>
     */
    public function normaliseAdminSettings(array $settings): array
    {
        $normalised = [];

        foreach ($settings as $slug => $row) {
            if (!is_string($slug) || !is_array($row)) {
                continue;
            }

            $services = $row['services'] ?? [];
            if (!is_array($services)) {
                $services = [];
            }

            $normalised[$slug] = [
                'enabled' => !empty($row['enabled']),
                'services' => array_values(array_intersect(
                    array_map('strval', $this->normaliseServices($services)),
                    array_keys(self::SERVICES)
                )),
            ];
        }

        return $normalised;
    }

    /**
     * Inverse of carrierSlugs(): widget slug => SDK carrier value ('postnl' => 'POSTNL').
     * Lets the export resolve the shopper's chosen carrier without a hand-kept slug list.
     * Lossless flip — every widget slug is unique.
     *
     * @return array<string, string>
     */
    public function carrierValuesBySlug(): array
    {
        return array_flip($this->carrierSlugs());
    }

    /**
     * Build the capabilities-carrier => widget-slug map by joining the two SDK enums on
     * their shared constant names (BPOST, UPS_EXPRESS_SAVER, …). The capabilities enum
     * supplies the key, the shipment-parameters enum the widget slug. Cached.
     *
     * @return array<string, string>
     */
    private function carrierSlugs(): array
    {
        if (self::$carrierSlugs === null) {
            $capabilities = (new \ReflectionClass(RefCapabilitiesSharedCarrierV2::class))
                ->getConstants(\ReflectionClassConstant::IS_PUBLIC);
            $slugs = (new \ReflectionClass(ShipmentParametersCarrierName::class))
                ->getConstants(\ReflectionClassConstant::IS_PUBLIC);

            $map = [];

            foreach ($capabilities as $name => $capabilityValue) {
                $slug = $slugs[$name] ?? null;

                if (is_string($capabilityValue) && is_string($slug)) {
                    $map[$capabilityValue] = $slug;
                }
            }

            self::$carrierSlugs = $map;
        }

        return self::$carrierSlugs;
    }

    /**
     * Read the contract-definition rows from either supported cache key shape.
     *
     * @param array<string, mixed> $capabilitiesBlob
     *
     * @return list<array<string, mixed>>
     */
    private function contracts(array $capabilitiesBlob): array
    {
        $normalised = $this->normaliseArray($capabilitiesBlob);
        $contracts = $normalised['contract_definitions'] ?? $normalised['contractDefinitions'] ?? [];

        return is_array($contracts) ? array_values(array_filter($contracts, 'is_array')) : [];
    }

    /**
     * Check whether one contract advertises a configured widget service.
     *
     * @param array<string, mixed> $contract
     * @param array{
     *     source: string,
     *     sourceKey: string,
     *     allowKey: string,
     *     priceKey: string,
     *     defaultEnabled: bool,
     *     deliveryOption: bool
     * } $definition
     */
    private function contractSupports(array $contract, array $definition): bool
    {
        if ($definition['source'] === 'deliveryTypes') {
            $deliveryTypes = $contract['deliveryTypes'] ?? $contract['delivery_types'] ?? [];

            return is_array($deliveryTypes) && in_array($definition['sourceKey'], $deliveryTypes, true);
        }

        $options = $contract['options'] ?? [];
        $sourceKey = (string) $definition['sourceKey'];
        $attributeKey = $this->optionAttributeKey($sourceKey);

        return is_array($options) && (
            (array_key_exists($attributeKey, $options) && $options[$attributeKey] !== null)
            || (array_key_exists($sourceKey, $options) && $options[$sourceKey] !== null)
        );
    }

    /**
     * Resolve an SDK local option name to its serialized cache key.
     */
    private function optionAttributeKey(string $sourceKey): string
    {
        $attributeMap = RefCapabilitiesContractDefinitionsResponseOptionsOptionsV2::attributeMap();

        return is_string($attributeMap[$sourceKey] ?? null) ? $attributeMap[$sourceKey] : $sourceKey;
    }

    /**
     * Convert list- or checkbox-shaped service input to its enabled values.
     *
     * @param array<array-key, mixed> $services
     *
     * @return list<array-key>
     */
    private function normaliseServices(array $services): array
    {
        if (self::isList($services)) {
            return array_map('strval', $services);
        }

        return array_keys(array_filter($services));
    }

    /**
     * Convert nested SDK model values to plain arrays through JSON normalisation.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function normaliseArray(array $data): array
    {
        $json = json_encode($data);
        if (!is_string($json)) {
            return $data;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : $data;
    }

    /**
     * Check whether an array uses contiguous zero-based integer keys.
     *
     * @param array<array-key, mixed> $array
     */
    private static function isList(array $array): bool
    {
        return $array === [] || array_keys($array) === range(0, count($array) - 1);
    }
}
