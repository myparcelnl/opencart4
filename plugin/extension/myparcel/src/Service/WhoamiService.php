<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Service;

use MyParcelNL\Sdk\Client\Generated\IamApi\Api\DefaultApi as IamApi;
use MyParcelNL\Sdk\Client\Generated\IamApi\Model\Principal;

/**
 * Calls the IAM /whoami endpoint to resolve the authenticated principal.
 *
 * Pure transport: callers handle the thrown IamApiException (HTTP 401 means the
 * key was rejected, any other code is a transport error). The SDK deserializes
 * into a FixedPrincipal; we type against the stable parent Principal so a future
 * SDK regeneration cannot break callers.
 *
 * Not final on purpose: tests subclass it and override getWhoami() to return a
 * synthetic principal without hitting the network.
 */
class WhoamiService
{
    private ApiConfigFactory $configFactory;

    /** Allow tests to replace the SDK configuration factory. */
    public function __construct(?ApiConfigFactory $configFactory = null)
    {
        $this->configFactory = $configFactory ?? new ApiConfigFactory();
    }

    /**
     * Fetch the principal belonging to an API key in the selected environment.
     *
     * @throws \MyParcelNL\Sdk\Client\Generated\IamApi\ApiException HTTP 401 = invalid key
     */
    public function getWhoami(string $apiKey, bool $acceptance): Principal
    {
        $iam = new IamApi(null, $this->configFactory->forIam($apiKey, $acceptance));

        return $iam->whoamiGet();
    }
}
