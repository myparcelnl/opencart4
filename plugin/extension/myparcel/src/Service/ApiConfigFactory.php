<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Service;

use MyParcelNL\Sdk\Client\Generated\CoreApi\Configuration as CoreConfiguration;
use MyParcelNL\Sdk\Client\Generated\CoreApiPrivate\Configuration as CorePrivateConfiguration;
use MyParcelNL\Sdk\Client\Generated\IamApi\Configuration as IamConfiguration;

/**
 * Builds MyParcel SDK Configuration objects from an explicit API key and
 * environment. Production hosts are the SDK defaults; only acceptance is
 * overridden here.
 */
final class ApiConfigFactory
{
    private const USER_AGENT_PREFIX = 'MyParcel-OC4/1.0.0 PHP/';

    private const IAM_HOST_ACCEPTANCE = 'https://iam.api.acceptance.myparcel.nl';

    private const CORE_HOST_ACCEPTANCE = 'https://api.acceptance.myparcel.nl';

    /** User agent passed to both the SDK config and the *_user_agent call arguments. */
    public function userAgent(): string
    {
        return self::USER_AGENT_PREFIX . PHP_VERSION;
    }

    /**
     * IAM API configuration, used for API key validation (whoami).
     */
    public function forIam(string $apiKey, bool $acceptance): IamConfiguration
    {
        $config = new IamConfiguration();
        $config->setAccessToken(base64_encode($apiKey));
        $config->setUserAgent($this->userAgent());

        if ($acceptance) {
            $config->setHost(self::IAM_HOST_ACCEPTANCE);
        }

        return $config;
    }

    /**
     * Core API configuration, used for shipments and capabilities.
     */
    public function forCore(string $apiKey, bool $acceptance): CoreConfiguration
    {
        $config = new CoreConfiguration();
        $config->setAccessToken(base64_encode($apiKey));
        $config->setUserAgent($this->userAgent());

        if ($acceptance) {
            $config->setHost(self::CORE_HOST_ACCEPTANCE);
        }

        return $config;
    }

    /**
     * Private Core API configuration, used for account shipping-rule implications.
     */
    public function forCorePrivate(string $apiKey, bool $acceptance): CorePrivateConfiguration
    {
        $config = new CorePrivateConfiguration();
        $config->setAccessToken(base64_encode($apiKey));
        $config->setUserAgent($this->userAgent());

        if ($acceptance) {
            $config->setHost(self::CORE_HOST_ACCEPTANCE);
        }

        return $config;
    }

    /**
     * Core API host for the chosen environment, or null for production (the SDK default).
     * For services that take a host string rather than a Configuration (e.g. ShipmentCreateService).
     */
    public function coreHost(bool $acceptance): ?string
    {
        return $acceptance ? self::CORE_HOST_ACCEPTANCE : null;
    }
}
