<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Settings;

/**
 * Runs numbered, idempotent schema migrations and advances the durable version
 * only after every pending migration completes successfully.
 */
final class SchemaMigrator
{
    public const CURRENT_VERSION = 1;

    private PluginStateStore $state;

    /** Persist migration progress through the durable plugin state. */
    public function __construct(PluginStateStore $state)
    {
        $this->state = $state;
    }

    /**
     * Run pending migrations in consecutive version order.
     *
     * @param array<int, callable(): void> $migrations Version-indexed migrations.
     */
    public function migrate(array $migrations): void
    {
        $current = $this->state->schemaVersion();

        if ($current >= self::CURRENT_VERSION) {
            return;
        }

        for ($version = $current + 1; $version <= self::CURRENT_VERSION; $version++) {
            if (!isset($migrations[$version])) {
                throw new \LogicException("Missing MyParcel schema migration $version");
            }

            $migrations[$version]();
        }

        $this->state->setSchemaVersion(self::CURRENT_VERSION);
    }
}
