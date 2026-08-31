<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Tests\Unit;

use MyParcelNL\OpenCart\Core\Dto\DeliveryOptionsDto;
use MyParcelNL\OpenCart\Core\Dto\OrderDto;
use MyParcelNL\OpenCart\Core\Dto\OrderItemDto;
use MyParcelNL\OpenCart\Core\Dto\RecipientDto;
use MyParcelNL\OpenCart\Core\Helper\OrderToShipmentMapper;
use MyParcelNL\OpenCart\Core\Service\DeliveryOptions\CarrierSettingsBuilder;
use MyParcelNL\OpenCart\Core\Service\Shipment\MissingRecipientFieldsException;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\RefShipmentPackageTypeV2;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\RefTypesCarrierV2;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\RefTypesDeliveryTypeV2;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\ShipmentPostShipmentsRequestV11;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\ShipmentPostShipmentsRequestV11Data;
use MyParcelNL\Sdk\Client\Generated\CoreApi\ObjectSerializer;
use MyParcelNL\Sdk\Model\Shipment\Carrier;
use MyParcelNL\Sdk\Model\Shipment\Mapping\DeliveryTypeApiMapping;
use MyParcelNL\Sdk\Model\Shipment\PackageType;
use PHPUnit\Framework\TestCase;

class OrderToShipmentMapperTest extends TestCase
{
    public function testMapsRecipientCarrierPackageTypeAndTotalWeight(): void
    {
        $shipment = $this->mapper()->mapOrderToShipment($this->order());

        self::assertSame('NL', $shipment->getRecipient()->getCc());
        self::assertSame('Jan Jansen', $shipment->getRecipient()->getPerson());
        self::assertSame('1234AB', $shipment->getRecipient()->getPostalCode());

        // carrier + package type are stored as SDK ids, so compare against the same mapping
        self::assertSame(Carrier::toId(RefTypesCarrierV2::POSTNL), $shipment->getCarrier());
        self::assertSame(PackageType::toId(RefShipmentPackageTypeV2::PACKAGE), $shipment->getOptions()->getPackageType());

        // weight = sum of weight * quantity: 2*200 + 1*350
        self::assertSame(750, $shipment->getPhysicalProperties()->getWeight());

        self::assertSame('oc-5', $shipment->getReferenceIdentifier());
    }

    public function testWithoutDeliveryOptionsLeavesDeliveryTypeUnset(): void
    {
        $shipment = $this->mapper()->mapOrderToShipment($this->order());

        self::assertNull($shipment->getOptions()->getDeliveryType());
    }

    public function testAppliesChosenDeliveryAndPackageType(): void
    {
        $deliveryOptions = new DeliveryOptionsDto(deliveryType: 'morning', packageType: 'mailbox');

        $options = $this->mapper()->mapOrderToShipment($this->order(), $deliveryOptions)->getOptions();

        self::assertSame(
            (new DeliveryTypeApiMapping())->enumToId(RefTypesDeliveryTypeV2::MORNING),
            $options->getDeliveryType()
        );
        self::assertSame(PackageType::toId(RefShipmentPackageTypeV2::MAILBOX), $options->getPackageType());
    }

    public function testAppliesSupportedShipmentOptionFlags(): void
    {
        $deliveryOptions = new DeliveryOptionsDto(shipmentOptions: [
            'signature' => true,
            'only_recipient' => true,
        ]);

        $options = $this->mapper()->mapOrderToShipment($this->order(), $deliveryOptions)->getOptions();

        self::assertSame(1, $options->getSignature());
        self::assertSame(1, $options->getOnlyRecipient());
    }

    public function testDoesNotForwardAgeCheckFromDeliveryOptionsPayload(): void
    {
        $deliveryOptions = DeliveryOptionsDto::fromJson([
            'shipmentOptions' => ['ageCheck' => true],
        ]);

        $options = $this->mapper()->mapOrderToShipment($this->order(), $deliveryOptions)->getOptions();

        self::assertNull($options->getAgeCheck());
    }

    public function testUnknownPackageTypeFallsBackToDefault(): void
    {
        $deliveryOptions = new DeliveryOptionsDto(packageType: 'does_not_exist');

        $options = $this->mapper()->mapOrderToShipment($this->order(), $deliveryOptions)->getOptions();

        self::assertSame(PackageType::toId(RefShipmentPackageTypeV2::PACKAGE), $options->getPackageType());
    }

    public function testPackageTypeValueResolvesSlugToSdkValue(): void
    {
        self::assertSame(RefShipmentPackageTypeV2::MAILBOX, OrderToShipmentMapper::packageTypeValue('mailbox'));
        self::assertSame(RefShipmentPackageTypeV2::UNFRANKED, OrderToShipmentMapper::packageTypeValue('letter'));
        self::assertSame(RefShipmentPackageTypeV2::SMALL_PACKAGE, OrderToShipmentMapper::packageTypeValue('package_small'));
        self::assertSame(RefShipmentPackageTypeV2::PALLET, OrderToShipmentMapper::packageTypeValue('pallet'));
        self::assertSame(RefShipmentPackageTypeV2::PACKAGE, OrderToShipmentMapper::packageTypeValue('does_not_exist'));
    }

    public function testPackageTypeSlugsIncludeNewGeneratedValuesWithoutDuplicatingAliases(): void
    {
        $slugs = OrderToShipmentMapper::packageTypeSlugs();

        self::assertContains('package_small', $slugs);
        self::assertContains('letter', $slugs);
        self::assertContains('pallet', $slugs);
        self::assertNotContains('small_package', $slugs);
        self::assertNotContains('unfranked', $slugs);
    }

    public function testConventionBasedDeliveryTypeUsesGeneratedSdkConstant(): void
    {
        $deliveryOptions = new DeliveryOptionsDto(deliveryType: 'early_morning');
        $options = $this->mapper()->mapOrderToShipment($this->order(), $deliveryOptions)->getOptions();

        self::assertSame(
            (new DeliveryTypeApiMapping())->enumToId(RefTypesDeliveryTypeV2::EARLY_MORNING),
            $options->getDeliveryType()
        );
    }

    public function testUnexpectedDeliveryOptionsValuesAreReportedBeforeFallback(): void
    {
        $messages = [];
        $mapper = new OrderToShipmentMapper(
            RefTypesCarrierV2::POSTNL,
            RefShipmentPackageTypeV2::PACKAGE,
            null,
            null,
            0,
            static function (string $message) use (&$messages): void {
                $messages[] = $message;
            }
        );
        $deliveryOptions = new DeliveryOptionsDto(
            carrier: 'unknown\ncarrier',
            deliveryType: 'unknown_delivery',
            packageType: 'unknown_package'
        );

        $shipment = $mapper->mapOrderToShipment($this->order(), $deliveryOptions);

        self::assertSame(Carrier::toId(RefTypesCarrierV2::POSTNL), $shipment->getCarrier());
        self::assertSame(PackageType::toId(RefShipmentPackageTypeV2::PACKAGE), $shipment->getOptions()->getPackageType());
        self::assertNull($shipment->getOptions()->getDeliveryType());
        self::assertCount(3, $messages);
        self::assertStringNotContainsString("\n", implode('', $messages));
        self::assertStringNotContainsString('unknown', implode('', $messages));
    }

    public function testFromJsonReadsTheStoredWidgetPayload(): void
    {
        $deliveryOptions = DeliveryOptionsDto::fromJson([
            'carrier'         => 'postnl',
            'date'            => '2026-09-02',
            'deliveryType'    => 'evening',
            'packageType'     => 'package',
            'shipmentOptions' => [
                'signature'     => true,
                'onlyRecipient' => false,
            ],
        ]);

        self::assertSame('postnl', $deliveryOptions->carrier);
        self::assertSame('2026-09-02 00:00:00', $deliveryOptions->deliveryDate);
        self::assertSame('evening', $deliveryOptions->deliveryType);
        self::assertTrue($deliveryOptions->shipmentOption('signature'));
        self::assertFalse($deliveryOptions->shipmentOption('only_recipient'));
    }

    public function testFromJsonNormalisesPickupLocationForSdkModel(): void
    {
        $deliveryOptions = DeliveryOptionsDto::fromJson([
            'carrier' => 'postnl',
            'deliveryType' => 'pickup',
            'pickupLocation' => [
                'postalCode' => '1012AA',
                'locationCode' => '176193',
                'locationName' => 'Service Point Amsterdam CS',
                'city' => 'Amsterdam',
                'street' => 'De Ruijterkade',
                'number' => '26',
            ],
        ]);

        self::assertSame('1012AA', $deliveryOptions->pickup['postal_code'] ?? null);
        self::assertSame('176193', $deliveryOptions->pickup['location_code'] ?? null);
        self::assertSame('Service Point Amsterdam CS', $deliveryOptions->pickup['location_name'] ?? null);
    }

    public function testFromJsonAcceptsLegacyDeliveryDateAndRejectsInvalidDate(): void
    {
        $legacy = DeliveryOptionsDto::fromJson([
            'deliveryDate' => '2026-09-02T14:30:00+02:00',
        ]);
        $invalid = DeliveryOptionsDto::fromJson([
            'date' => '2026-02-30',
        ]);

        self::assertSame('2026-09-02 14:30:00', $legacy->deliveryDate);
        self::assertNull($invalid->deliveryDate);
    }

    public function testThrowsWhenRecipientIsIncomplete(): void
    {
        $order = new OrderDto(
            new RecipientDto(cc: 'NL', person: 'Jan Jansen', postalCode: '1234AB', city: 'Amsterdam', street: ''),
            [new OrderItemDto('T-shirt', 1, 200, 15.00)],
            'oc-9',
        );

        try {
            $this->mapper()->mapOrderToShipment($order);
            self::fail('Expected incomplete recipient validation to fail.');
        } catch (MissingRecipientFieldsException $exception) {
            self::assertSame([MissingRecipientFieldsException::STREET], $exception->fields());
        }
    }

    public function testAppliesChosenCarrier(): void
    {
        $deliveryOptions = new DeliveryOptionsDto(carrier: 'upsstandard');

        $shipment = $this->mapper()->mapOrderToShipment($this->order(), $deliveryOptions);

        self::assertSame(Carrier::toId(RefTypesCarrierV2::UPS_STANDARD), $shipment->getCarrier());
    }

    public function testUnknownCarrierFallsBackToDefault(): void
    {
        $deliveryOptions = new DeliveryOptionsDto(carrier: 'not_a_carrier');

        $shipment = $this->mapper()->mapOrderToShipment($this->order(), $deliveryOptions);

        self::assertSame(Carrier::toId(RefTypesCarrierV2::POSTNL), $shipment->getCarrier());
    }

    public function testCarrierValuesBySlugInvertsTheSdkDerivedMap(): void
    {
        $map = (new CarrierSettingsBuilder())->carrierValuesBySlug();

        self::assertSame(RefTypesCarrierV2::POSTNL, $map['postnl']);
        self::assertSame(RefTypesCarrierV2::UPS_STANDARD, $map['upsstandard']);
    }

    public function testAppliesPickupLocation(): void
    {
        $deliveryOptions = new DeliveryOptionsDto(
            carrier: 'upsstandard',
            deliveryType: 'pickup',
            pickup: [
                'postal_code' => '2132JE',
                'location_name' => 'Pickup point',
                'city' => 'Hoofddorp',
                'street' => 'Kruisweg',
                'number' => '10',
                'location_code' => 'ABC123',
            ],
        );

        $shipment = $this->mapper()->mapOrderToShipment($this->order(), $deliveryOptions);
        $pickup = $shipment->getPickup();

        self::assertNotNull($pickup);
        self::assertNull($shipment->getDropOffPoint());
        self::assertSame('ABC123', $pickup->getLocationCode());
        self::assertSame('Hoofddorp', $pickup->getCity());
    }

    public function testIncompletePickupIsSkipped(): void
    {
        $messages = [];
        $deliveryOptions = new DeliveryOptionsDto(
            deliveryType: 'pickup',
            pickup: ['postal_code' => '2132JE', 'city' => 'Hoofddorp'],
        );
        $mapper = new OrderToShipmentMapper(
            RefTypesCarrierV2::POSTNL,
            RefShipmentPackageTypeV2::PACKAGE,
            null,
            null,
            0,
            static function (string $message) use (&$messages): void {
                $messages[] = $message;
            }
        );

        $shipment = $mapper->mapOrderToShipment($this->order(), $deliveryOptions);

        self::assertNull($shipment->getPickup());
        self::assertSame(['Incomplete Delivery Options pickup location; ignoring pickup data.'], $messages);
    }

    public function testMapsRecipientPhoneAndEmail(): void
    {
        $order = new OrderDto(
            new RecipientDto(
                cc: 'NL',
                person: 'Jan Jansen',
                postalCode: '1234AB',
                city: 'Amsterdam',
                street: 'Hoofdstraat',
                number: '12',
                email: 'jan@example.com',
                phone: '+31612345678',
            ),
            [new OrderItemDto('T-shirt', 1, 200, 15.00)],
            'oc-11',
        );

        $recipient = $this->mapper()->mapOrderToShipment($order)->getRecipient();

        self::assertSame('jan@example.com', $recipient->getEmail());
        self::assertSame('+31612345678', $recipient->getPhone());
    }

    public function testMapsRecipientNumberSuffix(): void
    {
        $order = new OrderDto(
            new RecipientDto(
                cc: 'NL',
                person: 'Jan Jansen',
                postalCode: '1234AB',
                city: 'Amsterdam',
                street: 'Hoofdstraat',
                number: '12',
                numberSuffix: 'A',
            ),
            [new OrderItemDto('T-shirt', 1, 200, 15.00)],
            'oc-number-suffix',
        );

        $recipient = $this->mapper()->mapOrderToShipment($order)->getRecipient();

        self::assertSame('A', $recipient->getNumberSuffix());
    }

    public function testMappedShipmentSerializesAsCreateRequest(): void
    {
        $order = new OrderDto(
            new RecipientDto(
                cc: 'NL',
                person: 'Jan Jansen',
                postalCode: '2132JE',
                city: 'Hoofddorp',
                street: 'Kruisweg',
                number: '10',
                email: 'jan@example.com',
                phone: '+31612345678',
            ),
            [new OrderItemDto('Doos', 1, 750, 12.50)],
            'oc-sdk-compatibility',
        );
        $deliveryOptions = new DeliveryOptionsDto(
            carrier: 'upsstandard',
            deliveryType: 'pickup',
            packageType: 'package',
            shipmentOptions: [
                'signature' => true,
                'only_recipient' => false,
            ],
            pickup: [
                'postal_code'  => '2132JE',
                'location_name' => 'Pickup point',
                'city'          => 'Hoofddorp',
                'street'        => 'Kruisweg',
                'number'        => '10',
                'location_code' => 'ABC123',
            ],
            deliveryDate: $futureDeliveryDate = date('Y-m-d 00:00:00', strtotime('+5 days')),
        );
        $shipment = $this->mapper()->mapOrderToShipment($order, $deliveryOptions);
        $data = new ShipmentPostShipmentsRequestV11Data();
        $data->setShipments([$shipment]);
        $request = new ShipmentPostShipmentsRequestV11();
        $request->setData($data);

        $payload = json_decode(
            json_encode(ObjectSerializer::sanitizeForSerialization($request), JSON_THROW_ON_ERROR),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $serializedShipment = $payload['data']['shipments'][0];

        self::assertSame('jan@example.com', $serializedShipment['recipient']['email']);
        self::assertSame('Kruisweg', $serializedShipment['recipient']['street']);
        self::assertSame('ABC123', $serializedShipment['pickup']['location_code']);
        self::assertArrayNotHasKey('drop_off_point', $serializedShipment);
        self::assertSame(750, $serializedShipment['physical_properties']['weight']);
        self::assertSame(Carrier::toId(RefTypesCarrierV2::UPS_STANDARD), $serializedShipment['carrier']);
        self::assertSame(PackageType::toId(RefShipmentPackageTypeV2::PACKAGE), $serializedShipment['options']['package_type']);
        self::assertSame(1, $serializedShipment['options']['signature']);
        self::assertSame(0, $serializedShipment['options']['only_recipient']);
        self::assertSame($futureDeliveryDate, $serializedShipment['options']['delivery_date']);
        self::assertSame(
            (new DeliveryTypeApiMapping())->enumToId(RefTypesDeliveryTypeV2::PICKUP),
            $serializedShipment['options']['delivery_type']
        );
    }

    public function testDropsAPassedDeliveryDateInsteadOfFailingTheExport(): void
    {
        $diagnostics = [];
        $mapper = new OrderToShipmentMapper(
            RefTypesCarrierV2::POSTNL,
            fallbackReporter: static function (string $message) use (&$diagnostics): void {
                $diagnostics[] = $message;
            }
        );

        $stale = new DeliveryOptionsDto(deliveryDate: date('Y-m-d 00:00:00', strtotime('-1 day')));
        $shipment = $mapper->mapOrderToShipment($this->order(), $stale);

        self::assertNull($shipment->getOptions()->getDeliveryDate());
        self::assertNotEmpty($diagnostics);

        $today = new DeliveryOptionsDto(deliveryDate: date('Y-m-d 00:00:00'));
        $shipment = $mapper->mapOrderToShipment($this->order(), $today);

        self::assertSame(date('Y-m-d 00:00:00'), $shipment->getOptions()->getDeliveryDate());
    }

    public function testRestOfWorldShipmentSerializesCustomsDeclaration(): void
    {
        $order = new OrderDto(
            new RecipientDto(
                cc: 'GB',
                person: 'Jane Smith',
                postalCode: 'SW1A 1AA',
                city: 'London',
                street: 'Downing Street',
                number: '10',
            ),
            [new OrderItemDto('T-shirt', 2, 200, 15.25, '610910', 'IT', 'EUR')],
            'oc-customs',
        );
        $shipment = $this->mapper()->mapOrderToShipment($order);
        $data = (new ShipmentPostShipmentsRequestV11Data())->setShipments([$shipment]);
        $request = (new ShipmentPostShipmentsRequestV11())->setData($data);
        $payload = json_decode(
            json_encode(ObjectSerializer::sanitizeForSerialization($request), JSON_THROW_ON_ERROR),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $customs = $payload['data']['shipments'][0]['customs_declaration'];

        self::assertSame(400, $customs['weight']);
        self::assertSame('610910', $customs['items'][0]['classification']);
        self::assertSame('IT', $customs['items'][0]['country']);
        self::assertSame(2, $customs['items'][0]['amount']);
        self::assertSame(200, $customs['items'][0]['weight']);
        self::assertSame(['currency' => 'EUR', 'amount' => 1525], $customs['items'][0]['item_value']);
    }

    public function testSetsShopIdWhenProvided(): void
    {
        $mapper = new OrderToShipmentMapper(RefTypesCarrierV2::POSTNL, RefShipmentPackageTypeV2::PACKAGE, 10532);

        $shipment = $mapper->mapOrderToShipment($this->order());

        self::assertSame(10532, $shipment->getShopId());
    }

    public function testAppliesDimensionsWhenProvided(): void
    {
        $mapper = new OrderToShipmentMapper(
            RefTypesCarrierV2::POSTNL,
            RefShipmentPackageTypeV2::PACKAGE,
            null,
            ['length' => 30, 'width' => 20, 'height' => 10]
        );

        $properties = $mapper->mapOrderToShipment($this->order())->getPhysicalProperties();

        self::assertSame(30, $properties->getLength());
        self::assertSame(20, $properties->getWidth());
        self::assertSame(10, $properties->getHeight());
    }

    public function testLeavesDimensionsUnsetByDefault(): void
    {
        $properties = $this->mapper()->mapOrderToShipment($this->order())->getPhysicalProperties();

        self::assertNull($properties->getLength());
    }

    public function testFallsBackToConfiguredWeightWhenProductsCarryNone(): void
    {
        $messages = [];
        $mapper = new OrderToShipmentMapper(
            RefTypesCarrierV2::POSTNL,
            RefShipmentPackageTypeV2::PACKAGE,
            null,
            null,
            1000,
            static function (string $message) use (&$messages): void {
                $messages[] = $message;
            }
        );

        $order = new OrderDto(
            new RecipientDto(cc: 'NL', person: 'Jan Jansen', postalCode: '1234AB', city: 'Amsterdam', street: 'Hoofdstraat', number: '12'),
            [new OrderItemDto('Weightless', 2, 0, 15.00)],
            'oc-weightless',
        );

        self::assertSame(1000, $mapper->mapOrderToShipment($order)->getPhysicalProperties()->getWeight());
        self::assertSame(['Order products provide no weight; using 1000 grams.'], $messages);
    }

    public function testUsesOneGramWhenProductsAndFallbackCarryNoWeight(): void
    {
        $order = new OrderDto(
            new RecipientDto(cc: 'NL', person: 'Jan Jansen', postalCode: '1234AB', city: 'Amsterdam', street: 'Hoofdstraat', number: '12'),
            [new OrderItemDto('Weightless', 1, 0, 15.00)],
            'oc-minimum-weight',
        );

        self::assertSame(1, $this->mapper()->mapOrderToShipment($order)->getPhysicalProperties()->getWeight());
    }

    public function testFallbackWeightDoesNotOverridePartiallyKnownWeight(): void
    {
        $mapper = new OrderToShipmentMapper(
            RefTypesCarrierV2::POSTNL,
            RefShipmentPackageTypeV2::PACKAGE,
            null,
            null,
            1000
        );
        $order = new OrderDto(
            new RecipientDto(cc: 'NL', person: 'Jan Jansen', postalCode: '1234AB', city: 'Amsterdam', street: 'Hoofdstraat', number: '12'),
            [
                new OrderItemDto('Known weight', 2, 200, 15.00),
                new OrderItemDto('Weightless', 3, 0, 8.00),
            ],
            'oc-partially-known-weight',
        );

        self::assertSame(400, $mapper->mapOrderToShipment($order)->getPhysicalProperties()->getWeight());
    }

    public function testProductsWithoutShippingDoNotAffectShipmentWeight(): void
    {
        $order = new OrderDto(
            new RecipientDto(cc: 'NL', person: 'Jan Jansen', postalCode: '1234AB', city: 'Amsterdam', street: 'Hoofdstraat', number: '12'),
            [
                new OrderItemDto('Physical product', 1, 200, 15.00),
                new OrderItemDto('Download', 1, 5000, 8.00, '', '', 'EUR', false),
            ],
            'oc-mixed-order',
        );

        self::assertSame(200, $this->mapper()->mapOrderToShipment($order)->getPhysicalProperties()->getWeight());
    }

    private function mapper(): OrderToShipmentMapper
    {
        return new OrderToShipmentMapper(RefTypesCarrierV2::POSTNL);
    }

    private function order(): OrderDto
    {
        return new OrderDto(
            new RecipientDto(
                cc: 'NL',
                person: 'Jan Jansen',
                postalCode: '1234AB',
                city: 'Amsterdam',
                street: 'Hoofdstraat',
                number: '12',
            ),
            [
                new OrderItemDto('T-shirt', 2, 200, 15.00),
                new OrderItemDto('Mok', 1, 350, 8.00),
            ],
            'oc-5',
        );
    }
}
