<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Service;

/**
 * Adds actionable OpenCart guidance to known shipment validation messages.
 */
final class ExportErrorMessageEnhancer
{
    /** @var string[] */
    private const PHONE_MARKERS = ['phone', 'telephone', 'telefoon', 'telefono'];

    /**
     * A physical-properties marker alone is enough for the dimensions advice: the
     * API often reports only the violated range (e.g. "moet tussen 15 en 180
     * liggen") without naming the dimension, so requiring a dimension word too
     * would suppress the advice exactly when the merchant needs it.
     *
     * @var string[]
     */
    private const PHYSICAL_PROPERTIES_MARKERS = [
        'physical properties',
        'physical_properties',
        'fysieke eigenschappen',
        'proprietà fisiche',
    ];

    /** @var string[] */
    private const CLASSIFICATION_MARKERS = [
        'classification',
        'classificatie',
        'classificazione',
        'hs code',
        'hs-code',
    ];

    /**
     * Preserve the API message and append guidance for validation errors the
     * merchant can resolve in OpenCart.
     */
    public function enhance(
        string $message,
        string $phoneAdvice,
        string $dimensionsAdvice,
        string $classificationAdvice = ''
    ): string {
        $normalized = strtolower($message);
        $advice = [];

        if ($this->containsAny($normalized, self::PHONE_MARKERS)) {
            $advice[] = $phoneAdvice;
        }

        if ($this->containsAny($normalized, self::PHYSICAL_PROPERTIES_MARKERS)) {
            $advice[] = $dimensionsAdvice;
        }

        if ($this->containsAny($normalized, self::CLASSIFICATION_MARKERS)) {
            $advice[] = $classificationAdvice;
        }

        foreach (array_unique($advice) as $text) {
            if ($text !== '' && !str_contains($message, $text)) {
                $message .= ' ' . $text;
            }
        }

        return $message;
    }

    /**
     * @param string[] $needles
     */
    private function containsAny(string $message, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }
}
