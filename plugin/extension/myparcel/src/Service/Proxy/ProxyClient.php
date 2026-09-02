<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Service\Proxy;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use MyParcelNL\OpenCart\Core\Service\ApiConfigFactory;
use MyParcelNL\OpenCart\Core\Service\Http\HeaderValidator;

/**
 * Security choke point for the storefront API proxy.
 */
final class ProxyClient
{
    public const MAX_BODY_BYTES = 32768;
    private const TIMEOUT_SECONDS = 5;

    public const ALLOWED_METHODS = ['GET', 'POST', 'HEAD', 'OPTIONS'];

    private const REQUEST_HEADERS_DROP = [
        'host',
        'content-length',
        'connection',
        'transfer-encoding',
        'upgrade',
        'accept-encoding',
        'authorization',
        'cookie',
    ];

    private const RESPONSE_HEADERS_DROP = [
        'transfer-encoding',
        'connection',
        'keep-alive',
        'set-cookie',
        'content-encoding',
        'content-length',
        // Upstream CORS is irrelevant to the browser; the proxy sets its own (CorsPolicy).
        'access-control-allow-origin',
        'access-control-allow-methods',
        'access-control-allow-headers',
        'access-control-expose-headers',
        'access-control-allow-credentials',
        'access-control-max-age',
        // The proxy serves account- and address-dependent data; upstream cache
        // headers would let the browser reuse a response after those change
        // (e.g. delivery options surviving a checkout country switch).
        'cache-control',
        'expires',
        'etag',
        'last-modified',
        'age',
        'pragma',
    ];

    private ProxyConfig $config;
    private Client $client;

    /** Allow tests to replace the proxy configuration and HTTP client. */
    public function __construct(?ProxyConfig $config = null, ?Client $client = null)
    {
        $this->config = $config ?? new ProxyConfig();
        $this->client = $client ?? new Client();
    }

    /**
     * Forward an allowlisted request to MyParcel with the server-side API key.
     *
     * @param array<string, string> $requestHeaders
     */
    public function forward(
        string $host,
        bool $acceptance,
        string $path,
        string $method,
        array $requestHeaders,
        string $requestBody,
        string $queryString,
        string $apiKey
    ): ProxyResponse {
        $method = strtoupper($method);
        $path = $this->config->canonicalPath($path);

        if (!in_array($method, self::ALLOWED_METHODS, true)) {
            return $this->problem(405, 'method not allowed', ['Allow' => implode(', ', self::ALLOWED_METHODS)]);
        }

        if (!$this->config->hasHost($host) || !$this->config->isPathAllowed($host, $path)) {
            return $this->problem(403, 'path not allowed');
        }

        if (strlen($requestBody) > self::MAX_BODY_BYTES) {
            return $this->problem(413, 'request body too large');
        }

        if ($apiKey === '') {
            return $this->problem(403, 'api key not configured');
        }

        $url = rtrim($this->config->baseUrl($host, $acceptance), '/') . '/' . $path;

        if ($queryString !== '') {
            $url .= '?' . $queryString;
        }

        $options = [
            RequestOptions::HEADERS => $this->outgoingHeaders($requestHeaders, $apiKey),
            RequestOptions::ALLOW_REDIRECTS => false,
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::TIMEOUT => self::TIMEOUT_SECONDS,
            RequestOptions::CONNECT_TIMEOUT => self::TIMEOUT_SECONDS,
            RequestOptions::DECODE_CONTENT => true,
        ];

        if ($requestBody !== '' && !in_array($method, ['GET', 'HEAD'], true)) {
            $options[RequestOptions::BODY] = $requestBody;
        }

        try {
            $response = $this->client->request($method, $url, $options);
        } catch (GuzzleException $e) {
            return $this->problem(502, 'upstream unreachable');
        }

        return new ProxyResponse(
            $response->getStatusCode(),
            $this->responseHeaders($response->getHeaders()),
            (string) $response->getBody()
        );
    }

    /**
     * Keep safe caller headers and add the server-owned authentication headers.
     *
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function outgoingHeaders(array $headers, string $apiKey): array
    {
        $out = [];

        foreach ($headers as $name => $value) {
            $normalised = strtolower((string) $name);

            if (in_array($normalised, self::REQUEST_HEADERS_DROP, true)) {
                continue;
            }

            if (HeaderValidator::isSafe((string) $name, (string) $value)) {
                $out[(string) $name] = (string) $value;
            }
        }

        $out['Authorization'] = 'Bearer ' . base64_encode($apiKey);
        $out['User-Agent'] = (new ApiConfigFactory())->userAgent();

        return $out;
    }

    /**
     * Flatten safe upstream headers for OpenCart's response object.
     *
     * @param array<string, string[]> $headers
     * @return array<string, string>
     */
    private function responseHeaders(array $headers): array
    {
        $out = [];

        foreach ($headers as $name => $values) {
            $normalised = strtolower((string) $name);
            $value = implode(', ', array_map('strval', $values));

            if (in_array($normalised, self::RESPONSE_HEADERS_DROP, true)) {
                continue;
            }

            if (HeaderValidator::isSafe((string) $name, $value)) {
                $out[(string) $name] = $value;
            }
        }

        $out['Cache-Control'] = 'no-store';

        return $out;
    }

    /**
     * Build a local proxy rejection in problem-details format.
     *
     * @param array<string, string> $headers
     */
    private function problem(int $status, string $detail, array $headers = []): ProxyResponse
    {
        return new ProxyResponse(
            $status,
            array_merge(['Content-Type' => ProblemDetails::CONTENT_TYPE], $headers),
            ProblemDetails::fromStatus($status, $detail)->toJsonString()
        );
    }
}
