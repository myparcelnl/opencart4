<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Service\Order;

/**
 * Owns the `myparcel_order` table: order-level MyParcel data — the Delivery Options
 * chosen at checkout and the last export error. Shipments live in their own 1:N
 * table (`myparcel_shipment`); an order can be exported multiple times.
 */
final class OrderTable
{
    /**
     * Create the order-level table during module install.
     *
     * @param object $db OpenCart database adapter.
     */
    public function ensure(object $db, string $prefix): void
    {
        $table = '`' . str_replace('`', '``', $prefix . 'myparcel_order') . '`';

        $db->query("
            CREATE TABLE IF NOT EXISTS {$table} (
                `order_id` INT NOT NULL PRIMARY KEY,
                `delivery_options` JSON NULL,
                `last_error` TEXT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
