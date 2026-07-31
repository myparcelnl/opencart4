<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Service\Product;

/**
 * Owns the `myparcel_product` table: per-product customs fields (HS code + country of origin)
 * used for customs declarations on non-EU shipments.
 */
final class ProductTable
{
    /** Column size of `hs_code`; writers truncate to this so inserts never overflow. */
    public const HS_CODE_LENGTH = 32;

    /**
     * Create the product customs table during module install.
     *
     * @param object $db OpenCart database adapter.
     */
    public function ensure(object $db, string $prefix): void
    {
        $table = '`' . str_replace('`', '``', $prefix . 'myparcel_product') . '`';

        $db->query("
            CREATE TABLE IF NOT EXISTS {$table} (
                `product_id` INT NOT NULL PRIMARY KEY,
                `hs_code` VARCHAR(32) NOT NULL DEFAULT '',
                `country_of_origin` VARCHAR(2) NOT NULL DEFAULT '',
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
