<?php

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

/**
 * Scope third-party references in the plugin source while keeping OpenCart and
 * MyParcel namespaces stable. Vendor dependencies are handled separately by
 * scoper.vendor.inc.php, matching the MyParcel WooCommerce/PrestaShop pattern.
 */
return [
    'prefix' => '_MyParcelNL',
    'php-version' => '8.2',

    'finders' => [
        Finder::create()->append([
            'bootstrap.php',
            'composer.json',
        ]),
        Finder::create()
            ->files()
            ->name('*.php')
            ->in(['admin', 'catalog', 'src']),
    ],

    'exclude-namespaces' => [
        '/^$/',
        'Composer',
        'MyParcelNL',
        'Opencart',
    ],
];
