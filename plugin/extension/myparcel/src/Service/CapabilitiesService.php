<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Service;

use MyParcelNL\Sdk\Client\Generated\CoreApi\Api\ShipmentApi;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\CapabilitiesPostContractDefinitionsRequestV2;

/**
 * Fetches contract definitions (the carriers + options an account may use) from
 * the Core API.
 *
 * Thin transport, like WhoamiService: builds the SDK client and returns its own
 * models untouched. The SDK already requests Accept version=2, so we don't add
 * any Accept-header middleware.
 *
 * Not final on purpose: tests subclass it and override getContractDefinitions()
 * to return synthetic items without hitting the network.
 */
class CapabilitiesService
{
    private ApiConfigFactory $configFactory;

    /** Allow tests to replace the SDK configuration factory. */
    public function __construct(?ApiConfigFactory $configFactory = null)
    {
        $this->configFactory = $configFactory ?? new ApiConfigFactory();
    }

    /**
     * One call, all carriers on the account (no setCarrier()).
     *
     * @return \MyParcelNL\Sdk\Client\Generated\CoreApi\Model\RefCapabilitiesContractDefinitionsResponseContractDefinitionsV2[]
     * @throws \MyParcelNL\Sdk\Client\Generated\CoreApi\ApiException
     */
    public function getContractDefinitions(string $apiKey, bool $acceptance): array
    {
        $api = new ShipmentApi(null, $this->configFactory->forCore($apiKey, $acceptance));

        // The user-agent is set on the Configuration (like WhoamiService), so only the request
        // is passed here — no method-level user_agent arg whose position the generator may shift.
        $response = $api->postCapabilitiesContractDefinitions(new CapabilitiesPostContractDefinitionsRequestV2());

        return $response->getItems();
    }
}
