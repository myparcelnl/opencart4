<?php

namespace Opencart\Catalog\Model\Extension\Myparcel\Checkout;

/**
 * Persists Delivery Options selections against the order-level MyParcel row
 * (`myparcel_order`). Shipments live in their own table; the Delivery Options
 * stay order-level and are applied to every shipment created on export.
 * The table is created on install, so the queries assume it exists.
 * Its `updated_at` column is maintained by the database through ON UPDATE.
 */
class DeliveryOptions extends \Opencart\System\Engine\Model
{
    /**
     * Save the raw widget payload for the final order.
     */
    public function saveDeliveryOptions(int $orderId, string $json): void
    {
        // The escaped value is repeated instead of VALUES()/alias syntax: VALUES()
        // is deprecated since MySQL 8.0.20
        $escapedJson = $this->db->escape($json);

        $this->db->query(sprintf(
            "
                INSERT INTO `%smyparcel_order` (`order_id`, `delivery_options`)
                VALUES (%d, '%s')
                ON DUPLICATE KEY UPDATE
                    `delivery_options` = '%s'
            ",
            DB_PREFIX,
            (int)$orderId,
            $escapedJson,
            $escapedJson
        ));
    }

    /** Clear a stale Delivery Options choice without deleting other order data. */
    public function deleteDeliveryOptions(int $orderId): void
    {
        $this->db->query(sprintf(
            "
                UPDATE `%smyparcel_order`
                SET `delivery_options` = NULL
                WHERE `order_id` = %d
            ",
            DB_PREFIX,
            (int)$orderId
        ));

        // Keep rows that still contain an export error; otherwise an empty order row
        // has no value and can be removed.
        $this->db->query(sprintf(
            "
                DELETE FROM `%smyparcel_order`
                WHERE `order_id` = %d
                  AND `delivery_options` IS NULL
                  AND `last_error` IS NULL
            ",
            DB_PREFIX,
            (int)$orderId
        ));
    }
}
