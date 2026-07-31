<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use MyParcelNL\OpenCart\Core\Service\Carrier\CarrierCatalogService;
use PHPUnit\Framework\TestCase;

final class CarrierCatalogServiceTest extends TestCase
{
    public function testFetchesThePublicAcceptanceCatalogWithoutAuthorization(): void
    {
        $history = [];
        $body = json_encode([
            'data' => ['carriers' => [[
                'id' => 1,
                'name' => 'postnl',
                'human' => 'PostNL',
                'meta' => ['logo_svg' => '/skin/general-images/carrier-logos/svg/24/postnl.svg'],
            ]]],
        ], JSON_THROW_ON_ERROR);
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, ['Content-Type' => 'application/json; charset=utf-8'], $body),
        ]));
        $stack->push(Middleware::history($history));

        $catalog = (new CarrierCatalogService(null, new Client(['handler' => $stack])))->getCatalog(true);

        self::assertSame('postnl', $catalog->slugForId(1));
        self::assertCount(1, $history);
        $request = $history[0]['request'];
        self::assertSame('https://api.acceptance.myparcel.nl/carriers', (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertFalse($request->hasHeader('Authorization'));
    }

    public function testRejectsNonJsonResponses(): void
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(200, ['Content-Type' => 'text/html'], '<html></html>'),
        ]))]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Carrier catalog response is invalid.');

        (new CarrierCatalogService(null, $client))->getCatalog(false);
    }
}
