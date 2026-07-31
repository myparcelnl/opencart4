<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Service\Address;

/**
 * Splits a single-field street line ("Hoofdstraat 12a") into its parts. Shared
 * between the checkout state endpoint (widget address) and the shipment export
 * mapper, so both use the same splitting rules. The SDK SplitStreet helper is
 * intentionally not used here: it is marked internal/legacy and only splits
 * Dutch and Belgian formats when an origin country is available.
 */
final class StreetSplitter
{
    /**
     * Street-line pattern: street name, house number, optional suffix
     * ("12a", "12-2", "12 bis"). Known limitation: number-first formats
     * ("12 Rue de la Paix") yield no match.
     */
    private const STREET_PATTERN = '/(.*?)\s?(\d{1,5})[\/\s-]{0,2}([A-Za-z]\d{1,3}|-\d{1,4}|\d{2}\w{1,2}|[A-Za-z][A-Za-z\s]{0,3})?$/';

    /**
     * Split a street line while preserving the original when no number matches.
     *
     * @return array{street: string, number: int|null, suffix: string|null}
     */
    public function split(string $streetLine): array
    {
        if (preg_match(self::STREET_PATTERN, trim($streetLine), $matches) !== 1 || !isset($matches[2])) {
            return ['street' => trim($streetLine), 'number' => null, 'suffix' => null];
        }

        $street = trim($matches[1]);
        $suffix = isset($matches[3]) ? trim($matches[3], " -/") : '';

        return [
            'street' => $street !== '' ? $street : trim($streetLine),
            'number' => (int) $matches[2],
            'suffix' => $suffix !== '' ? $suffix : null,
        ];
    }

    /**
     * The house number alone, or null when none could be extracted.
     */
    public function houseNumber(string $streetLine): ?int
    {
        return $this->split($streetLine)['number'];
    }
}
