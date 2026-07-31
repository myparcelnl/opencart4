<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Settings;

/**
 * Keeps an update-safe snapshot of settings that OpenCart deletes when a
 * module or shipping extension is uninstalled. The conventional active groups
 * remain the runtime source; install restores them from this snapshot.
 */
final class PluginStateStore
{
    private object $settingModel;

    /**
     * Use OpenCart's setting model as the active and durable backing store.
     *
     * @param object $settingModel OpenCart model_setting_setting.
     */
    public function __construct(object $settingModel)
    {
        $this->settingModel = $settingModel;
    }

    /**
     * Save one complete active group and mirror it to the durable snapshot.
     *
     * @param array<string, mixed> $values
     */
    public function save(string $group, array $values): void
    {
        $this->assertSupportedGroup($group);

        $state = $this->state();
        $state['settings'][$group] = $values;

        // Persist the durable copy first. If the active-group write fails, a
        // later install can still restore the intended values.
        $this->writeState($state);
        $this->settingModel->editSetting($group, $values);
    }

    /**
     * Restore an active group without replacing saved values with new defaults.
     * Existing snapshots win over a legacy active group; missing keys receive
     * their current defaults.
     *
     * @param array<string, mixed> $defaults
     * @return array<string, mixed>
     */
    public function restore(string $group, array $defaults): array
    {
        $this->assertSupportedGroup($group);

        $active = $this->settingModel->getSetting($group);
        $snapshot = $this->state()['settings'][$group] ?? [];
        $values = array_replace(
            $defaults,
            array_intersect_key(is_array($active) ? $active : [], $defaults),
            array_intersect_key(is_array($snapshot) ? $snapshot : [], $defaults)
        );

        $this->save($group, $values);

        return $values;
    }

    /** Return the database-schema version recorded after the last successful migration. */
    public function schemaVersion(): int
    {
        return max(0, (int) $this->state()['schema_version']);
    }

    /** Record a schema version while preserving every settings snapshot. */
    public function setSchemaVersion(int $version): void
    {
        $state = $this->state();
        $state['schema_version'] = max(0, $version);
        $this->writeState($state);
    }

    /**
     * Read and normalise the serialized state stored through OpenCart's setting model.
     *
     * @return array{schema_version: int, settings: array<string, array<string, mixed>>}
     */
    private function state(): array
    {
        $stored = $this->settingModel->getSetting(SettingKeys::STATE);
        $state = is_array($stored[SettingKeys::STATE] ?? null) ? $stored[SettingKeys::STATE] : [];
        $rawSettings = is_array($state['settings'] ?? null) ? $state['settings'] : [];
        $settings = [];

        foreach ([SettingKeys::MODULE, SettingKeys::SHIPPING] as $group) {
            if (is_array($rawSettings[$group] ?? null)) {
                $settings[$group] = $rawSettings[$group];
            }
        }

        return [
            'schema_version' => max(0, (int) ($state['schema_version'] ?? 0)),
            'settings' => $settings,
        ];
    }

    /**
     * Persist the complete snapshot as one serialized OpenCart setting.
     *
     * @param array{schema_version: int, settings: array<string, array<string, mixed>>} $state
     */
    private function writeState(array $state): void
    {
        $this->settingModel->editSetting(SettingKeys::STATE, [
            SettingKeys::STATE => $state,
        ]);
    }

    /** Limit snapshots to the two active groups OpenCart removes automatically. */
    private function assertSupportedGroup(string $group): void
    {
        if (!in_array($group, [SettingKeys::MODULE, SettingKeys::SHIPPING], true)) {
            throw new \InvalidArgumentException("Unsupported MyParcel settings group: $group");
        }
    }
}
