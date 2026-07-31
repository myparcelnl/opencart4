<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Service\Carrier;

use MyParcelNL\OpenCart\Core\Dto\CarrierCatalog;
use MyParcelNL\OpenCart\Core\Service\DeliveryOptions\CarrierSettingsBuilder;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\RefCapabilitiesSharedCarrierV2;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\RefTypesCarrier;

/** Resolves carrier identifiers by joining catalog and generated SDK definitions. */
final class CarrierResolver
{
    /** Allow tests and alternate clients to supply the generated slug bridge. */
    public function __construct(private ?CarrierSettingsBuilder $settingsBuilder = null)
    {
    }

    /**
     * Convert the private shipping-rule carrier id to its capabilities value.
     *
     * The public catalog is preferred because it is current independently of the
     * SDK. Generated enum constant names provide a safe fallback during a catalog
     * outage without reintroducing a hand-maintained id map.
     */
    public function valueForLegacyId(int $carrierId, CarrierCatalog $catalog): ?string
    {
        if ($carrierId < 1) {
            return null;
        }

        $builder = $this->settingsBuilder ?? new CarrierSettingsBuilder();
        $slug = $catalog->slugForId($carrierId);

        if ($slug !== null) {
            $value = $builder->carrierValuesBySlug()[$slug] ?? null;

            if ($value !== null) {
                return $value;
            }
        }

        $legacyConstants = (new \ReflectionClass(RefTypesCarrier::class))->getConstants();
        $constantName = array_search($carrierId, $legacyConstants, true);

        if (!is_string($constantName)) {
            return null;
        }

        $capabilityConstants = (new \ReflectionClass(RefCapabilitiesSharedCarrierV2::class))->getConstants();
        $value = $capabilityConstants[$constantName] ?? null;

        return is_string($value)
            && in_array($value, RefCapabilitiesSharedCarrierV2::getAllowableEnumValues(), true)
                ? $value
                : null;
    }
}
