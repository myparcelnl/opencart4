<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Support;

/** OpenCart compatibility contract shared by both installable extension types. */
final class OpenCartCompatibility
{
    /** OCMOD and the registered event signatures are available from this version. */
    public const MINIMUM_VERSION = '4.1.0.3';

    /** Static compatibility contract; not instantiable. */
    private function __construct()
    {
    }

    /**
     * Keep JSON and PDF responses free of PHP notice output. Dependencies emit
     * deprecations, and OpenCart's error handler echoes every handled error
     * into the response body when error_display is on (the default), which
     * corrupts the admin AJAX handling. Non-fatal noise goes to the PHP error
     * log instead; anything else follows the normal error route.
     */
    public static function guardJsonOutput(): void
    {
        ini_set('display_errors', '0');

        set_error_handler(static function (int $code, string $message, string $file, int $line): bool {
            $nonFatal = [E_DEPRECATED, E_USER_DEPRECATED, E_NOTICE, E_USER_NOTICE, E_WARNING, E_USER_WARNING];

            if (!in_array($code, $nonFatal, true)) {
                return false;
            }

            error_log(sprintf('[MyParcel] PHP %d: %s in %s on line %d', $code, $message, $file, $line));

            return true;
        });
    }
}
