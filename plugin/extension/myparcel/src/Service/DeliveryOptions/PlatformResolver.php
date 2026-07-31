<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Service\DeliveryOptions;

use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\AccountDefsPlatformName as PlatformName;
use MyParcelNL\Sdk\Client\Generated\IamApi\Model\Platform as IamPlatform;

/**
 * Maps the IAM whoami platform to the slug the Delivery Options widget expects.
 * Both sides are SDK constants: the IAM enum as key, the CoreApi platform-name slug
 * as value (PDK documents AccountDefsPlatformName as the delivery-options platform id).
 */
final class PlatformResolver
{
    private const IAM_TO_WIDGET = [
        IamPlatform::MYPARCEL_NL => PlatformName::MYPARCEL,
        IamPlatform::MYPARCEL_BE => PlatformName::BELGIE,
        IamPlatform::MYPARCEL_IT => PlatformName::ITALY,
    ];

    /**
     * Resolve the stored IAM platform to a widget slug, defaulting to MyParcel NL
     * when the value is missing (pre-platform blob) or unknown.
     */
    public static function toWidget(?string $iamPlatform): string
    {
        return self::IAM_TO_WIDGET[$iamPlatform] ?? PlatformName::MYPARCEL;
    }
}
