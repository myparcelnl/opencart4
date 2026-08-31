<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Dto;

use DateTimeImmutable;
use Exception;

/**
 * The Delivery Options a shopper picked at checkout, as stored on the order.
 * Holds the widget slugs ('standard', 'mailbox', ...); the mapper translates
 * them to SDK enum values when building the shipment. The SDK only models the
 * API request/response shapes, not the checkout widget's payload, so this DTO
 * is the bridge between the two. Shipment options remain a keyed collection so
 * the mapper can apply the centrally supported widget options dynamically.
 */
final class DeliveryOptionsDto
{
    /**
     * Create a normalised delivery-options selection.
     *
     * @param array<string, mixed> $shipmentOptions raw shipmentOptions payload from the widget
     * @param array<string, mixed>|null $pickup pickupLocation payload normalised to SDK snake_case keys
     * @param string|null $deliveryDate delivery date normalised to the shipment API date-time format
     */
    public function __construct(
        public ?string $carrier = null,
        public ?string $deliveryType = null,
        public ?string $packageType = null,
        public array $shipmentOptions = [],
        public ?array $pickup = null,
        public ?string $deliveryDate = null,
    ) {
    }

    /**
     * Build from the stored widget payload. Reads the widget's camelCase keys and
     * the shipment-options snake_case variants, so a payload shape change does not
     * silently drop a value.
     *
     * @param array<string, mixed> $json
     */
    public static function fromJson(array $json): self
    {
        $options = self::subArray($json, 'shipmentOptions', 'shipment_options');
        return new self(
            carrier: self::string($json, 'carrier'),
            deliveryType: self::string($json, 'deliveryType', 'delivery_type'),
            packageType: self::string($json, 'packageType', 'package_type'),
            shipmentOptions: $options,
            pickup: self::pickupLocation($json),
            deliveryDate: self::deliveryDate($json),
        );
    }

    /**
     * Read a boolean widget option by its SDK snake_case key. Camel-case input
     * remains supported for stored payloads produced by older widget versions.
     */
    public function shipmentOption(string $key): ?bool
    {
        $camelCase = lcfirst(str_replace('_', '', ucwords($key, '_')));

        return self::bool($this->shipmentOptions, $key, $camelCase);
    }

    /**
     * Normalise the widget's camelCase pickupLocation payload to the SDK model's
     * snake_case constructor keys.
     *
     * @param array<string, mixed> $json
     * @return array<string, mixed>|null
     */
    private static function pickupLocation(array $json): ?array
    {
        $raw = self::subArray($json, 'pickupLocation', 'pickup_location');

        if ($raw === []) {
            return null;
        }

        $normalised = [
            'cc' => self::string($raw, 'cc'),
            'city' => self::string($raw, 'city'),
            'number' => self::string($raw, 'number'),
            'street' => self::string($raw, 'street'),
            'postal_code' => self::string($raw, 'postal_code', 'postalCode'),
            'location_code' => self::string($raw, 'location_code', 'locationCode'),
            'location_name' => self::string($raw, 'location_name', 'locationName'),
            'number_suffix' => self::string($raw, 'number_suffix', 'numberSuffix'),
            'box_number' => self::string($raw, 'box_number', 'boxNumber'),
            'region' => self::string($raw, 'region'),
            'state' => self::string($raw, 'state'),
            'retail_network_id' => self::string($raw, 'retail_network_id', 'retailNetworkId'),
        ];

        return array_filter($normalised, static fn ($value): bool => $value !== null && $value !== '');
    }

    /**
     * Normalise the widget's date (`Y-m-d`) and legacy ISO 8601 variants to the
     * date-time format required by the shipment API.
     *
     * @param array<string, mixed> $json
     */
    private static function deliveryDate(array $json): ?string
    {
        $raw = self::string($json, 'date', 'deliveryDate', 'delivery_date');

        if ($raw === null || preg_match('/^\d{4}-\d{2}-\d{2}/', $raw) !== 1) {
            return null;
        }

        try {
            $date = new DateTimeImmutable($raw);
        } catch (Exception) {
            return null;
        }

        $parseErrors = DateTimeImmutable::getLastErrors();

        if ($parseErrors !== false && ($parseErrors['warning_count'] > 0 || $parseErrors['error_count'] > 0)) {
            return null;
        }

        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Return the first array found under one of the accepted payload keys.
     *
     * @param array<string, mixed> $source
     *
     * @return array<string, mixed>
     */
    private static function subArray(array $source, string ...$keys): array
    {
        foreach ($keys as $key) {
            if (isset($source[$key]) && is_array($source[$key])) {
                return $source[$key];
            }
        }

        return [];
    }

    /**
     * Return the first non-empty scalar value under one of the accepted keys.
     *
     * @param array<string, mixed> $source
     */
    private static function string(array $source, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($source[$key]) && is_scalar($source[$key]) && (string) $source[$key] !== '') {
                return (string) $source[$key];
            }
        }

        return null;
    }

    /**
     * Return the first present value under one of the accepted keys as a boolean.
     *
     * @param array<string, mixed> $source
     */
    private static function bool(array $source, string ...$keys): ?bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $source) && $source[$key] !== null) {
                return (bool) $source[$key];
            }
        }

        return null;
    }
}
