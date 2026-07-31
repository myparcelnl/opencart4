<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Tests\Unit;

use MyParcelNL\OpenCart\Core\Service\Shipment\ShipmentApiFailure;
use MyParcelNL\OpenCart\Core\Service\Shipment\ShipmentApiFailureLogger;
use PHPUnit\Framework\TestCase;

final class ShipmentApiFailureLoggerTest extends TestCase
{
    public function testKeepsUserFacingApiErrorButSanitizesOperationalLogContext(): void
    {
        $exception = new class ('Request failed') extends \RuntimeException {
            public function getResponseBody(): string
            {
                return json_encode(['errors' => [[
                    'status' => 422,
                    'code' => 'recipient_invalid',
                    'message' => 'Email jane@example.test phone +31612345678 api_key=secret',
                ]]], JSON_THROW_ON_ERROR);
            }
        };
        $failure = ShipmentApiFailure::fromThrowable($exception);
        $logged = '';

        (new ShipmentApiFailureLogger(
            static function (string $message) use (&$logged): void {
                $logged = $message;
            }
        ))->log(
            'export',
            17,
            null,
            $exception
        );

        self::assertSame('[recipient_invalid] Email [email] phone [phone] [redacted]', $failure->message());
        self::assertStringContainsString('action="export"', $logged);
        self::assertStringContainsString('order_id="17"', $logged);
        self::assertStringContainsString('status="422"', $logged);
        self::assertStringContainsString('api_error_code="recipient_invalid"', $logged);
        self::assertStringContainsString('[email]', $logged);
        self::assertStringContainsString('[phone]', $logged);
        self::assertStringNotContainsString('jane@example.test', $logged);
        self::assertStringNotContainsString('31612345678', $logged);
        self::assertStringNotContainsString('secret', $logged);
    }

    public function testKeepsValidationTextThatNamesAPersonalDataField(): void
    {
        $exception = new class ('Invalid address') extends \RuntimeException {
            public function getResponseBody(): string
            {
                return json_encode(['errors' => [[
                    'status' => 422,
                    'message' => "The address field 'phone' is shorter than 1 character.",
                ]]], JSON_THROW_ON_ERROR);
            }
        };

        self::assertSame(
            "[422] The address field 'phone' is shorter than 1 character.",
            ShipmentApiFailure::fromThrowable($exception)->message()
        );
    }

    public function testMasksAConcreteAddressInAnApiMessage(): void
    {
        $exception = new class ('Invalid recipient') extends \RuntimeException {
            public function getResponseBody(): string
            {
                return json_encode(['errors' => [[
                    'status' => 422,
                    'message' => 'Recipient address: Jane Doe, Main Street 1',
                ]]], JSON_THROW_ON_ERROR);
            }
        };

        self::assertSame(
            '[422] Recipient address: [redacted]',
            ShipmentApiFailure::fromThrowable($exception)->message()
        );
    }

    public function testFormatsTheMapperFallbackWithOnlyItsNonSensitiveDiagnostic(): void
    {
        $logged = '';

        (new ShipmentApiFailureLogger(
            static function (string $message) use (&$logged): void {
                $logged = $message;
            }
        ))->logMapperFallback(
            17,
            'MyParcel shipment mapper fallback: package type "legacy" => "package"'
        );

        self::assertSame(
            'MyParcel shipment mapper fallback order_id="17" diagnostic="MyParcel shipment mapper fallback: package type \\"legacy\\" => \\"package\\""',
            $logged
        );
    }

    public function testValidationShapeKeepsItsApiErrorCodeInTheLog(): void
    {
        $exception = new class ('Unprocessable Entity', 422) extends \RuntimeException {
            public function getResponseBody(): string
            {
                return json_encode(['errors' => [[
                    '3704' => ['human' => ['Weight should be between 50 and 70000']],
                ]]], JSON_THROW_ON_ERROR);
            }
        };
        $logged = '';
        $logger = new ShipmentApiFailureLogger(static function (string $message) use (&$logged): void {
            $logged = $message;
        });

        $logger->log('export', 17, null, $exception);

        self::assertSame(
            '3704: Weight should be between 50 and 70000',
            ShipmentApiFailure::fromThrowable($exception)->message()
        );
        self::assertStringContainsString('api_error_code="3704"', $logged);
    }
}
