<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Service\Carrier;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use MyParcelNL\OpenCart\Core\Dto\CarrierCatalog;
use MyParcelNL\OpenCart\Core\Service\ApiConfigFactory;

/**
 * Temporary raw adapter for GET /carriers until the beta SDK generates it.
 *
 * The endpoint is public: deliberately do not attach the configured API key.
 */
final class CarrierCatalogService
{
    private const TIMEOUT_SECONDS = 5;

    private const MAX_RESPONSE_BYTES = 262144;

    private ApiConfigFactory $configFactory;

    private ClientInterface $client;

    /** Allow unit tests to supply a Guzzle-compatible client. */
    public function __construct(?ApiConfigFactory $configFactory = null, ?ClientInterface $client = null)
    {
        $this->configFactory = $configFactory ?? new ApiConfigFactory();
        $this->client = $client ?? new Client();
    }

    /**
     * Fetch and validate the public catalog for the selected Core API environment.
     *
     * @throws \RuntimeException When the upstream response cannot safely be used.
     */
    public function getCatalog(bool $acceptance): CarrierCatalog
    {
        $configuration = $this->configFactory->forCore('', $acceptance);
        $url = rtrim($configuration->getHost(), '/') . '/carriers';

        try {
            $response = $this->client->request('GET', $url, [
                RequestOptions::HEADERS => [
                    'Accept' => 'application/json',
                    'User-Agent' => $this->configFactory->userAgent(),
                ],
                RequestOptions::ALLOW_REDIRECTS => false,
                RequestOptions::HTTP_ERRORS => false,
                RequestOptions::TIMEOUT => self::TIMEOUT_SECONDS,
                RequestOptions::CONNECT_TIMEOUT => self::TIMEOUT_SECONDS,
            ]);
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Carrier catalog is unavailable.', 0, $e);
        }

        $contentType = strtolower(trim(explode(';', $response->getHeaderLine('Content-Type'))[0]));
        $body = (string) $response->getBody();

        if ($response->getStatusCode() !== 200 || $contentType !== 'application/json'
            || $body === '' || strlen($body) > self::MAX_RESPONSE_BYTES
        ) {
            throw new \RuntimeException('Carrier catalog response is invalid.');
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException $e) {
            throw new \RuntimeException('Carrier catalog response is invalid.', 0, $e);
        }

        if (!is_array($decoded)) {
            throw new \RuntimeException('Carrier catalog response is invalid.');
        }

        try {
            return CarrierCatalog::fromApiResponse($decoded);
        } catch (\UnexpectedValueException $e) {
            throw new \RuntimeException('Carrier catalog response is invalid.', 0, $e);
        }
    }
}
