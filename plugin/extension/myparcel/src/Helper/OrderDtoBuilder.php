<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Helper;

use MyParcelNL\OpenCart\Core\Dto\OrderDto;
use MyParcelNL\OpenCart\Core\Dto\OrderItemDto;
use MyParcelNL\OpenCart\Core\Dto\RecipientDto;
use MyParcelNL\OpenCart\Core\Service\Address\StreetSplitter;

/**
 * Builds an OrderDto from a plain OpenCart order array.
 * The controller loads the order and resolves product weights (grams) before calling this.
 */
class OrderDtoBuilder
{
    /**
     * Build the export DTO from the order and its product lines.
     *
     * @param array<string, mixed>             $order         model_sale_order->getOrder()
     * @param array<int, array<string, mixed>> $orderProducts each line with weight/customs values attached
     */
    public function build(array $order, array $orderProducts, string $currency = 'EUR'): OrderDto
    {
        return new OrderDto(
            $this->recipient($order),
            $this->items($orderProducts, strtoupper(trim($currency))),
            (string) ($order['order_id'] ?? '')
        );
    }

    /**
     * Builds a RecipientDto from the order's shipping-address fields.
     *
     * @param array<string, mixed> $order model_sale_order->getOrder()
     */
    private function recipient(array $order): RecipientDto
    {
        $split = (new StreetSplitter())->split((string) ($order['shipping_address_1'] ?? ''));

        return new RecipientDto(
            cc:           strtoupper(trim((string) ($order['shipping_iso_code_2'] ?? ''))),
            person:       trim(((string) ($order['shipping_firstname'] ?? '')) . ' ' . ((string) ($order['shipping_lastname'] ?? ''))),
            postalCode:   trim((string) ($order['shipping_postcode'] ?? '')),
            city:         trim((string) ($order['shipping_city'] ?? '')),
            street:       $split['street'],
            number:       $split['number'] !== null ? (string) $split['number'] : null,
            numberSuffix: $split['suffix'],
            company:      trim((string) ($order['shipping_company'] ?? '')),
            // Contact details are order-level; some carriers (e.g. Poste Italiane) require a phone.
            email:        trim((string) ($order['email'] ?? '')) ?: null,
            phone:        trim((string) ($order['telephone'] ?? '')) ?: null,
        );
    }

    /**
     * Build the item DTOs used for the shipment weight.
     *
     * @param array<int, array<string, mixed>> $orderProducts
     * @return OrderItemDto[]
     */
    private function items(array $orderProducts, string $currency): array
    {
        $items = [];

        foreach ($orderProducts as $product) {
            $items[] = new OrderItemDto(
                (string) ($product['name'] ?? ''),
                (int) ($product['quantity'] ?? 0),
                (int) ($product['weight'] ?? 0),   // grams per single item; resolved by the controller
                (float) ($product['price'] ?? 0),  // value per single item in the store currency
                trim((string) ($product['hs_code'] ?? '')),
                strtoupper(trim((string) ($product['country_of_origin'] ?? ''))),
                $currency,
                (bool) ($product['requires_shipping'] ?? true)
            );
        }

        return $items;
    }
}
