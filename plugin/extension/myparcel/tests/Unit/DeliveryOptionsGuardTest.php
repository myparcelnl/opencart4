<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Tests\Unit;

use LengthException;
use Opencart\Catalog\Controller\Extension\Myparcel\Checkout\DeliveryOptions;
use Opencart\System\Engine\Registry;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/catalog/controller/checkout/delivery_options.php';

class DeliveryOptionsGuardTest extends TestCase
{
    public function testRejectsNonPostRequests(): void
    {
        $controller = new TestableDeliveryOptions(new Registry());
        $controller->post = false;

        self::assertNull($controller->guard());
        self::assertSame(405, $controller->responseStatus);
        self::assertSame('method_not_allowed', $controller->responseBody['error']);
    }

    public function testRejectsOversizedRequests(): void
    {
        $controller = new TestableDeliveryOptions(new Registry());
        $controller->tooLarge = true;

        self::assertNull($controller->guard());
        self::assertSame(413, $controller->responseStatus);
        self::assertSame('payload_too_large', $controller->responseBody['error']);
    }

    public function testRejectsInvalidTokenOrOrigin(): void
    {
        $controller = new TestableDeliveryOptions(new Registry());
        $controller->allowed = false;

        self::assertNull($controller->guard());
        self::assertSame(403, $controller->responseStatus);
        self::assertSame('forbidden', $controller->responseBody['error']);
    }

    public function testReturnsDecodedBodyForAllowedPost(): void
    {
        $controller = new TestableDeliveryOptions(new Registry());

        self::assertSame(['token' => 'valid'], $controller->guard());
        self::assertNull($controller->responseStatus);
    }
}

final class TestableDeliveryOptions extends DeliveryOptions
{
    public bool $post = true;

    public bool $tooLarge = false;

    public bool $allowed = true;

    public ?int $responseStatus = null;

    /** @var array<string, mixed>|null */
    public ?array $responseBody = null;

    public function guard(): ?array
    {
        return $this->guardedBody();
    }

    protected function isPost(): bool
    {
        return $this->post;
    }

    protected function readLimitedBody(): string
    {
        if ($this->tooLarge) {
            throw new LengthException('too large');
        }

        return '{"token":"valid"}';
    }

    protected function isAllowedRequest(mixed $token): bool
    {
        return $this->allowed && $token === 'valid';
    }

    protected function respond(array $payload, int $status = 200): void
    {
        $this->responseBody = $payload;
        $this->responseStatus = $status;
    }
}
