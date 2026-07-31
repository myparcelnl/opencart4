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

    /** @var string[] */
    private const PHYSICAL_PROPERTIES_MARKERS = [
        'physical properties',
        'physical_properties',
        'fysieke eigenschappen',
        'proprietà fisiche',
    ];

    /** @var string[] */
    private const DIMENSION_MARKERS = [
        'length',
        'width',
        'height',
        'dimension',
        'lengte',
        'breedte',
        'hoogte',
        'afmeting',
        'lunghezza',
        'larghezza',
        'altezza',
        'dimensione',
        'dimensioni',
    ];

    /**
     * Preserve the API message and append guidance for validation errors the
     * merchant can resolve in OpenCart.
     */
    public function enhance(string $message, string $phoneAdvice, string $dimensionsAdvice): string
    {
        $normalized = strtolower($message);
        $advice = [];

        if ($this->containsAny($normalized, self::PHONE_MARKERS)) {
            $advice[] = $phoneAdvice;
        }

        if (
            $this->containsAny($normalized, self::PHYSICAL_PROPERTIES_MARKERS)
            && $this->containsAny($normalized, self::DIMENSION_MARKERS)
        ) {
            $advice[] = $dimensionsAdvice;
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
