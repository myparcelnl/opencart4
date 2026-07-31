<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Dto;

/**
 * An order: recipient, line items and a reference to the OpenCart order.
 */
final class OrderDto
{
    /**
     * Create the order value consumed by the shipment mapper.
     *
     * @param OrderItemDto[] $items
     */
    public function __construct(
        public RecipientDto $recipient,
        public array $items,
        public string $reference   // OpenCart order id; set as the shipment's reference_identifier
    ) {
    }
}
