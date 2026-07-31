<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Service\Shipment;

use InvalidArgumentException;

/** A customs validation failure the admin controller can translate for the merchant. */
final class CustomsDeclarationException extends InvalidArgumentException
{
    public const EMPTY_ITEMS = 'empty_items';
    public const INVALID_COUNTRY_OF_ORIGIN = 'invalid_country_of_origin';
    public const INVALID_QUANTITY = 'invalid_quantity';
    public const MISSING_COUNTRY_OF_ORIGIN = 'missing_country_of_origin';
    public const TOO_MANY_ITEMS = 'too_many_items';
    public const UNSUPPORTED_CURRENCY = 'unsupported_currency';

    private string $reason;

    private string $context;

    /** Keep the domain failure independent of OpenCart language keys. */
    private function __construct(string $reason, string $context = '')
    {
        parent::__construct($reason);

        $this->reason = $reason;
        $this->context = $context;
    }

    /** The non-EU order has no deliverable product lines. */
    public static function emptyItems(): self
    {
        return new self(self::EMPTY_ITEMS);
    }

    /** The resolved origin is not an ISO country known to the SDK. */
    public static function invalidCountryOfOrigin(string $description): self
    {
        return new self(self::INVALID_COUNTRY_OF_ORIGIN, $description);
    }

    /** The order line amount cannot be represented by the Core API model. */
    public static function invalidQuantity(string $description): self
    {
        return new self(self::INVALID_QUANTITY, $description);
    }

    /** Neither the product nor the configured/store fallback supplies an origin. */
    public static function missingCountryOfOrigin(string $description): self
    {
        return new self(self::MISSING_COUNTRY_OF_ORIGIN, $description);
    }

    /** The Core API accepts at most one hundred customs lines. */
    public static function tooManyItems(): self
    {
        return new self(self::TOO_MANY_ITEMS);
    }

    /** The generated customs money model currently accepts EUR values. */
    public static function unsupportedCurrency(string $currency): self
    {
        return new self(self::UNSUPPORTED_CURRENCY, $currency);
    }

    /** Stable reason code translated by the OpenCart adapter. */
    public function reason(): string
    {
        return $this->reason;
    }

    /** Product description or currency associated with the reason. */
    public function context(): string
    {
        return $this->context;
    }
}
