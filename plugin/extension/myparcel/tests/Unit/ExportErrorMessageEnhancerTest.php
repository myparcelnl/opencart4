<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Tests\Unit;

use MyParcelNL\OpenCart\Core\Service\ExportErrorMessageEnhancer;
use PHPUnit\Framework\TestCase;

final class ExportErrorMessageEnhancerTest extends TestCase
{
    private const PHONE_ADVICE = 'Add a phone number to the order.';

    private const DIMENSIONS_ADVICE = 'Configure package dimensions.';

    private const CLASSIFICATION_ADVICE = 'Check the HS code.';

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

    public function testAddsAdviceWhenTheApiOmitsTheDimensionName(): void
    {
        // Range violations often name only the range, not the dimension.
        $message = 'Invalid physical properties: Moet tussen 15 en 180 liggen.';

        self::assertSame(
            $message . ' ' . self::DIMENSIONS_ADVICE,
            $this->enhancer()->enhance($message, self::PHONE_ADVICE, self::DIMENSIONS_ADVICE)
        );
    }

    public function testAddsAdviceForWeightViolationsToo(): void
    {
        $message = 'Invalid physical properties: Weight should be between 50 and 70000.';

        self::assertSame(
            $message . ' ' . self::DIMENSIONS_ADVICE,
            $this->enhancer()->enhance($message, self::PHONE_ADVICE, self::DIMENSIONS_ADVICE)
        );
    }

    public function testAddsClassificationAdviceForHsCodeErrors(): void
    {
        $message = 'Invalid customs declaration: The length of items.0.classification is 9 characters'
            . ' and must be 10 characters for US shipments.';

        self::assertSame(
            $message . ' ' . self::CLASSIFICATION_ADVICE,
            $this->enhancer()->enhance($message, self::PHONE_ADVICE, self::DIMENSIONS_ADVICE, self::CLASSIFICATION_ADVICE)
        );
    }

    public function testLeavesUnrelatedErrorsUnchanged(): void
    {
        $message = 'Invalid recipient: The postal code is malformed.';

        self::assertSame(
            $message,
            $this->enhancer()->enhance($message, self::PHONE_ADVICE, self::DIMENSIONS_ADVICE, self::CLASSIFICATION_ADVICE)
        );
    }

    private function enhancer(): ExportErrorMessageEnhancer
    {
        return new ExportErrorMessageEnhancer();
    }
}
