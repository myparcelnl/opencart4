<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Dto;

/** One deliverable line from the order, including its customs attributes. */
final class OrderItemDto
{
    /** Create one order line with weight and value expressed per individual item. */
    public function __construct(
        public string $description,
        public int $quantity,
        public int $weight,   // grams, per single item
        public float $value,  // price in the named currency, per single item
        public string $hsCode = '',
        public string $countryOfOrigin = '',
        public string $currency = 'EUR',
        public bool $requiresShipping = true
    ) {
    }
}
