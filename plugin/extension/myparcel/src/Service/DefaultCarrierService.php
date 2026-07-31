<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Service;

use MyParcelNL\Sdk\Client\Generated\CoreApiPrivate\Api\ShippingRuleApi;
use MyParcelNL\Sdk\Client\Generated\CoreApiPrivate\ApiException;

/** Resolves the account's default carrier from its shipping-rule implications. */
final class DefaultCarrierService
{
    private ApiConfigFactory $configFactory;

    /** Allow callers to supply the shared SDK configuration factory. */
    public function __construct(?ApiConfigFactory $configFactory = null)
    {
        $this->configFactory = $configFactory ?? new ApiConfigFactory();
    }

    /**
     * Return the legacy carrier id implied by the shop's first shipping rule, or null when unavailable.
     */
    public function getDefaultCarrierId(string $apiKey, bool $acceptance, int $shopId): ?int
    {
        if ($shopId < 1) {
            return null;
        }

        try {
            $api = new ShippingRuleApi(null, $this->configFactory->forCorePrivate($apiKey, $acceptance));
            $response = $api->getShippingRuleImplications($shopId);
            $implications = $response->getData()->getImplications();

            if (empty($implications)) {
                return null;
            }

            $carrierId = $implications[0]->getCarrierId();

            return $carrierId !== null && (int) $carrierId > 0 ? (int) $carrierId : null;
        } catch (ApiException $e) {
            return null;
        } catch (\Throwable $e) {
            error_log(sprintf(
                '[MyParcel] Could not resolve the default carrier: %s: %s',
                $e::class,
                $e->getMessage()
            ));

            return null;
        }
    }

    /**
     * Prefer the API result, then a previous value, but only when currently contracted.
     *
     * @param array<int, array<string, mixed>> $contracts
     */
    public static function resolveAvailable(?string $resolved, ?string $previous, array $contracts): ?string
    {
        $available = [];

        foreach ($contracts as $contract) {
            $carrier = $contract['carrier'] ?? null;

            if (is_string($carrier) && $carrier !== '') {
                $available[] = $carrier;
            }
        }

        if ($resolved !== null && in_array($resolved, $available, true)) {
            return $resolved;
        }

        return $previous !== null && in_array($previous, $available, true) ? $previous : null;
    }
}
