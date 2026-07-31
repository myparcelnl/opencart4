<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Enum;

/** MyParcel API environments supported by the plugin settings. */
final class Environment
{
    public const PRODUCTION = 'production';

    public const ACCEPTANCE = 'acceptance';

    /** Unknown stored values must never switch a shop away from production. */
    public static function normalize(mixed $value): string
    {
        return $value === self::ACCEPTANCE ? self::ACCEPTANCE : self::PRODUCTION;
    }

    /** Convert a stored environment value to the SDK services' acceptance flag. */
    public static function isAcceptance(mixed $value): bool
    {
        return self::normalize($value) === self::ACCEPTANCE;
    }

    /** Static environment vocabulary; not instantiable. */
    private function __construct()
    {
    }
}
