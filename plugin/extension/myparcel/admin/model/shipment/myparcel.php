<?php

namespace Opencart\Admin\Model\Extension\Myparcel\Shipment;

use MyParcelNL\OpenCart\Core\Service\Shipment\ShipmentTable;

require_once DIR_EXTENSION . 'myparcel/bootstrap.php';

/**
 * Reads and writes the MyParcel order/shipment administration: `myparcel_order`
 * holds the order-level data (Delivery Options, last export error), and
 * `myparcel_shipment` holds one row per shipment — an order can have many.
 * Both tables update `updated_at` at database level through ON UPDATE.
 */
class Myparcel extends \Opencart\System\Engine\Model
{
    /**
     * The order-level MyParcel row (delivery_options, last_error), or null when
     * the order has no MyParcel data yet.
     *
     * @return array<string, mixed>|null
     */
    public function getOrderRow(int $orderId): ?array
    {
        $query = $this->db->query(sprintf(
            "SELECT * FROM `%smyparcel_order` WHERE `order_id` = %d",
            DB_PREFIX,
            $orderId
        ));

        return $query->num_rows ? $query->row : null;
    }

    /**
     * All shipments for an order, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getShipmentsByOrderId(int $orderId): array
    {
        $query = $this->db->query(sprintf(
            "SELECT * FROM `%smyparcel_shipment` WHERE `order_id` = %d ORDER BY `id` DESC",
            DB_PREFIX,
            $orderId
        ));

        return $query->rows;
    }

    /**
     * The latest shipment for an order, or null when the order was never exported.
     *
     * @return array<string, mixed>|null
     */
    public function getLatestShipment(int $orderId): ?array
    {
        $query = $this->db->query(sprintf(
            "SELECT * FROM `%smyparcel_shipment` WHERE `order_id` = %d ORDER BY `id` DESC LIMIT 1",
            DB_PREFIX,
            $orderId
        ));

        return $query->num_rows ? $query->row : null;
    }

    /**
     * The shipment row for a specific MyParcel shipment id, scoped to the order so
     * a request cannot address another order's shipment. Null when not found.
     *
     * @return array<string, mixed>|null
     */
    public function getShipmentForOrder(int $orderId, int $shipmentId): ?array
    {
        $query = $this->db->query(sprintf(
            "SELECT * FROM `%smyparcel_shipment` WHERE `order_id` = %d AND `shipment_id` = %d",
            DB_PREFIX,
            $orderId,
            $shipmentId
        ));

        return $query->num_rows ? $query->row : null;
    }

    /**
     * Export state for a set of orders, keyed by order id: the latest shipment plus
     * the order-level Delivery Options and last export error. Orders without any
     * MyParcel data are absent. shipment_id is 0 for orders that were never exported.
     *
     * @param int[] $orderIds
     * @return array<int, array{shipment_id: int, shipment_count: int, status: string, carrier: string, delivery_options: string, last_error: string}>
     */
    public function statesByOrderIds(array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));

        if ($orderIds === []) {
            return [];
        }

        // Ids are cast to int above, so the IN list is safe to inline.
        $idList = implode(',', $orderIds);
        $states = [];

        // Order-level rows first, so never-exported orders still get their DO badge.
        $orderRows = $this->db->query(sprintf(
            "SELECT `order_id`, `delivery_options`, `last_error` FROM `%smyparcel_order` WHERE `order_id` IN (%s)",
            DB_PREFIX,
            $idList
        ));

        foreach ($orderRows->rows as $row) {
            $states[(int) $row['order_id']] = [
                'shipment_id' => 0,
                'shipment_count' => 0,
                'status' => '',
                'carrier' => '',
                'delivery_options' => (string) ($row['delivery_options'] ?? ''),
                'last_error' => (string) ($row['last_error'] ?? ''),
            ];
        }

        // Overlay the latest shipment per order (highest row id).
        $shipmentRows = $this->db->query(sprintf(
            "SELECT s.`order_id`, s.`shipment_id`, s.`status`, s.`carrier`, latest.`shipment_count`
             FROM `%smyparcel_shipment` s
             INNER JOIN (
                 SELECT `order_id`, MAX(`id`) AS `max_id`, COUNT(*) AS `shipment_count`
                 FROM `%smyparcel_shipment`
                 WHERE `order_id` IN (%s)
                 GROUP BY `order_id`
             ) latest ON latest.`max_id` = s.`id`",
            DB_PREFIX,
            DB_PREFIX,
            $idList
        ));

        foreach ($shipmentRows->rows as $row) {
            $orderId = (int) $row['order_id'];
            $existing = $states[$orderId] ?? [];
            $states[$orderId] = [
                'shipment_id' => (int) $row['shipment_id'],
                'shipment_count' => (int) $row['shipment_count'],
                'status' => (string) $row['status'],
                'carrier' => (string) ($row['carrier'] ?? ''),
                'delivery_options' => (string) ($existing['delivery_options'] ?? ''),
                'last_error' => (string) ($existing['last_error'] ?? ''),
            ];
        }

        return $states;
    }

    /**
     * Store a successful export as a new shipment row and clear the order's last
     * error. The upsert only fires when MyParcel returns an already-known shipment
     * id (e.g. a retried request); a new id always appends a row.
     */
    public function markExported(int $orderId, int $shipmentId, ?string $reference, ?string $carrier = null): void
    {
        $this->db->query(sprintf(
            "INSERT INTO `%smyparcel_shipment` (`order_id`, `shipment_id`, `reference`, `carrier`, `status`)
             VALUES (%d, %d, '%s', '%s', '%s')
             ON DUPLICATE KEY UPDATE
                 `reference` = '%s',
                 `carrier` = '%s',
                 `status` = '%s'",
            DB_PREFIX,
            $orderId,
            $shipmentId,
            $this->db->escape((string) $reference),
            $this->db->escape((string) $carrier),
            ShipmentTable::STATUS_EXPORTED,
            $this->db->escape((string) $reference),
            $this->db->escape((string) $carrier),
            ShipmentTable::STATUS_EXPORTED
        ));

        $this->db->query(sprintf(
            "UPDATE `%smyparcel_order` SET `last_error` = NULL WHERE `order_id` = %d",
            DB_PREFIX,
            $orderId
        ));
    }

    /**
     * Record a failed export on the order row, so the admin sees why without an
     * exception. A failed export creates no shipment row.
     */
    public function markFailed(int $orderId, string $message): void
    {
        $escaped = $this->db->escape($message);

        $this->db->query(sprintf(
            "INSERT INTO `%smyparcel_order` (`order_id`, `last_error`)
             VALUES (%d, '%s')
             ON DUPLICATE KEY UPDATE
                 `last_error` = '%s'",
            DB_PREFIX,
            $orderId,
            $escaped,
            $escaped
        ));
    }

    /**
     * Store the track & trace barcode and URL fetched after export (FR-009) on the
     * shipment's own row. Empty values never overwrite previously stored ones: the
     * carrier feed may briefly omit data it reported before.
     */
    public function updateTrackTrace(int $shipmentId, ?string $barcode, ?string $trackTraceUrl): void
    {
        $this->db->query(sprintf(
            "UPDATE `%smyparcel_shipment`
             SET `barcode` = COALESCE(NULLIF('%s', ''), `barcode`),
                 `tracktrace_url` = COALESCE(NULLIF('%s', ''), `tracktrace_url`)
             WHERE `shipment_id` = %d",
            DB_PREFIX,
            $this->db->escape((string) $barcode),
            $this->db->escape((string) $trackTraceUrl),
            $shipmentId
        ));
    }
}
