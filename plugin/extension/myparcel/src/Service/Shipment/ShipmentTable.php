<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Service\Shipment;

use RuntimeException;

/**
 * Owns the `myparcel_shipment` table: one row per MyParcel shipment. An order can
 * have multiple shipments (every export appends one), so `order_id` is a plain
 * index; order-level data (Delivery Options, last error) lives in `myparcel_order`.
 */
final class ShipmentTable
{
    /** Plugin-owned state stored in the bounded `status` column. */
    public const STATUS_EXPORTED = 'exported';

    /**
     * Create the shipment table during module install.
     *
     * @param object $db OpenCart database adapter.
     */
    public function ensure(object $db, string $prefix): void
    {
        $table = $this->tableName($prefix);
        $quotedTable = $this->quoteIdentifier($table);

        $db->query("
            CREATE TABLE IF NOT EXISTS {$quotedTable} (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `order_id` INT NOT NULL,
                `shipment_id` INT NULL,
                `reference` TEXT NULL,
                `carrier` TEXT NULL,
                `barcode` TEXT NULL,
                `tracktrace_url` TEXT NULL,
                `status` VARCHAR(32) NOT NULL DEFAULT 'exported',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY `idx_order_id` (`order_id`),
                UNIQUE KEY `uniq_shipment_id` (`shipment_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->assertSupportedSchema($db, $table, $prefix);
    }

    /**
     * Fail clearly when a local pre-release database still uses the old
     * one-shipment-per-order schema. There are no released installations to migrate;
     * local development tables should be recreated before reinstalling the module.
     *
     * @param object $db OpenCart database adapter.
     */
    private function assertSupportedSchema(object $db, string $table, string $prefix): void
    {
        $legacy = $this->columnType($db, $table, 'delivery_options') !== null
            || $this->columnType($db, $table, 'error_message') !== null
            || $this->hasIndex($db, $table, 'uniq_order_id')
            || $this->columnType($db, $table, 'reference') !== 'text'
            || $this->columnType($db, $table, 'carrier') !== 'text'
            || $this->columnType($db, $table, 'barcode') !== 'text'
            || $this->columnType($db, $table, 'tracktrace_url') !== 'text';

        if ($legacy) {
            throw new RuntimeException(sprintf(
                'Unsupported pre-release MyParcel schema. Drop `%smyparcel_order` and `%smyparcel_shipment`, then reinstall the module.',
                $prefix,
                $prefix
            ));
        }
    }

    /**
     * Return a column's lowercase database type, or null when it is absent.
     *
     * @param object $db OpenCart database adapter.
     */
    private function columnType(object $db, string $table, string $column): ?string
    {
        $query = $db->query(sprintf(
            "SHOW COLUMNS FROM %s LIKE '%s'",
            $this->quoteIdentifier($table),
            $db->escape($column)
        ));

        return $query->num_rows ? strtolower((string) ($query->row['Type'] ?? '')) : null;
    }

    /**
     * Check whether a named index exists on the shipment table.
     *
     * @param object $db OpenCart database adapter.
     */
    private function hasIndex(object $db, string $table, string $indexName): bool
    {
        $query = $db->query(sprintf(
            "SHOW INDEX FROM %s WHERE `Key_name` = '%s'",
            $this->quoteIdentifier($table),
            $db->escape($indexName)
        ));

        return (bool) $query->num_rows;
    }

    /**
     * Build the fully prefixed table name.
     */
    private function tableName(string $prefix): string
    {
        return $prefix . 'myparcel_shipment';
    }

    /**
     * Quote a SQL identifier without relying on OpenCart's value escaping.
     */
    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
