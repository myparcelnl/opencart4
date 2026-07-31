<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Service\Shipment;

use Closure;

/** Formats API diagnostics without logging request payloads or credentials. */
final class ShipmentApiFailureLogger
{
    /** @var Closure(string): void */
    private Closure $write;

    /**
     * Configure the OpenCart log writer without coupling this service to Registry.
     *
     * @param callable(string): void $write
     */
    public function __construct(callable $write)
    {
        $this->write = Closure::fromCallable($write);
    }

    /** Log a failed SDK call with identifiers and sanitized API diagnostics. */
    public function log(string $action, int $orderId, ?int $shipmentId, \Throwable $exception): void
    {
        $details = ShipmentApiFailure::fromThrowable($exception)->logDetails();
        $fields = [
            'action' => $action,
            'order_id' => (string) $orderId,
            'shipment_id' => $shipmentId === null ? 'n/a' : (string) $shipmentId,
            'exception' => $details['exception'],
            'status' => $details['status'] === null ? 'n/a' : (string) $details['status'],
            'api_error_code' => $details['api_error_code'] ?? 'n/a',
            'api_error_message' => $details['api_error_message'],
        ];

        $parts = [];
        foreach ($fields as $key => $value) {
            $parts[] = $key . '=' . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        ($this->write)('MyParcel API failure ' . implode(' ', $parts));
    }

    /** Log a mapper fallback that contains only controlled, non-sensitive values. */
    public function logMapperFallback(int $orderId, string $diagnostic): void
    {
        ($this->write)(sprintf(
            'MyParcel shipment mapper fallback order_id=%s diagnostic=%s',
            json_encode((string) $orderId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($diagnostic, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ));
    }
}
