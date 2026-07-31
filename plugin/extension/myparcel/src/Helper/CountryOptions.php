<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Helper;

/**
 * Maps OpenCart's country rows to an ISO-2 code => name list for select inputs.
 */
final class CountryOptions
{
    /**
     * Build select options from OpenCart's country model rows.
     *
     * @param array<int, array<string, mixed>> $countries Rows from model_localisation_country.
     * @return array<string, string> iso_code_2 => name
     */
    public static function fromOcCountries(array $countries): array
    {
        $options = [];

        foreach ($countries as $country) {
            $code = (string) ($country['iso_code_2'] ?? '');

            if ($code !== '') {
                $options[$code] = (string) ($country['name'] ?? '');
            }
        }

        return $options;
    }
}
