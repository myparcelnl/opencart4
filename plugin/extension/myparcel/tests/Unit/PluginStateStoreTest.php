<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Tests\Unit;

use MyParcelNL\OpenCart\Core\Settings\PluginStateStore;
use MyParcelNL\OpenCart\Core\Settings\SchemaMigrator;
use MyParcelNL\OpenCart\Core\Settings\SettingKeys;
use PHPUnit\Framework\TestCase;

final class PluginStateStoreTest extends TestCase
{
    public function testRestorePreservesExistingValuesAndAddsMissingDefaults(): void
    {
        $model = new FakeSettingModel([
            SettingKeys::MODULE => [
                SettingKeys::API_KEY => 'saved-key',
                SettingKeys::STATUS => 1,
            ],
        ]);
        $store = new PluginStateStore($model);

        $restored = $store->restore(SettingKeys::MODULE, [
            SettingKeys::API_KEY => '',
            SettingKeys::STATUS => 0,
            SettingKeys::ENVIRONMENT => 'production',
        ]);

        self::assertSame('saved-key', $restored[SettingKeys::API_KEY]);
        self::assertSame(1, $restored[SettingKeys::STATUS]);
        self::assertSame('production', $restored[SettingKeys::ENVIRONMENT]);
        self::assertSame($restored, $model->getSetting(SettingKeys::MODULE));
    }

    public function testSnapshotsRestoreBothGroupsAfterCoreUninstall(): void
    {
        $model = new FakeSettingModel();
        $store = new PluginStateStore($model);
        $module = [SettingKeys::API_KEY => 'durable-key', SettingKeys::STATUS => 1];
        $shipping = [SettingKeys::SHIPPING_RATE => 9.95, SettingKeys::SHIPPING_STATUS => 1];

        $store->save(SettingKeys::MODULE, $module);
        $store->save(SettingKeys::SHIPPING, $shipping);
        $model->deleteSetting(SettingKeys::MODULE);
        $model->deleteSetting(SettingKeys::SHIPPING);

        self::assertSame($module, $store->restore(SettingKeys::MODULE, [
            SettingKeys::API_KEY => '',
            SettingKeys::STATUS => 0,
        ]));
        self::assertSame($shipping, $store->restore(SettingKeys::SHIPPING, [
            SettingKeys::SHIPPING_RATE => 6.95,
            SettingKeys::SHIPPING_STATUS => 0,
        ]));
    }

    public function testFailedMigrationDoesNotAdvanceVersion(): void
    {
        $store = new PluginStateStore(new FakeSettingModel());
        $migrator = new SchemaMigrator($store);

        try {
            $migrator->migrate([1 => static function (): void {
                throw new \RuntimeException('migration failed');
            }]);
            self::fail('The migration should have failed.');
        } catch (\RuntimeException $e) {
            self::assertSame('migration failed', $e->getMessage());
        }

        self::assertSame(0, $store->schemaVersion());
    }

    public function testSuccessfulMigrationAdvancesOnce(): void
    {
        $store = new PluginStateStore(new FakeSettingModel());
        $migrator = new SchemaMigrator($store);
        $runs = 0;
        $migrations = [1 => static function () use (&$runs): void {
            $runs++;
        }];

        $migrator->migrate($migrations);
        $migrator->migrate($migrations);

        self::assertSame(1, $runs);
        self::assertSame(SchemaMigrator::CURRENT_VERSION, $store->schemaVersion());
    }
}

/** Minimal OpenCart setting-model double, including its key-prefix rule. */
final class FakeSettingModel
{
    /** @var array<string, array<string, mixed>> */
    private array $groups;

    /**
     * Seed the fake model with complete setting groups.
     *
     * @param array<string, array<string, mixed>> $groups
     */
    public function __construct(array $groups = [])
    {
        $this->groups = $groups;
    }

    /**
     * Return one fake setting group.
     *
     * @return array<string, mixed>
     */
    public function getSetting(string $code): array
    {
        return $this->groups[$code] ?? [];
    }

    /**
     * Replace a group using the same key-prefix filter as OpenCart 4.1.
     *
     * @param array<string, mixed> $values
     */
    public function editSetting(string $code, array $values): void
    {
        $this->groups[$code] = array_filter(
            $values,
            static fn (string $key): bool => str_starts_with($key, $code),
            ARRAY_FILTER_USE_KEY
        );
    }

    public function deleteSetting(string $code): void
    {
        unset($this->groups[$code]);
    }
}
