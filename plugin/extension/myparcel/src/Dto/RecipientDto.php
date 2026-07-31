<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Dto;

/**
 * Recipient address and contact details for a shipment.
 */
final class RecipientDto
{
    /** Create the recipient value passed to the shipment mapper. */
    public function __construct(
        public string $cc,
        public string $person,
        public string $postalCode,
        public string $city,
        public string $street,
        public ?string $number = null,
        public ?string $numberSuffix = null,
        public ?string $company = '',
        public ?string $email = null,
        public ?string $phone = null
    ) {
    }
}
