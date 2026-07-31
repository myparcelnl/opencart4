<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Helper;

/** Adds preloaded customs and shipping attributes to OpenCart order lines. */
final class ProductExportDataResolver
{
    /**
     * @param array<int, array<string, mixed>> $orderProducts
     * @return array<int, array<string, mixed>>
     */
    public function attach(ProductData $data, array $orderProducts): array
    {
        foreach ($orderProducts as &$product) {
            $row = $data->rows[(int) ($product['product_id'] ?? 0)] ?? [];
            $product['hs_code'] = trim((string) ($row['hs_code'] ?? ''));
            $product['country_of_origin'] = strtoupper(trim((string) ($row['country_of_origin'] ?? '')));
            $product['requires_shipping'] = !isset($row['shipping']) || (bool) $row['shipping'];
        }
        unset($product);

        return $orderProducts;
    }
}
