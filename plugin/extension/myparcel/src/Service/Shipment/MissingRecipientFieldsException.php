<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Service\Shipment;

use InvalidArgumentException;

/** Recipient validation failure translated by the OpenCart admin adapter. */
final class MissingRecipientFieldsException extends InvalidArgumentException
{
    public const CITY = 'city';
    public const COUNTRY = 'country';
    public const PERSON_OR_COMPANY = 'person_or_company';
    public const POSTAL_CODE = 'postal_code';
    public const STREET = 'street';

    /** @var list<string> */
    private array $fields;

    /**
     * Keep field identifiers structured and independent of OpenCart language keys.
     *
     * @param list<string> $fields
     */
    public function __construct(array $fields)
    {
        parent::__construct('missing_recipient_fields');

        $this->fields = array_values(array_unique($fields));
    }

    /** @return list<string> */
    public function fields(): array
    {
        return $this->fields;
    }
}
