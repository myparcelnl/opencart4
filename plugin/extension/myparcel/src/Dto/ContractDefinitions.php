<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Dto;

/** Typed view of the contract definitions stored in OpenCart settings. */
final class ContractDefinitions
{
    /** Cache schema that includes public carrier metadata. */
    public const SCHEMA_VERSION = 4;

    /**
     * Create the typed cache value after its raw data has been normalised.
     *
     * @param array<int, array<string, mixed>> $contracts
     * @param array{timestamp: int, reason: string}|null $lastError
     */
    private function __construct(
        public int $schemaVersion,
        public ?string $environment,
        public ?string $accountId,
        public ?int $shopId,
        public ?string $platform,
        public ?string $defaultCarrier,
        public array $contracts,
        public CarrierCatalog $carrierCatalog,
        public ?int $fetchedAt,
        public ?array $lastError
    ) {
    }

    /**
     * Build the typed value from fresh SDK data or OpenCart's stored array.
     *
     * @param array<string, mixed> $raw
     */
    public static function fromArray(array $raw): self
    {
        $raw = self::normalise($raw);
        $contracts = $raw['contract_definitions'] ?? [];
        $lastError = $raw['last_error'] ?? null;

        return new self(
            schemaVersion: (int) ($raw['schema_version'] ?? 0),
            environment: self::nullableString($raw['environment'] ?? null),
            accountId: self::nullableString($raw['account_id'] ?? null),
            shopId: isset($raw['shop_id']) && (int) $raw['shop_id'] > 0 ? (int) $raw['shop_id'] : null,
            platform: self::nullableString($raw['platform'] ?? null),
            defaultCarrier: self::nullableString($raw['default_carrier'] ?? null),
            contracts: is_array($contracts) ? array_values(array_filter($contracts, 'is_array')) : [],
            carrierCatalog: CarrierCatalog::fromCache($raw['carrier_catalog'] ?? null),
            fetchedAt: isset($raw['fetched_at']) ? (int) $raw['fetched_at'] : null,
            lastError: is_array($lastError) ? [
                'timestamp' => (int) ($lastError['timestamp'] ?? 0),
                'reason' => (string) ($lastError['reason'] ?? ''),
            ] : null,
        );
    }

    /** Empty cache value used when the first import attempt fails. */
    public static function empty(): self
    {
        return self::fromArray([]);
    }

    /** Return a copy with the latest import error. */
    public function withLastError(string $reason, ?int $timestamp = null): self
    {
        $data = $this->toArray();
        $data['last_error'] = [
            'timestamp' => $timestamp ?? time(),
            'reason' => $reason,
        ];

        return self::fromArray($data);
    }

    /** Return a copy with the carrier resolved from the account's shipping rules. */
    public function withDefaultCarrier(?string $carrier): self
    {
        $data = $this->toArray();
        $data['default_carrier'] = $carrier;

        return self::fromArray($data);
    }

    /**
     * Convert to the plain array OpenCart settings can store.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'environment' => $this->environment,
            'account_id' => $this->accountId,
            'shop_id' => $this->shopId,
            'platform' => $this->platform,
            'default_carrier' => $this->defaultCarrier,
            'contract_definitions' => $this->contracts,
            'carrier_catalog' => $this->carrierCatalog->toArray(),
            'fetched_at' => $this->fetchedAt,
            'last_error' => $this->lastError,
        ];
    }

    /**
     * Small admin-page summary without exposing the full cached payload.
     *
     * @return array{
     *     carrier_count: int,
     *     fetched_at: int|null,
     *     environment: string|null,
     *     last_error: array{timestamp: int, reason: string}|null
     * }
     */
    public function summary(): array
    {
        return [
            'carrier_count' => count($this->contracts),
            'fetched_at' => $this->fetchedAt,
            'environment' => $this->environment,
            'last_error' => $this->lastError,
        ];
    }

    /**
     * Convert SDK JsonSerializable objects to the stored array shape.
     *
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private static function normalise(array $raw): array
    {
        $json = json_encode($raw);
        $decoded = is_string($json) ? json_decode($json, true) : null;

        return is_array($decoded) ? $decoded : $raw;
    }

    /**
     * Normalise an optional scalar to a non-empty string.
     *
     * @param mixed $value Value read from SDK or OpenCart storage.
     */
    private static function nullableString(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
