<?php

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

$sourceConfig = require __DIR__ . '/scoper.inc.php';

/** Scope production dependencies, excluding development and documentation files. */
return array_replace($sourceConfig, [
    'finders' => [
        Finder::create()
            ->files()
            ->ignoreVCS(true)
            ->exclude([
                '.github',
                '.idea',
                '.openapi-generator',
                '.run',
                '.yarn',
                'doc',
                'docs',
                'example',
                'examples',
                'openapi',
                'test',
                'tests',
                'Tests',
                'vendor-bin',
            ])
            ->notName([
                '.php-cs-fixer*',
                '.releaserc*',
                '.yarnrc*',
                'CHANGELOG*',
                'CONTRIBUTING*',
                'phpstan*',
                'phpunit*',
                'psalm*',
                'UPGRADE*',
            ])
            ->in('vendor'),
    ],
]);
