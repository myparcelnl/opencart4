<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Settings;

/**
 * Typed view of the delivery-options widget configuration shown in the checkout.
 * Defaults mirror the widget's previous hardcoded values, so behaviour is unchanged
 * until a merchant tweaks them.
 */
final class CheckoutSettings
{
    public const VIEW_LIST = 'list';

    public const VIEW_MAP = 'map';

    public bool $deliveryOptionsEnabled;

    public bool $showDeliveryDate;

    public int $deliveryDaysWindow;

    public int $dropOffDelay;

    public string $pickupLocationsDefaultView;

    public bool $allowPickupLocationsViewSelection;

    public bool $excludeParcelLockers;

    public bool $compactView;

    public bool $popUpMap;

    /** Require callers to use the factory that applies widget defaults. */
    private function __construct()
    {
    }

    /**
     * Build validated checkout settings from their stored representation.
     *
     * @param array<string, mixed> $raw
     */
    public static function fromArray(array $raw): self
    {
        $settings = new self();

        $settings->deliveryOptionsEnabled = (bool) ($raw['delivery_options_enabled'] ?? true);
        $settings->showDeliveryDate = (bool) ($raw['show_delivery_date'] ?? false);
        $settings->deliveryDaysWindow = max(0, (int) ($raw['delivery_days_window'] ?? 0));
        $settings->dropOffDelay = max(0, (int) ($raw['drop_off_delay'] ?? 0));

        $view = (string) ($raw['pickup_locations_default_view'] ?? self::VIEW_LIST);
        $settings->pickupLocationsDefaultView = $view === self::VIEW_MAP ? self::VIEW_MAP : self::VIEW_LIST;

        $settings->allowPickupLocationsViewSelection = (bool) ($raw['allow_pickup_locations_view_selection'] ?? true);
        $settings->excludeParcelLockers = (bool) ($raw['exclude_parcel_lockers'] ?? false);
        $settings->compactView = (bool) ($raw['compact_view'] ?? false);
        $settings->popUpMap = (bool) ($raw['pop_up_map'] ?? false);

        return $settings;
    }

    /**
     * Normalised storage shape (snake_case keys matching fromArray()).
     *
     * @return array<string, bool|int|string>
     */
    public function toArray(): array
    {
        return [
            'delivery_options_enabled' => $this->deliveryOptionsEnabled,
            'show_delivery_date' => $this->showDeliveryDate,
            'delivery_days_window' => $this->deliveryDaysWindow,
            'drop_off_delay' => $this->dropOffDelay,
            'pickup_locations_default_view' => $this->pickupLocationsDefaultView,
            'allow_pickup_locations_view_selection' => $this->allowPickupLocationsViewSelection,
            'exclude_parcel_lockers' => $this->excludeParcelLockers,
            'compact_view' => $this->compactView,
            'pop_up_map' => $this->popUpMap,
        ];
    }

    /**
     * The flags the delivery-options widget expects, keyed by its own config names.
     * Excludes deliveryOptionsEnabled (that gates whether the widget renders at all).
     *
     * @return array<string, bool|int|string>
     */
    public function toWidgetConfig(): array
    {
        return [
            'showDeliveryDate' => $this->showDeliveryDate,
            'deliveryDaysWindow' => $this->deliveryDaysWindow,
            'dropOffDelay' => $this->dropOffDelay,
            'pickupLocationsDefaultView' => $this->pickupLocationsDefaultView,
            'allowPickupLocationsViewSelection' => $this->allowPickupLocationsViewSelection,
            'excludeParcelLockers' => $this->excludeParcelLockers,
            'compactView' => $this->compactView,
            'popUpMap' => $this->popUpMap,
        ];
    }
}
