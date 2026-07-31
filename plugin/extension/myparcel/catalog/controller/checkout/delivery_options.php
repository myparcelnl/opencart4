<?php

namespace Opencart\Catalog\Controller\Extension\Myparcel\Checkout;

use MyParcelNL\OpenCart\Core\Service\Address\StreetSplitter;

require_once DIR_EXTENSION . 'myparcel/bootstrap.php';

/**
 * Same-origin session endpoints the checkout adapter calls to read checkout state,
 * stash the chosen Delivery Options, or clear them. Guarded by a per-session token
 * plus a same-origin check.
 */
class DeliveryOptions extends \Opencart\System\Engine\Controller
{
    private const MAX_BODY_BYTES = 32768;
    private const SESSION_KEY = 'myparcel_delivery_options';
    private const SHIPPING_CODE = 'myparcel.myparcel';
    private const TOKEN_KEY = 'myparcel_delivery_options_token';

    /**
     * POST: return the active shipping-method code and the normalised shipping
     * address the widget needs to render. POST (token in the body) keeps the
     * session token out of access logs and browser history.
     */
    public function state(): void
    {
        if ($this->guardedBody() === null) {
            return;
        }

        $this->respond([
            'success' => true,
            'shipping_code' => $this->currentShippingCode(),
            'address' => $this->normalizedShippingAddress(),
        ]);
    }

    /**
     * POST: stash the widget's Delivery Options JSON in the session — only while
     * MyParcel is the active shipping method, otherwise the stash is dropped.
     */
    public function save(): void
    {
        $body = $this->guardedBody();

        if ($body === null) {
            return;
        }

        if ($this->currentShippingCode() !== self::SHIPPING_CODE) {
            unset($this->session->data[self::SESSION_KEY]);
            $this->respond(['success' => true, 'saved' => false]);
            return;
        }

        $deliveryOptions = $body['delivery_options'] ?? null;

        if (is_string($deliveryOptions)) {
            $deliveryOptions = json_decode($deliveryOptions, true);
        }

        if (!is_array($deliveryOptions) || $deliveryOptions === []) {
            unset($this->session->data[self::SESSION_KEY]);
            $this->respond(['success' => true, 'saved' => false]);
            return;
        }

        try {
            $this->session->data[self::SESSION_KEY] = json_encode(
                $deliveryOptions,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        } catch (\JsonException $e) {
            unset($this->session->data[self::SESSION_KEY]);
            $this->respond(['success' => false, 'error' => 'invalid_json'], 400);
            return;
        }

        $this->respond(['success' => true, 'saved' => true]);
    }

    /**
     * POST: drop any stashed Delivery Options from the session.
     */
    public function clear(): void
    {
        if ($this->guardedBody() === null) {
            return;
        }

        unset($this->session->data[self::SESSION_KEY]);
        $this->respond(['success' => true, 'cleared' => true]);
    }

    /**
     * Shared guard for the POST session endpoints: enforces POST, the body-size cap
     * and the per-session token + same-origin check. Returns the decoded JSON body,
     * or null after emitting the matching error response.
     *
     * @return array<string, mixed>|null
     */
    protected function guardedBody(): ?array
    {
        if (!$this->isPost()) {
            $this->respond(['success' => false, 'error' => 'method_not_allowed'], 405);
            return null;
        }

        try {
            $rawBody = $this->readLimitedBody();
        } catch (\LengthException $e) {
            $this->respond(['success' => false, 'error' => 'payload_too_large'], 413);
            return null;
        }

        $body = json_decode($rawBody, true);

        if (!is_array($body) || !$this->isAllowedRequest($body['token'] ?? '')) {
            $this->respond(['success' => false, 'error' => 'forbidden'], 403);
            return null;
        }

        return $body;
    }

    /** Check whether the current request uses POST. */
    protected function isPost(): bool
    {
        return strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
    }

    /**
     * Read the request body, capped at MAX_BODY_BYTES.
     *
     * @throws \LengthException when the body exceeds the configured limit.
     */
    protected function readLimitedBody(): string
    {
        $stream = fopen('php://input', 'rb');

        if ($stream === false) {
            return '';
        }

        $body = (string)stream_get_contents($stream, self::MAX_BODY_BYTES + 1);
        fclose($stream);

        if (strlen($body) > self::MAX_BODY_BYTES) {
            throw new \LengthException('Request body exceeds the allowed size.');
        }

        return $body;
    }

    /**
     * Validate the session token and same-origin request metadata.
     *
     * @param mixed $token Untrusted token value from the decoded request body.
     */
    protected function isAllowedRequest(mixed $token): bool
    {
        $sessionToken = (string)($this->session->data[self::TOKEN_KEY] ?? '');

        if ($sessionToken === '' || !is_string($token) || !hash_equals($sessionToken, $token)) {
            return false;
        }

        return $this->hasAllowedOriginOrReferer();
    }

    /** Verify same-origin using Fetch Metadata with Origin/Referer fallbacks. */
    private function hasAllowedOriginOrReferer(): bool
    {
        $headers = $this->headers();

        // Sec-Fetch-Site is sent by modern browsers on every request and, unlike
        // Referer, is not suppressed by Referrer-Policy. Use it as the primary
        // same-origin signal so the address-returning state route is not guarded
        // by Referer alone.
        $fetchSite = strtolower(trim($headers['sec-fetch-site'] ?? ''));

        if ($fetchSite !== '') {
            return $fetchSite === 'same-origin';
        }

        // Older clients without Sec-Fetch metadata: fall back to Origin, then Referer.
        $origin = $headers['origin'] ?? '';

        if ($origin !== '') {
            return in_array($this->normalizeOrigin($origin), $this->allowedOrigins(), true);
        }

        $referer = $headers['referer'] ?? '';

        if ($referer !== '') {
            return in_array($this->normalizeOrigin($referer), $this->allowedOrigins(), true);
        }

        // No origin signal at all: fail closed. The session token is still required
        // and the response carries no CORS headers, so this only rejects edge cases.
        return false;
    }

    /**
     * Collect the request headers in a lowercase-name map.
     *
     * @return array<string, string>
     */
    private function headers(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = (string)$value;
            }
        }

        return $headers;
    }

    /**
     * Build the configured store origins accepted by the session endpoints.
     *
     * @return list<string>
     */
    private function allowedOrigins(): array
    {
        $origins = [];

        foreach ([$this->config->get('config_url'), $this->config->get('config_ssl')] as $url) {
            $origin = $this->normalizeOrigin((string)$url);

            if ($origin !== '') {
                $origins[] = $origin;
            }
        }

        return array_values(array_unique($origins));
    }

    /** Reduce a URL to its scheme, host and optional non-default port. */
    private function normalizeOrigin(string $url): string
    {
        $parts = parse_url(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));

        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        $scheme = strtolower((string)$parts['scheme']);
        $host = strtolower((string)$parts['host']);
        $port = isset($parts['port']) ? (int)$parts['port'] : null;
        $defaultPort = $scheme === 'https' ? 443 : 80;

        return $scheme . '://' . $host . ($port !== null && $port !== $defaultPort ? ':' . $port : '');
    }

    /** Return the shipping method currently selected in the checkout session. */
    private function currentShippingCode(): string
    {
        return (string)($this->session->data['shipping_method']['code'] ?? '');
    }

    /**
     * Normalise the session shipping address for the widget, or null when incomplete.
     *
     * @return array{
     *     cc: string,
     *     postalCode: string,
     *     city: string,
     *     street: string,
     *     number?: string
     * }|null
     */
    private function normalizedShippingAddress(): ?array
    {
        $address = $this->session->data['shipping_address'] ?? null;

        if (!is_array($address) || $address === []) {
            return null;
        }

        $cc = strtoupper(trim((string)($address['iso_code_2'] ?? '')));

        if ($cc === '' && !empty($address['country_id'])) {
            $this->load->model('localisation/country');
            $country = $this->model_localisation_country->getCountry((int)$address['country_id']);
            $cc = strtoupper(trim((string)($country['iso_code_2'] ?? '')));
        }

        $street = trim(html_entity_decode((string)($address['address_1'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $postalCode = trim(html_entity_decode((string)($address['postcode'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $city = trim(html_entity_decode((string)($address['city'] ?? ''), ENT_QUOTES, 'UTF-8'));

        if ($cc === '' || $street === '' || $postalCode === '' || $city === '') {
            return null;
        }

        $normalized = [
            'cc' => $cc,
            'postalCode' => $postalCode,
            'city' => $city,
            'street' => $street,
        ];

        $houseNumber = (new StreetSplitter())->houseNumber($street);

        if ($houseNumber !== null) {
            $normalized['number'] = $houseNumber;
        }

        return $normalized;
    }

    /**
     * Emit a JSON response without OpenCart's permissive global CORS headers.
     *
     * @param array<string, mixed> $payload
     */
    protected function respond(array $payload, int $status = 200): void
    {
        if (!headers_sent()) {
            // OpenCart sets permissive global CORS headers (Allow-Origin: * + credentials)
            // in system/framework.php; strip them so this session endpoint stays same-origin.
            // Set-Cookie is kept here, unlike the proxy, because these run in the storefront session.
            header_remove('Access-Control-Allow-Origin');
            header_remove('Access-Control-Allow-Credentials');
            header_remove('Access-Control-Allow-Methods');
            header_remove('Access-Control-Allow-Headers');
            header_remove('Access-Control-Max-Age');

            http_response_code($status);
            header('Content-Type: application/json', true);
        }

        echo json_encode($payload);
        exit;
    }
}
