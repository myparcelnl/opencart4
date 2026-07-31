<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Service\Proxy;

/**
 * Storefront CORS policy for the proxy.
 */
final class CorsPolicy
{
    private const PREFLIGHT_MAX_AGE_SECONDS = 600;

    /** @var string[] */
    private array $allowedOrigins;

    /**
     * Create a policy from the storefront origins configured by OpenCart.
     *
     * @param string[] $allowedOrigins
     */
    public function __construct(array $allowedOrigins)
    {
        $this->allowedOrigins = array_values(array_filter(array_map([$this, 'normaliseOrigin'], $allowedOrigins)));
    }

    /**
     * Whether the request method is a CORS preflight.
     */
    public function isPreflight(string $method): bool
    {
        return strtoupper($method) === 'OPTIONS';
    }

    /**
     * Extract the browser origin, with Referer as a legacy fallback.
     *
     * @param array<string, string> $headers
     */
    public function requestOrigin(array $headers): string
    {
        $normalised = $this->normaliseHeaderNames($headers);

        return $normalised['origin'] ?? $normalised['referer'] ?? '';
    }

    /**
     * Whether the origin matches the configured storefront URL exactly.
     */
    public function isAllowedOrigin(string $origin): bool
    {
        $origin = $this->normaliseOrigin($origin);

        return $origin !== '' && in_array($origin, $this->allowedOrigins, true);
    }

    /**
     * Apply CORS headers to an upstream response after origin validation.
     */
    public function apply(ProxyResponse $response, string $origin): ProxyResponse
    {
        // The controller rejects disallowed origins before forwarding; keep this guard here too.
        if (!$this->isAllowedOrigin($origin)) {
            return $this->forbiddenResponse();
        }

        $vary = $this->varyWithOrigin($response->headers);

        // Drop any upstream Vary key so the merged value below
        // is the only one emitted.
        $headers = array_filter(
            $response->headers,
            static fn ($name): bool => strtolower((string) $name) !== 'vary',
            ARRAY_FILTER_USE_KEY
        );

        return (new ProxyResponse($response->status, $headers, $response->body))->withHeaders([
            'Access-Control-Allow-Origin' => $this->normaliseOrigin($origin),
            'Vary' => $vary,
        ]);
    }

    /**
     * Append Origin to an upstream Vary header instead of replacing it.
     *
     * @param array<string, string> $headers
     */
    private function varyWithOrigin(array $headers): string
    {
        $members = [];

        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) === 'vary') {
                foreach (explode(',', (string) $value) as $member) {
                    $member = trim($member);

                    if ($member !== '') {
                        $members[] = $member;
                    }
                }
            }
        }

        $hasOrigin = in_array('origin', array_map('strtolower', $members), true);

        if (!$hasOrigin) {
            $members[] = 'Origin';
        }

        return implode(', ', $members);
    }

    /**
     * Build a CORS preflight response. A preflight only advertises what is allowed,
     * it never rejects: a disallowed origin gets a 204 without Allow-Origin, so the
     * browser blocks the real request. apply() still hard-rejects it server-side.
     *
     * @param array<string, string> $headers
     */
    public function preflightResponse(string $origin, array $headers): ProxyResponse
    {
        if (!$this->isAllowedOrigin($origin)) {
            return new ProxyResponse(204, ['Vary' => 'Origin'], '');
        }

        $normalised = $this->normaliseHeaderNames($headers);
        $allowedHeaders = $normalised['access-control-request-headers'] ?? 'Content-Type, Accept, Accept-Language';

        return new ProxyResponse(204, [
            'Access-Control-Allow-Origin' => $this->normaliseOrigin($origin),
            'Access-Control-Allow-Methods' => implode(', ', ProxyClient::ALLOWED_METHODS),
            'Access-Control-Allow-Headers' => $allowedHeaders,
            'Access-Control-Max-Age' => (string) self::PREFLIGHT_MAX_AGE_SECONDS,
            'Vary' => 'Origin, Access-Control-Request-Method, Access-Control-Request-Headers',
        ], '');
    }

    /**
     * Build a forbidden response without usable CORS headers.
     */
    public function forbiddenResponse(): ProxyResponse
    {
        return new ProxyResponse(
            403,
            ['Content-Type' => ProblemDetails::CONTENT_TYPE],
            ProblemDetails::fromStatus(403, 'origin not allowed')->toJsonString()
        );
    }

    /**
     * Normalise a URL to scheme://host[:port].
     */
    public function normaliseOrigin(string $url): string
    {
        $parts = parse_url(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));

        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        $defaultPort = $scheme === 'https' ? 443 : 80;

        return $scheme . '://' . $host . ($port !== null && $port !== $defaultPort ? ':' . $port : '');
    }

    /**
     * Build a case-insensitive header map for policy checks.
     *
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function normaliseHeaderNames(array $headers): array
    {
        $out = [];

        foreach ($headers as $name => $value) {
            $out[strtolower((string) $name)] = (string) $value;
        }

        return $out;
    }
}
