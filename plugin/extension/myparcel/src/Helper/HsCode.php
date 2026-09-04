<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Helper;

/**
 * Normalises HS/HTS customs codes. Codes are commonly written with separators
 * ("6109.10.0012"), while the shipment API validates plain digit strings of
 * 6, 8 or 10 characters (US-bound shipments require all 10). Normalising on
 * save and on export keeps formatted input from failing that validation.
 */
final class HsCode
{
    /** Strip separator characters (dots, spaces, dashes) from a customs code. */
    public static function normalize(string $code): string
    {
        return str_replace(['.', ' ', '-'], '', trim($code));
    }
}
