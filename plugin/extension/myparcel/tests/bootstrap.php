<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/Support/OpenCartStubs.php';

if (!defined('DB_PREFIX')) {
    define('DB_PREFIX', 'oc_');
}

if (!defined('DIR_EXTENSION')) {
    define('DIR_EXTENSION', dirname(__DIR__, 2) . '/');
}
