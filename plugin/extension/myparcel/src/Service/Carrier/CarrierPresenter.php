<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Service\Carrier;

use MyParcelNL\OpenCart\Core\Dto\CarrierCatalog;
use MyParcelNL\OpenCart\Core\Service\DeliveryOptions\CarrierSettingsBuilder;

/**
 * Presentation of carriers in the admin from the public carrier catalog.
 *
 * The Delivery Options enum bridge remains the source for SDK-value-to-slug
 * conversion; catalog data supplies only display metadata.
 */
final class CarrierPresenter
{
    private CarrierSettingsBuilder $builder;

    private CarrierCatalog $catalog;

    /** @var array<string, string>|null */
    private ?array $valuesBySlug = null;

    /** Allow callers to supply cached catalog metadata or replace the enum bridge. */
    public function __construct(?CarrierCatalog $catalog = null, ?CarrierSettingsBuilder $builder = null)
    {
        $this->builder = $builder ?? new CarrierSettingsBuilder();
        $this->catalog = $catalog ?? CarrierCatalog::empty();
    }

    /** Resolve the display name for a Delivery Options carrier slug. */
    public function nameForSlug(string $slug): string
    {
        return $this->catalog->nameForSlug($slug) ?? self::humanise($slug);
    }

    /** Display name for an SDK carrier value, with a readable unknown fallback. */
    public function nameForValue(string $value): string
    {
        $this->valuesBySlug ??= $this->builder->carrierValuesBySlug();
        $slug = array_search($value, $this->valuesBySlug, true);

        return is_string($slug) ? $this->nameForSlug($slug) : self::humanise($value);
    }

    /** Return the cached, verified public logo URL for a carrier slug, or an empty string. */
    public function logoUrl(string $slug): string
    {
        return $this->catalog->logoUrlForSlug($slug);
    }

    /** Convert a safe catalog slug or SDK enum value into a usable fallback label. */
    private static function humanise(string $value): string
    {
        $label = trim(str_replace(['_', '.', '-'], ' ', strtolower($value)));

        return $label === '' ? 'Unknown carrier' : ucwords($label);
    }
}
