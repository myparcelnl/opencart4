<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Helper;

/**
 * Resolves a package's dimensions to centimetres (the unit MyParcel expects). Some
 * carriers (e.g. Poste Italiane, InPost) require length/width/height on the shipment;
 * OpenCart stores dimensions per product in the shop's length class (preloaded in
 * ProductData), so the values are converted via the class ratios.
 */
final class DimensionResolver
{
    /**
     * Package dimensions in whole centimetres, or null when they cannot be resolved:
     * no product carries usable dimensions, or the shop has no cm length class to
     * convert against (unit classes are admin-managed, so 'cm' is not guaranteed to
     * exist). On null the caller falls back to the configured default package size.
     *
     * Uses the largest value per axis: the box must fit the biggest item in each
     * dimension. Quantity is ignored — real packing is unknown.
     *
     * @param array<int, array<string, mixed>> $orderProducts
     * @return array{length: int, width: int, height: int}|null
     */
    public function resolve(ProductData $data, array $orderProducts): ?array
    {
        if ($data->cmValue === null) {
            return null;
        }

        $max = ['length' => 0, 'width' => 0, 'height' => 0];

        foreach ($orderProducts as $product) {
            $productId = (int) ($product['product_id'] ?? 0);
            $row = $data->rows[$productId] ?? [];

            if (isset($row['shipping']) && !(bool) $row['shipping']) {
                continue;
            }

            $dimensions = $this->centimetres($data, $productId);

            foreach ($max as $axis => $value) {
                $max[$axis] = max($value, $dimensions[$axis]);
            }
        }

        return ($max['length'] > 0 && $max['width'] > 0 && $max['height'] > 0) ? $max : null;
    }

    /**
     * Convert one product's stored dimensions to whole centimetres.
     *
     * @return array{length: int, width: int, height: int}
     */
    private function centimetres(ProductData $data, int $productId): array
    {
        $empty = ['length' => 0, 'width' => 0, 'height' => 0];
        $row = $data->rows[$productId] ?? null;

        if ($row === null) {
            return $empty;
        }

        $classValue = $data->lengthClassValues[(int) $row['length_class_id']] ?? 0.0;

        if ($classValue <= 0) {
            return $empty;
        }

        // cm = value * (cm-per-base-unit / product-class-per-base-unit), per OC's length converter.
        $toCm = static fn (float $value): int => (int) round($value * ($data->cmValue / $classValue));

        return [
            'length' => $toCm((float) $row['length']),
            'width' => $toCm((float) $row['width']),
            'height' => $toCm((float) $row['height']),
        ];
    }
}
