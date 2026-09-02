<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use MyParcelNL\OpenCart\Core\Service\Proxy\ProxyClient;
use PHPUnit\Framework\TestCase;

final class ProxyResponseCachingTest extends TestCase
{
    public function testStripsUpstreamCacheHeadersAndForbidsBrowserCaching(): void
    {
        $upstream = new Response(200, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'public, max-age=3600',
            'Expires' => 'Thu, 01 Jan 2026 00:00:00 GMT',
            'ETag' => '"abc123"',
            'Last-Modified' => 'Wed, 31 Dec 2025 00:00:00 GMT',
            'Age' => '120',
            'Pragma' => 'cache',
        ], '{"data":{}}');

        $response = $this->client($upstream)->forward(
            'core',
            false,
            'shipments/capabilities',
            'POST',
            [],
            '{}',
            '',
            'test-api-key'
        );

        self::assertSame(200, $response->status);

        $names = array_map('strtolower', array_keys($response->headers));

        foreach (['expires', 'etag', 'last-modified', 'age', 'pragma'] as $dropped) {
            self::assertNotContains($dropped, $names, "Upstream $dropped header must not be forwarded.");
        }

        // The proxy serves account- and environment-dependent data, so the
        // browser must never reuse a response after those change.
        self::assertSame('no-store', $response->headers['Cache-Control'] ?? null);
        self::assertSame('application/json', $response->headers['Content-Type'] ?? null);
    }

    private function client(Response $upstream): ProxyClient
    {
        $handler = HandlerStack::create(new MockHandler([$upstream]));

        return new ProxyClient(null, new Client(['handler' => $handler]));
    }
}
