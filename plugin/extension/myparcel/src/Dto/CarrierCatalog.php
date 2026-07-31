<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Dto;

/**
 * Validated public carrier metadata, kept separately from account contracts.
 *
 * Contracts decide which carriers an account may use; this value only supplies
 * the id, display name and logo for those carriers.
 */
final class CarrierCatalog
{
    private const ASSET_HOST = 'https://assets.myparcel.nl';

    /**
     * Store carrier rows after API or cache validation.
     *
     * @param list<array{id: int, slug: string, name: string, logo_svg: string}> $carriers
     */
    private function __construct(public array $carriers)
    {
    }

    /** Return an empty catalog for installations that have not imported it yet. */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Rehydrate a cache value, discarding individual invalid legacy rows.
     *
     * @param mixed $raw
     */
    public static function fromCache(mixed $raw): self
    {
        if (!is_array($raw)) {
            return self::empty();
        }

        $carriers = [];

        foreach ($raw as $row) {
            $carrier = self::normaliseRow($row);

            if ($carrier !== null) {
                $carriers[$carrier['id']] = $carrier;
            }
        }

        return new self(array_values($carriers));
    }

    /**
     * Strictly parse the public Core API response before it enters the cache.
     *
     * @param array<string, mixed> $response
     * @throws \UnexpectedValueException When the public response contract is malformed.
     */
    public static function fromApiResponse(array $response): self
    {
        $data = $response['data'] ?? null;
        $rows = is_array($data) && array_key_exists('carriers', $data) ? $data['carriers'] : $data;

        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \UnexpectedValueException('Invalid carrier catalog response.');
        }

        $carriers = [];

        foreach ($rows as $row) {
            $carrier = self::normaliseRow($row);

            if ($carrier === null || isset($carriers[$carrier['id']])) {
                throw new \UnexpectedValueException('Invalid carrier catalog row.');
            }

            foreach ($carriers as $existing) {
                if ($existing['slug'] === $carrier['slug']) {
                    throw new \UnexpectedValueException('Invalid carrier catalog row.');
                }
            }

            $carriers[$carrier['id']] = $carrier;
        }

        if ($carriers === []) {
            throw new \UnexpectedValueException('Carrier catalog response is empty.');
        }

        return new self(array_values($carriers));
    }

    /**
     * Serialize validated rows for OpenCart's settings cache.
     *
     * @return list<array{id: int, slug: string, name: string, logo_svg: string}>
     */
    public function toArray(): array
    {
        return $this->carriers;
    }

    /** Resolve a legacy numeric carrier id to the Delivery Options slug. */
    public function slugForId(int $id): ?string
    {
        foreach ($this->carriers as $carrier) {
            if ($carrier['id'] === $id) {
                return $carrier['slug'];
            }
        }

        return null;
    }

    /** Return the public human label for a slug, if that slug is in the catalog. */
    public function nameForSlug(string $slug): ?string
    {
        foreach ($this->carriers as $carrier) {
            if ($carrier['slug'] === $slug) {
                return $carrier['name'];
            }
        }

        return null;
    }

    /** Return a verified MyParcel asset URL for a slug, or an empty string. */
    public function logoUrlForSlug(string $slug): string
    {
        foreach ($this->carriers as $carrier) {
            if ($carrier['slug'] === $slug) {
                return self::ASSET_HOST . $carrier['logo_svg'];
            }
        }

        return '';
    }

    /**
     * Normalize one API or cache row into the internal catalog shape.
     *
     * @param mixed $row
     * @return array{id: int, slug: string, name: string, logo_svg: string}|null
     */
    private static function normaliseRow(mixed $row): ?array
    {
        if (!is_array($row)) {
            return null;
        }

        $id = $row['id'] ?? null;
        $isCachedRow = array_key_exists('slug', $row);
        $slug = $isCachedRow ? $row['slug'] : ($row['name'] ?? null);
        $name = $isCachedRow ? $row['name'] : ($row['human'] ?? $row['label'] ?? null);
        $meta = $row['meta'] ?? null;
        $logo = $isCachedRow
            ? ($row['logo_svg'] ?? null)
            : (is_array($meta) ? ($meta['logo_svg'] ?? null) : null);

        if (!is_int($id) || $id < 1
            || !self::isSafeSlug($slug)
            || !self::isSafeLabel($name)
            || !self::isSafeLogoPath($logo)
        ) {
            return null;
        }

        return [
            'id' => $id,
            'slug' => $slug,
            'name' => trim($name),
            'logo_svg' => $logo,
        ];
    }

    /** True when a public carrier slug is safe to cache and render. */
    private static function isSafeSlug(mixed $value): bool
    {
        return is_string($value)
            && !str_contains($value, '..')
            && preg_match('/^[a-z0-9](?:[a-z0-9._-]{0,62}[a-z0-9])?$/', $value) === 1;
    }

    /** True when a public display label is valid UTF-8 without markup or controls. */
    private static function isSafeLabel(mixed $value): bool
    {
        return is_string($value)
            && trim($value) !== ''
            && strlen($value) <= 120
            && preg_match('//u', $value) === 1
            && preg_match('/[<>\x00-\x1F\x7F]/', $value) !== 1;
    }

    /** True when the logo is a relative SVG path under the known carrier asset tree. */
    private static function isSafeLogoPath(mixed $value): bool
    {
        return is_string($value)
            && !str_contains($value, '..')
            && preg_match(
                '#^/skin/general-images/carrier-logos/(?:[A-Za-z0-9][A-Za-z0-9._-]*/)*[A-Za-z0-9][A-Za-z0-9._-]*\.svg$#',
                $value
            ) === 1;
    }
}
