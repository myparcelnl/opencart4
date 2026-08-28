<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Helper;

use Closure;
use MyParcelNL\OpenCart\Core\Dto\DeliveryOptionsDto;
use MyParcelNL\OpenCart\Core\Dto\OrderDto;
use MyParcelNL\OpenCart\Core\Dto\RecipientDto;
use MyParcelNL\OpenCart\Core\Service\DeliveryOptions\CarrierSettingsBuilder;
use MyParcelNL\OpenCart\Core\Service\DeliveryOptions\PackageTypeMapping;
use MyParcelNL\OpenCart\Core\Service\Shipment\CustomsDeclarationFromOrder;
use MyParcelNL\OpenCart\Core\Service\Shipment\MissingRecipientFieldsException;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\RefShipmentPackageTypeV2;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\RefShipmentPickup as PickupModel;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\RefTypesDeliveryTypeV2;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\ShipmentPostShipmentsRequestV11DataShipmentsInnerPhysicalProperties as PhysicalPropertiesModel;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\ShipmentPostShipmentsRequestV11DataShipmentsInnerRecipient as RecipientModel;
use MyParcelNL\Sdk\Model\Shipment\Shipment;
use MyParcelNL\Sdk\Model\Shipment\ShipmentOptions;

/**
 * Builds a MyParcel SDK Shipment from an OpenCart order, applying the Delivery
 * Options the shopper picked at checkout when they are available.
 */
class OrderToShipmentMapper
{
    /** Technical minimum used when neither products nor settings provide a weight. */
    private const MINIMUM_WEIGHT_IN_GRAMS = 1;

    /**
     * Delivery Options delivery-type slug => generated Core API v2 value. Explicit
     * aliases cover names whose API constant has a different value suffix.
     *
     * @var array<string, string>
     */
    private const DELIVERY_TYPES = [
        'standard' => RefTypesDeliveryTypeV2::STANDARD,
        'morning'  => RefTypesDeliveryTypeV2::MORNING,
        'evening'  => RefTypesDeliveryTypeV2::EVENING,
        'pickup'   => RefTypesDeliveryTypeV2::PICKUP,
        'express'  => RefTypesDeliveryTypeV2::EXPRESS,
        'same_day' => RefTypesDeliveryTypeV2::SAME_DAY,
    ];

    /** @var Closure(string): void|null */
    private ?Closure $fallbackReporter;

    private CustomsDeclarationFromOrder $customsDeclarations;

    /**
     * The package-type slugs the mapper accepts, so the admin UI can offer them as a
     * configurable default without maintaining its own (SDK-derived) list.
     *
     * @return string[]
     */
    public static function packageTypeSlugs(): array
    {
        return PackageTypeMapping::exportSlugs();
    }

    /**
     * Resolve a stored Delivery Options slug to the generated package-type value.
     *
     * @param callable(string): void|null $fallbackReporter Receives a diagnostic when fallback is required.
     */
    public static function packageTypeValue(string $slug, ?callable $fallbackReporter = null): string
    {
        $value = PackageTypeMapping::toSdk($slug);

        if ($value !== null) {
            return $value;
        }

        self::notifyFallback($fallbackReporter, 'package type', RefShipmentPackageTypeV2::PACKAGE);

        return RefShipmentPackageTypeV2::PACKAGE;
    }

    /**
     * Configure the fallbacks used when checkout data is incomplete.
     *
     * A fallback weight of 0 leaves only the technical minimum of 1 gram. Carrier-specific
     * minimums are enforced by the shipment API and may be higher.
     *
     * @param array{length: int, width: int, height: int}|null $dimensions package size in cm
     * @param callable(string): void|null $fallbackReporter Receives non-sensitive fallback diagnostics.
     */
    public function __construct(
        private string $defaultCarrier,
        private string $defaultPackageType = RefShipmentPackageTypeV2::PACKAGE,
        private ?int $shopId = null,
        private ?array $dimensions = null,
        private int $fallbackWeight = 0,
        ?callable $fallbackReporter = null,
        ?CustomsDeclarationFromOrder $customsDeclarations = null
    ) {
        $this->fallbackReporter = $fallbackReporter !== null
            ? Closure::fromCallable($fallbackReporter)
            : null;
        $this->customsDeclarations = $customsDeclarations ?? new CustomsDeclarationFromOrder();
    }

    /** Map one OpenCart order DTO to an SDK shipment. */
    public function mapOrderToShipment(OrderDto $order, ?DeliveryOptionsDto $deliveryOptions = null): Shipment
    {
        $this->assertDeliverable($order->recipient);

        $shipment = new Shipment();
        $shipment->setRecipient($this->recipient($order->recipient));
        $shipment->setCarrier($this->carrier($deliveryOptions));
        $shipment->setOptions($this->options($deliveryOptions));
        $this->applyPickup($shipment, $deliveryOptions);
        $physicalProperties = $this->physicalProperties($order);
        $shipment->setPhysicalProperties($physicalProperties);
        $shipment->setReferenceIdentifier($order->reference);

        $customsDeclaration = $this->customsDeclarations->create(
            $order,
            (int) $physicalProperties->getWeight()
        );

        if ($customsDeclaration !== null) {
            $shipment->setCustomsDeclaration($customsDeclaration);
        }

        if ($this->shopId !== null) {
            $shipment->withShopId($this->shopId);
        }

        return $shipment;
    }

    /**
     * Resolve the shopper's chosen carrier slug to its SDK carrier value, falling back
     * to the configured default. The slug => value map is derived from the SDK, so new
     * carriers need no change here.
     */
    private function carrier(?DeliveryOptionsDto $deliveryOptions): string
    {
        $slug = $deliveryOptions?->carrier;

        if ($slug !== null) {
            $value = (new CarrierSettingsBuilder())->carrierValuesBySlug()[$slug] ?? null;

            if ($value !== null) {
                return $value;
            }

            $this->reportFallback('carrier', $this->defaultCarrier);
        }

        return $this->defaultCarrier;
    }

    /**
     * Attach the shopper's chosen pickup location to the shipment. A drop-off point
     * is where the merchant hands in a parcel and is therefore a different field.
     * Skipped when the data is incomplete, so we never send half a pickup address.
     */
    private function applyPickup(Shipment $shipment, ?DeliveryOptionsDto $deliveryOptions): void
    {
        $pickup = $deliveryOptions?->pickup;

        if ($pickup === null) {
            return;
        }

        foreach (['postal_code', 'location_name', 'city', 'street', 'number', 'location_code'] as $field) {
            if (empty($pickup[$field])) {
                $this->reportDiagnostic('Incomplete Delivery Options pickup location; ignoring pickup data.');
                return;
            }
        }

        $shipment->setPickup(new PickupModel($pickup));
    }

    /** Build the SDK recipient model. */
    private function recipient(RecipientDto $dto): RecipientModel
    {
        $recipient = new RecipientModel();
        $recipient->setPerson($dto->person);
        $recipient->setCompany($dto->company);
        $recipient->setStreet($dto->street);
        $recipient->setNumber($dto->number);

        if ($dto->numberSuffix !== null && $dto->numberSuffix !== '') {
            $recipient->setNumberSuffix($dto->numberSuffix);
        }

        $recipient->setPostalCode($dto->postalCode);
        $recipient->setCity($dto->city);
        $recipient->setCc($dto->cc);

        if ($dto->email !== null && $dto->email !== '') {
            $recipient->setEmail($dto->email);
        }

        if ($dto->phone !== null && $dto->phone !== '') {
            $recipient->setPhone($dto->phone);
        }

        return $recipient;
    }

    /**
     * Carry the chosen package, delivery type and centrally supported widget
     * options onto the shipment. Falls back to the default package type when
     * none was picked.
     */
    private function options(?DeliveryOptionsDto $deliveryOptions): ShipmentOptions
    {
        $shipmentOptions = [];

        if ($deliveryOptions !== null) {
            foreach ((new CarrierSettingsBuilder())->shipmentOptionKeys() as $key) {
                $value = $deliveryOptions->shipmentOption($key);

                if ($value !== null) {
                    $shipmentOptions[$key] = (int) $value;
                }
            }
        }

        $options = new ShipmentOptions($shipmentOptions);
        $options->setPackageType($this->packageType($deliveryOptions));

        if ($deliveryOptions === null) {
            return $options;
        }

        if ($deliveryOptions->deliveryType !== null) {
            $deliveryType = $this->deliveryType($deliveryOptions->deliveryType);

            if ($deliveryType !== null) {
                $options->setDeliveryType($deliveryType);
            }
        }

        if ($deliveryOptions->deliveryDate !== null) {
            $deliveryDate = $this->upcomingDeliveryDate($deliveryOptions->deliveryDate);

            if ($deliveryDate !== null) {
                $options->setDeliveryDate($deliveryDate);
            }
        }

        return $options;
    }

    /**
     * Return the chosen delivery date, or null when that day has already passed.
     * Orders are regularly exported days after checkout and the shipment API
     * rejects past dates, so a stale choice is dropped instead of failing the export.
     */
    private function upcomingDeliveryDate(string $deliveryDate): ?string
    {
        if (substr($deliveryDate, 0, 10) < date('Y-m-d')) {
            $this->reportDiagnostic(sprintf(
                'Chosen delivery date %s has passed; exporting without a delivery date.',
                substr($deliveryDate, 0, 10)
            ));

            return null;
        }

        return $deliveryDate;
    }

    /** Use the checkout package type when supported, otherwise the configured default. */
    private function packageType(?DeliveryOptionsDto $deliveryOptions): string
    {
        $slug = $deliveryOptions?->packageType;

        if ($slug === null || trim($slug) === '') {
            return $this->defaultPackageType;
        }

        $value = PackageTypeMapping::toSdk($slug);

        if ($value !== null) {
            return $value;
        }

        $this->reportFallback('package type', $this->defaultPackageType);

        return $this->defaultPackageType;
    }

    /** Resolve a Delivery Options delivery type through aliases or a generated constant. */
    private function deliveryType(string $slug): ?string
    {
        $normalised = strtolower(trim($slug));
        $value = self::DELIVERY_TYPES[$normalised]
            ?? self::generatedEnumValue($normalised, RefTypesDeliveryTypeV2::class);

        if ($value !== null) {
            return $value;
        }

        $this->reportFallback('delivery type', 'unset');

        return null;
    }

    /**
     * Resolve a safe SCREAMING_SNAKE_CASE slug against an SDK-generated enum.
     *
     * @param class-string $enumClass Generated model exposing getAllowableEnumValues().
     */
    private static function generatedEnumValue(string $slug, string $enumClass): ?string
    {
        $constantName = strtoupper(trim($slug));

        if (preg_match('/^[A-Z][A-Z0-9_]*$/', $constantName) !== 1) {
            return null;
        }

        $constant = $enumClass . '::' . $constantName;

        if (!defined($constant)) {
            return null;
        }

        $value = constant($constant);

        return is_string($value) && in_array($value, $enumClass::getAllowableEnumValues(), true)
            ? $value
            : null;
    }

    /** Report a traceable, non-sensitive fallback to the controller-provided logger. */
    private function reportFallback(string $field, string $fallback): void
    {
        self::notifyFallback($this->fallbackReporter, $field, $fallback);
    }

    /**
     * Emit a fallback without repeating the shopper-provided unsupported value.
     *
     * @param callable(string): void|null $reporter
     */
    private static function notifyFallback(
        ?callable $reporter,
        string $field,
        string $fallback
    ): void {
        if ($reporter === null) {
            return;
        }

        self::notifyDiagnostic($reporter, sprintf(
            'Unsupported Delivery Options %s; using "%s".',
            $field,
            $fallback
        ));
    }

    /** Emit a diagnostic through the optional controller-provided logger. */
    private function reportDiagnostic(string $message): void
    {
        self::notifyDiagnostic($this->fallbackReporter, $message);
    }

    /**
     * Invoke the optional diagnostics callback.
     *
     * @param callable(string): void|null $reporter
     */
    private static function notifyDiagnostic(?callable $reporter, string $message): void
    {
        if ($reporter !== null) {
            $reporter($message);
        }
    }

    /** Build the total weight and optional package dimensions. */
    private function physicalProperties(OrderDto $order): PhysicalPropertiesModel
    {
        $weight = 0;

        foreach ($order->items as $item) {
            if (!$item->requiresShipping) {
                continue;
            }

            $weight += $item->weight * $item->quantity;
        }

        // Some carrier integrations reject weight 0. Prefer the configured fallback
        // and otherwise use a technical minimum of 1 gram.
        if ($weight <= 0) {
            $weight = $this->fallbackWeight > 0
                ? $this->fallbackWeight
                : self::MINIMUM_WEIGHT_IN_GRAMS;
            $this->reportDiagnostic(sprintf(
                'Order products provide no weight; using %d gram%s.',
                $weight,
                $weight === 1 ? '' : 's'
            ));
        }

        $properties = new PhysicalPropertiesModel();
        $properties->setWeight($weight);

        if ($this->dimensions !== null) {
            $properties->setLength($this->dimensions['length']);
            $properties->setWidth($this->dimensions['width']);
            $properties->setHeight($this->dimensions['height']);
        }

        return $properties;
    }

    /**
     * Reject orders without the recipient data MyParcel needs, so the failure is a
     * clear message instead of a generic API rejection.
     */
    private function assertDeliverable(RecipientDto $recipient): void
    {
        $missing = [];

        $required = [
            MissingRecipientFieldsException::COUNTRY     => $recipient->cc,
            MissingRecipientFieldsException::STREET      => $recipient->street,
            MissingRecipientFieldsException::POSTAL_CODE => $recipient->postalCode,
            MissingRecipientFieldsException::CITY        => $recipient->city,
        ];

        foreach ($required as $field => $value) {
            if (trim((string) $value) === '') {
                $missing[] = $field;
            }
        }

        if (trim($recipient->person) === '' && trim((string) $recipient->company) === '') {
            $missing[] = MissingRecipientFieldsException::PERSON_OR_COMPANY;
        }

        if ($missing !== []) {
            throw new MissingRecipientFieldsException($missing);
        }
    }
}
