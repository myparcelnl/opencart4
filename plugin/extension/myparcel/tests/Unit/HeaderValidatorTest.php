<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Tests\Unit;

use MyParcelNL\OpenCart\Core\Service\Http\HeaderValidator;
use PHPUnit\Framework\TestCase;

class HeaderValidatorTest extends TestCase
{
    public function testAcceptsAValidHeader(): void
    {
        self::assertTrue(HeaderValidator::isSafe('Content-Type', 'application/pdf'));
    }

    public function testRejectsInvalidHeaderNames(): void
    {
        self::assertFalse(HeaderValidator::isSafe('Content Type', 'application/pdf'));
    }

    public function testRejectsControlCharactersInValues(): void
    {
        self::assertFalse(HeaderValidator::isSafe('Location', "https://example.test\r\nX-Test: injected"));
    }
}
