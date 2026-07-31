<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Tests\Unit;

use MyParcelNL\OpenCart\Core\Service\Shipment\ShipmentExportValidator;
use PHPUnit\Framework\TestCase;

final class ShipmentExportValidatorTest extends TestCase
{
    public function testReturnsTheExistingLanguageKeyForTheFirstInvalidPrecondition(): void
    {
        $validator = new ShipmentExportValidator();

        self::assertSame('error_permission', $validator->errorLanguageKey(false, 0, ''));
        self::assertSame('error_order_id', $validator->errorLanguageKey(true, 0, 'key'));
        self::assertSame('text_api_key_invalid', $validator->errorLanguageKey(true, 42, '  '));
    }

    public function testAcceptsAValidExportRequest(): void
    {
        self::assertNull((new ShipmentExportValidator())->errorLanguageKey(true, 42, 'api-key'));
    }
}
