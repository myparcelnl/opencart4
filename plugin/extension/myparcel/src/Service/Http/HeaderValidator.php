<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Service\Http;

/** Validates HTTP header names and values before they are forwarded or emitted. */
final class HeaderValidator
{
    /** Reject invalid names and values containing control characters. */
    public static function isSafe(string $name, string $value): bool
    {
        return preg_match('/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/', $name) === 1
            && preg_match('/[\x00-\x1F\x7F]/', $value) !== 1;
    }
}
