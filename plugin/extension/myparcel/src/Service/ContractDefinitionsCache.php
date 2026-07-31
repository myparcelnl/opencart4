<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Service;

use MyParcelNL\OpenCart\Core\Dto\ContractDefinitions;
use MyParcelNL\OpenCart\Core\Settings\SettingKeys;

/**
 * Adapter around OpenCart's settings storage for the imported contract-definitions
 * blob (the capabilities-endpoint response). The caller passes in OpenCart's setting
 * model, so this class stays free of controller/Registry inheritance.
 */
final class ContractDefinitionsCache
{
    /**
     * Store the contract-definitions blob under its own settings key.
     *
     * @param object $settingModel OpenCart model_setting_setting.
     */
    public function store(object $settingModel, ContractDefinitions $definitions): void
    {
        $settingModel->editSetting(SettingKeys::CONTRACT_DEFINITIONS, [
            SettingKeys::CONTRACT_DEFINITIONS => $definitions->toArray(),
        ]);
    }

    /**
     * Record the latest import error while preserving the cached contract definitions.
     *
     * @param object $settingModel OpenCart model_setting_setting.
     */
    public function storeLastError(object $settingModel, string $reason): void
    {
        $definitions = $this->get($settingModel) ?? ContractDefinitions::empty();
        $this->store($settingModel, $definitions->withLastError($reason));
    }

    /**
     * Read the cached contract-definitions blob, or null when nothing is cached.
     *
     * @param object $settingModel OpenCart model_setting_setting.
     */
    public function get(object $settingModel): ?ContractDefinitions
    {
        $stored = $settingModel->getSetting(SettingKeys::CONTRACT_DEFINITIONS);
        $blob = $stored[SettingKeys::CONTRACT_DEFINITIONS] ?? null;

        return is_array($blob) && $blob !== [] ? ContractDefinitions::fromArray($blob) : null;
    }

    /**
     * Build the admin-page summary for the cached blob.
     *
     * @return array{
     *     carrier_count: int,
     *     fetched_at: int|null,
     *     environment: string|null,
     *     last_error: array{timestamp: int, reason: string}|null
     * }|null
     */
    public function summary(?ContractDefinitions $definitions): ?array
    {
        return $definitions?->summary();
    }
}
