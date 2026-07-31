<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Helper;

/**
 * Bulk-loaded product data for one order: shipping/customs attributes plus the
 * weight, dimensions and unit-class ratios the resolvers need, fetched in a fixed
 * number of queries instead of one query per product.
 */
final class ProductData
{
    /**
     * Store the data loaded for one order export.
     *
     * @param array<int, array<string, mixed>> $rows product_id => product row
     * @param array<int, float> $weightClassValues weight_class_id => ratio relative to the base unit
     * @param array<int, float> $lengthClassValues length_class_id => ratio relative to the base unit
     * @param float $gramValue ratio of the gram class, or 0 when the shop has no gram weight class
     * @param float|null $cmValue ratio of the cm class, or null when the shop has no cm length class
     */
    private function __construct(
        public array $rows,
        public array $weightClassValues,
        public array $lengthClassValues,
        public float $gramValue,
        public ?float $cmValue
    ) {
    }

    /**
     * Load the shipping, customs, weight and dimension data for all products at once.
     *
     * @param object $db OpenCart database adapter.
     * @param array<int, array<string, mixed>> $orderProducts
     */
    public static function load(object $db, int $languageId, array $orderProducts): self
    {
        $weightClassValues = self::classValues($db, 'weight_class');
        $lengthClassValues = self::classValues($db, 'length_class');

        $gramClassId = self::classIdForUnit($db, 'weight_class', 'g', $languageId);
        $cmClassId = self::classIdForUnit($db, 'length_class', 'cm', $languageId);

        return new self(
            self::productRows($db, $orderProducts),
            $weightClassValues,
            $lengthClassValues,
            $weightClassValues[$gramClassId] ?? 0.0,
            $lengthClassValues[$cmClassId] ?? null
        );
    }

    /**
     * Load the relevant product rows in one query.
     *
     * @param object $db OpenCart database adapter.
     * @param array<int, array<string, mixed>> $orderProducts
     * @return array<int, array<string, mixed>> product_id => product row
     */
    private static function productRows(object $db, array $orderProducts): array
    {
        $ids = [];

        foreach ($orderProducts as $product) {
            $id = (int) ($product['product_id'] ?? 0);

            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        if ($ids === []) {
            return [];
        }

        $query = $db->query(sprintf(
            "SELECT p.`product_id`, p.`shipping`, p.`weight`, p.`weight_class_id`,
                    p.`length`, p.`width`, p.`height`, p.`length_class_id`,
                    COALESCE(mp.`hs_code`, '') AS `hs_code`,
                    COALESCE(mp.`country_of_origin`, '') AS `country_of_origin`
             FROM `%sproduct` p
             LEFT JOIN `%smyparcel_product` mp ON mp.`product_id` = p.`product_id`
             WHERE p.`product_id` IN (%s)",
            DB_PREFIX,
            DB_PREFIX,
            implode(',', $ids)
        ));

        $rows = [];

        foreach ($query->rows as $row) {
            $rows[(int) $row['product_id']] = $row;
        }

        return $rows;
    }

    /**
     * Load one unit class as id => conversion ratio.
     *
     * @param object $db OpenCart database adapter.
     * @param string $table 'weight_class' or 'length_class'
     * @return array<int, float> class id => ratio relative to the base unit
     */
    private static function classValues(object $db, string $table): array
    {
        $column = $table . '_id';
        $values = [];

        foreach ($db->query(sprintf("SELECT `%s`, `value` FROM `%s%s`", $column, DB_PREFIX, $table))->rows as $row) {
            $values[(int) $row[$column]] = (float) $row['value'];
        }

        return $values;
    }

    /**
     * The class id for a unit code, preferring the configured language but falling
     * back to any language: the codes themselves ('g', 'cm') are language-independent,
     * while a shop may lack the description row for the configured language. 0 when
     * the unit does not exist at all (unit classes are admin-managed).
     *
     * @param object $db OpenCart database adapter.
     */
    private static function classIdForUnit(object $db, string $table, string $unit, int $languageId): int
    {
        $column = $table . '_id';

        $query = $db->query(sprintf(
            "SELECT `%s` FROM `%s%s_description` WHERE `unit` = '%s' ORDER BY (`language_id` = %d) DESC LIMIT 1",
            $column,
            DB_PREFIX,
            $table,
            $db->escape($unit),
            $languageId
        ));

        return $query->num_rows ? (int) $query->row[$column] : 0;
    }
}
