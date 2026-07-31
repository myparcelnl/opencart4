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
}
