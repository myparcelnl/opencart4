<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Helper;

/**
 * Resolves order-line weights to grams. OpenCart's order_product carries no weight,
 * so the weight comes from the live product (preloaded in ProductData) and is
 * converted from the shop's weight class to grams (MyParcel expects integer grams
 * per single item).
 */
final class WeightResolver
{
    /**
     * Return the order products with a 'weight' key (grams per single item) added.
     *
     * @param array<int, array<string, mixed>> $orderProducts
     * @return array<int, array<string, mixed>>
     */
    public function attachWeights(ProductData $data, array $orderProducts): array
    {
        foreach ($orderProducts as &$product) {
            $product['weight'] = $this->grams($data, (int) ($product['product_id'] ?? 0));
        }
        unset($product);

        return $orderProducts;
    }

    /** Convert one product's configured weight to grams. */
    private function grams(ProductData $data, int $productId): int
    {
        $row = $data->rows[$productId] ?? null;

        if ($row === null) {
            return 0;
        }

        $weight = (float) $row['weight'];
        $classValue = $data->weightClassValues[(int) $row['weight_class_id']] ?? 0.0;

        if ($weight <= 0 || $classValue <= 0) {
            return 0;
        }

        // grams = weight * (grams-per-base-unit / product-class-per-base-unit), per OC's weight converter.
        return (int) round($weight * ($data->gramValue / $classValue));
    }
}
