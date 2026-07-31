<?php

namespace Opencart\Catalog\Controller\Extension\Myparcel;

use MyParcelNL\OpenCart\Core\Enum\Environment;
use MyParcelNL\OpenCart\Core\Service\Http\HeaderValidator;
use MyParcelNL\OpenCart\Core\Service\Proxy\CorsPolicy;
use MyParcelNL\OpenCart\Core\Service\Proxy\ProxyClient;
use MyParcelNL\OpenCart\Core\Service\Proxy\ProxyResponse;
use MyParcelNL\OpenCart\Core\Settings\SettingKeys;

require_once DIR_EXTENSION . 'myparcel/bootstrap.php';

/**
 * Storefront entry point for the allow-listed MyParcel API proxy. Runs the CORS
 * lifecycle, then forwards the request upstream with the server-side API key.
 */
class Proxy extends \Opencart\System\Engine\Controller
{
    /**
     * Handle a proxy request: answer a CORS preflight, enforce the origin
     * allowlist, forward to the upstream API, and emit the response with
     * proxy-owned headers (OpenCart's global CORS headers are stripped first).
     */
    public function index(): void
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $headers = $this->collectRequestHeaders();
        $cors = new CorsPolicy($this->allowedOrigins());
        $origin = $cors->requestOrigin($headers);

        if ($cors->isPreflight($method)) {
            $this->emit($cors->preflightResponse($origin, $headers));
        }

        if (!$cors->isAllowedOrigin($origin)) {
            $this->emit($cors->forbiddenResponse());
        }

        $response = (new ProxyClient())->forward(
            (string) ($_GET['host'] ?? ''),
            Environment::isAcceptance($this->config->get(SettingKeys::ENVIRONMENT)),
            (string) ($_GET['path'] ?? ''),
            $method,
            $headers,
            $this->readRequestBody(),
            $this->upstreamQueryString((string) ($_SERVER['QUERY_STRING'] ?? '')),
            trim((string) $this->config->get(SettingKeys::API_KEY))
        );

        $this->emit($cors->apply($response, $origin));
    }

    /**
     * Read the request body capped just past the proxy limit, so ProxyClient
     * rejects an oversized body without copying the whole payload.
     */
    private function readRequestBody(): string
    {
        $stream = fopen('php://input', 'rb');

        if ($stream === false) {
            return '';
        }

        $body = (string) stream_get_contents($stream, ProxyClient::MAX_BODY_BYTES + 1);
        fclose($stream);

        return $body;
    }

    /**
     * Return the configured HTTP and HTTPS storefront origins.
     *
     * @return string[]
     */
    private function allowedOrigins(): array
    {
        return array_filter([
            (string) $this->config->get('config_url'),
            (string) $this->config->get('config_ssl'),
        ]);
    }

    /**
     * Collect request headers on SAPIs with and without getallheaders().
     *
     * @return array<string, string>
     */
    private function collectRequestHeaders(): array
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                return array_map('strval', $headers);
            }
        }

        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = (string) $value;
            }
        }

        foreach (['CONTENT_TYPE' => 'Content-Type', 'CONTENT_LENGTH' => 'Content-Length'] as $key => $name) {
            if (isset($_SERVER[$key])) {
                $headers[$name] = (string) $_SERVER[$key];
            }
        }

        return $headers;
    }

    /**
     * Drop OpenCart's internal routing params so only the caller's own query
     * params are forwarded upstream.
     */
    private function upstreamQueryString(string $queryString): string
    {
        if ($queryString === '') {
            return '';
        }

        $internal = ['route', '_route_', 'host', 'path', 'language', 'store_id'];
        $pairs = [];

        foreach (explode('&', $queryString) as $pair) {
            if ($pair === '') {
                continue;
            }

            $key = rawurldecode(explode('=', $pair, 2)[0]);
            if (in_array($key, $internal, true)) {
                continue;
            }

            $pairs[] = $pair;
        }

        return implode('&', $pairs);
    }

    /**
     * Send the response, first removing OpenCart's global CORS + Set-Cookie headers
     * so only the proxy's own origin-validated headers reach the browser.
     */
    private function emit(ProxyResponse $response): void
    {
        if (headers_sent()) {
            echo $response->body;
            exit;
        }

        header_remove('Access-Control-Allow-Origin');
        header_remove('Access-Control-Allow-Credentials');
        header_remove('Access-Control-Allow-Methods');
        header_remove('Access-Control-Allow-Headers');
        header_remove('Access-Control-Max-Age');
        header_remove('Set-Cookie');

        http_response_code($response->status);

        foreach ($response->headers as $name => $value) {
            if (!is_string($name) || !HeaderValidator::isSafe($name, $value)) {
                continue;
            }

            header($name . ': ' . $value, true);
        }

        echo $response->body;
        exit;
    }
}
