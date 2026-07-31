<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Service\Shipment;

/** Maps export request preconditions to the existing OpenCart language keys. */
final class ShipmentExportValidator
{
    /** Return the first language key blocking export, or null when it may proceed. */
    public function errorLanguageKey(bool $allowed, int $orderId, string $apiKey): ?string
    {
        if (!$allowed) {
            return 'error_permission';
        }

        if ($orderId <= 0) {
            return 'error_order_id';
        }

        return trim($apiKey) === '' ? 'text_api_key_invalid' : null;
    }
}
