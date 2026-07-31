<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Tests\Unit;

use MyParcelNL\OpenCart\Core\Service\ExportErrorMessageEnhancer;
use PHPUnit\Framework\TestCase;

final class ExportErrorMessageEnhancerTest extends TestCase
{
    private const PHONE_ADVICE = 'Add a phone number to the order.';

    private const DIMENSIONS_ADVICE = 'Configure package dimensions.';

    public function testAddsPhoneAdviceAfterTheOriginalMessage(): void
    {
        $message = "Invalid address: Het adresveld 'phone' voor 'ontvangstadres' is korter dan de minimale lengte van 1 tekens.";

        self::assertSame(
            $message . ' ' . self::PHONE_ADVICE,
            $this->enhancer()->enhance($message, self::PHONE_ADVICE, self::DIMENSIONS_ADVICE)
        );
    }

    public function testAddsDimensionsAdviceAfterTheOriginalMessage(): void
    {
        $message = 'Invalid physical properties: De lengte van het pakket ontbreekt.';

        self::assertSame(
            $message . ' ' . self::DIMENSIONS_ADVICE,
            $this->enhancer()->enhance($message, self::PHONE_ADVICE, self::DIMENSIONS_ADVICE)
        );
    }

    public function testLeavesUnrelatedPhysicalPropertiesErrorsUnchanged(): void
    {
        $message = 'Invalid physical properties: Weight should be between 50 and 70000.';

        self::assertSame(
            $message,
            $this->enhancer()->enhance($message, self::PHONE_ADVICE, self::DIMENSIONS_ADVICE)
        );
    }

    private function enhancer(): ExportErrorMessageEnhancer
    {
        return new ExportErrorMessageEnhancer();
    }
}
