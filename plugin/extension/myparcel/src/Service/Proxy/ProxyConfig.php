<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Service\Proxy;

/**
 * Static allowlist for the storefront proxy.
 */
final class ProxyConfig
{
    private const HOSTS = [
        'core' => [
            'url' => 'https://api.myparcel.nl',
            'acceptanceUrl' => 'https://api.acceptance.myparcel.nl',
            'paths' => [
                'shipments/capabilities',
            ],
        ],
    ];

    /**
     * Whether a host key is registered.
     */
    public function hasHost(string $host): bool
    {
        return isset(self::HOSTS[$host]);
    }

    /**
     * Return the production or acceptance base URL for a host key.
     */
    public function baseUrl(string $host, bool $acceptance): string
    {
        $entry = self::HOSTS[$host] ?? null;

        if (!is_array($entry)) {
            return '';
        }

        return (string) ($acceptance ? $entry['acceptanceUrl'] : $entry['url']);
    }

    /**
     * Whether the canonical path is explicitly allowlisted for the host.
     */
    public function isPathAllowed(string $host, string $path): bool
    {
        $entry = self::HOSTS[$host] ?? null;

        if (!is_array($entry)) {
            return false;
        }

        return in_array($this->canonicalPath($path), $entry['paths'] ?? [], true);
    }

    /**
     * Normalise a user-provided upstream path without allowing traversal.
     */
    public function canonicalPath(string $path): string
    {
        $path = trim(rawurldecode($path));
        $path = trim($path, '/');

        if ($path === '' || str_contains($path, '..') || str_contains($path, '\\')) {
            return '';
        }

        return preg_replace('#/+#', '/', $path) ?? '';
    }
}
