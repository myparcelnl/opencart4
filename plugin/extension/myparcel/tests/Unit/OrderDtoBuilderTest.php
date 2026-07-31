<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Tests\Unit;

use MyParcelNL\OpenCart\Core\Dto\OrderDto;
use MyParcelNL\OpenCart\Core\Helper\OrderDtoBuilder;
use PHPUnit\Framework\TestCase;

class OrderDtoBuilderTest extends TestCase
{
    public function testBuildsOrderDtoFromOpenCartOrder(): void
    {
        $order = [
            'order_id'            => '42',
            'shipping_firstname'  => 'Jan',
            'shipping_lastname'   => 'Jansen',
            'shipping_company'    => 'MyParcel',
            'shipping_address_1'  => 'Hoofdstraat 12a',
            'shipping_postcode'   => '1234AB',
            'shipping_city'       => 'Amsterdam',
            'shipping_iso_code_2' => 'nl',
        ];

        $products = [
            [
                'name' => 'T-shirt',
                'quantity' => 2,
                'weight' => 200,
                'price' => 15.00,
                'hs_code' => '610910',
                'country_of_origin' => 'it',
                'requires_shipping' => true,
            ],
            [
                'name' => 'Download',
                'quantity' => 1,
                'weight' => 0,
                'price' => 8.00,
                'requires_shipping' => false,
            ],
        ];

        $dto = (new OrderDtoBuilder())->build($order, $products, 'eur');

        self::assertInstanceOf(OrderDto::class, $dto);
        self::assertSame('42', $dto->reference);

        // Recipient: name joined, cc uppercased, street split into parts
        self::assertSame('NL', $dto->recipient->cc);
        self::assertSame('Jan Jansen', $dto->recipient->person);
        self::assertSame('1234AB', $dto->recipient->postalCode);
        self::assertSame('Amsterdam', $dto->recipient->city);
        self::assertSame('Hoofdstraat', $dto->recipient->street);
        self::assertSame('12', $dto->recipient->number);
        self::assertSame('a', $dto->recipient->numberSuffix);
        self::assertSame('MyParcel', $dto->recipient->company);

        // Items mapped one-to-one
        self::assertCount(2, $dto->items);
        self::assertSame('T-shirt', $dto->items[0]->description);
        self::assertSame(2, $dto->items[0]->quantity);
        self::assertSame(200, $dto->items[0]->weight);
        self::assertSame(15.00, $dto->items[0]->value);
        self::assertSame('610910', $dto->items[0]->hsCode);
        self::assertSame('IT', $dto->items[0]->countryOfOrigin);
        self::assertSame('EUR', $dto->items[0]->currency);
        self::assertTrue($dto->items[0]->requiresShipping);
        self::assertFalse($dto->items[1]->requiresShipping);
    }
}
