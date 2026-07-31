<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Tests\Unit;

use MyParcelNL\OpenCart\Core\Dto\OrderDto;
use MyParcelNL\OpenCart\Core\Dto\OrderItemDto;
use MyParcelNL\OpenCart\Core\Dto\RecipientDto;
use MyParcelNL\OpenCart\Core\Service\Shipment\CustomsDeclarationException;
use MyParcelNL\OpenCart\Core\Service\Shipment\CustomsDeclarationFromOrder;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\ShipmentDefsCustomsShipmentType;
use PHPUnit\Framework\TestCase;

class CustomsDeclarationFromOrderTest extends TestCase
{
    /**
     * @dataProvider localAndEuCountries
     */
    public function testDoesNotCreateDeclarationForLocalOrEuDestination(string $country): void
    {
        $declaration = (new CustomsDeclarationFromOrder())->create(
            $this->order($country),
            750
        );

        self::assertNull($declaration);
    }

    /** @return array<string, array{string}> */
    public function localAndEuCountries(): array
    {
        return [
            'Netherlands' => ['NL'],
            'Belgium' => ['BE'],
            'Italy' => ['IT'],
        ];
    }

    public function testMapsProductCustomsValuesToGeneratedModels(): void
    {
        $description = str_repeat('Product description ', 4);
        $order = $this->order('GB', [
            new OrderItemDto($description, 2, 200, 15.25, '610910', 'IT', 'EUR'),
        ]);

        $declaration = (new CustomsDeclarationFromOrder('999999', 'NL'))->create($order, 400);

        self::assertNotNull($declaration);
        self::assertSame(ShipmentDefsCustomsShipmentType::COMMERCIAL_GOODS, $declaration->getContents());
        self::assertSame('oc-customs', $declaration->getInvoice());
        self::assertSame(400, $declaration->getWeight());
        self::assertCount(1, $declaration->getItems());

        $item = $declaration->getItems()[0];

        self::assertSame(mb_substr($description, 0, 50), $item->getDescription());
        self::assertSame(2, $item->getAmount());
        self::assertSame(200, $item->getWeight());
        self::assertSame('610910', $item->getClassification());
        self::assertSame('IT', $item->getCountry());
        self::assertSame('EUR', $item->getItemValue()->getCurrency());
        self::assertSame(1525, $item->getItemValue()->getAmount());
    }

    public function testUsesConfiguredFallbacksAndPreservesItemValue(): void
    {
        $order = $this->order('CH', [
            new OrderItemDto('Sample', 1, 0, 0.25),
        ]);

        $declaration = (new CustomsDeclarationFromOrder('123456', 'nl'))->create($order, 1000);
        $item = $declaration?->getItems()[0];

        self::assertNotNull($item);
        self::assertSame('123456', $item->getClassification());
        self::assertSame('NL', $item->getCountry());
        self::assertSame(25, $item->getItemValue()->getAmount());
        self::assertSame(0, $item->getWeight());
        self::assertSame(1000, $declaration?->getWeight());
    }

    public function testUsesGenericHsCodeWhenConfiguredFallbackIsEmpty(): void
    {
        $order = $this->order('US', [
            new OrderItemDto('Sample', 1, 100, 1.00, '', 'NL'),
        ]);

        $declaration = (new CustomsDeclarationFromOrder('', ''))->create($order, 100);

        self::assertSame(
            CustomsDeclarationFromOrder::DEFAULT_CUSTOMS_CODE,
            $declaration?->getItems()[0]->getClassification()
        );
    }

    public function testExcludesProductsThatDoNotRequireShipping(): void
    {
        $order = $this->order('GB', [
            new OrderItemDto('T-shirt', 1, 200, 15.00, '610910', 'NL'),
            new OrderItemDto('Download', 1, 0, 5.00, '', '', 'EUR', false),
        ]);

        $declaration = (new CustomsDeclarationFromOrder())->create($order, 200);

        self::assertCount(1, $declaration?->getItems() ?? []);
        self::assertSame('T-shirt', $declaration?->getItems()[0]->getDescription());
    }

    public function testRejectsRestOfWorldOrderWithoutDeliverableProducts(): void
    {
        $this->expectCustomsFailure(CustomsDeclarationException::EMPTY_ITEMS);

        (new CustomsDeclarationFromOrder())->create(
            $this->order('GB', [
                new OrderItemDto('Download', 1, 0, 5.00, '', '', 'EUR', false),
            ]),
            1
        );
    }

    public function testRequiresCountryOfOriginForRestOfWorldOrders(): void
    {
        $this->expectCustomsFailure(CustomsDeclarationException::MISSING_COUNTRY_OF_ORIGIN);

        (new CustomsDeclarationFromOrder())->create(
            $this->order('GB', [new OrderItemDto('T-shirt', 1, 200, 15.00)]),
            200
        );
    }

    public function testRejectsInvalidCountryOfOrigin(): void
    {
        $this->expectCustomsFailure(CustomsDeclarationException::INVALID_COUNTRY_OF_ORIGIN);

        (new CustomsDeclarationFromOrder())->create(
            $this->order('GB', [new OrderItemDto('T-shirt', 1, 200, 15.00, '610910', 'XX')]),
            200
        );
    }

    public function testRejectsQuantityOutsideCoreApiRange(): void
    {
        $this->expectCustomsFailure(CustomsDeclarationException::INVALID_QUANTITY);

        (new CustomsDeclarationFromOrder())->create(
            $this->order('GB', [new OrderItemDto('T-shirt', 0, 200, 15.00, '610910', 'NL')]),
            200
        );
    }

    public function testRejectsMoreThanOneHundredProductLines(): void
    {
        $items = array_fill(
            0,
            101,
            new OrderItemDto('T-shirt', 1, 200, 15.00, '610910', 'NL')
        );
        $this->expectCustomsFailure(CustomsDeclarationException::TOO_MANY_ITEMS);

        (new CustomsDeclarationFromOrder())->create($this->order('GB', $items), 20200);
    }

    public function testCustomsLimitOnlyCountsProductsThatRequireShipping(): void
    {
        $items = array_fill(
            0,
            101,
            new OrderItemDto('Download', 1, 0, 5.00, '', '', 'EUR', false)
        );
        $items[] = new OrderItemDto('T-shirt', 1, 200, 15.00, '610910', 'NL');

        $declaration = (new CustomsDeclarationFromOrder())->create($this->order('GB', $items), 200);

        self::assertCount(1, $declaration?->getItems() ?? []);
    }

    public function testRejectsUnsupportedStoreCurrency(): void
    {
        $this->expectCustomsFailure(CustomsDeclarationException::UNSUPPORTED_CURRENCY);

        (new CustomsDeclarationFromOrder())->create(
            $this->order('GB', [new OrderItemDto('T-shirt', 1, 200, 15.00, '610910', 'NL', 'USD')]),
            200
        );
    }

    /**
     * @param OrderItemDto[]|null $items
     */
    private function order(string $country, ?array $items = null): OrderDto
    {
        return new OrderDto(
            new RecipientDto(
                cc: $country,
                person: 'Jan Jansen',
                postalCode: 'SW1A 1AA',
                city: 'London',
                street: 'Downing Street',
                number: '10'
            ),
            $items ?? [new OrderItemDto('T-shirt', 1, 200, 15.00, '610910', 'NL')],
            'oc-customs'
        );
    }

    /** Expect one stable customs reason without testing an OpenCart translation here. */
    private function expectCustomsFailure(string $reason): void
    {
        $this->expectException(CustomsDeclarationException::class);
        $this->expectExceptionMessage($reason);
    }
}
