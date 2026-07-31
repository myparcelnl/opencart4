<?php

namespace Opencart\Admin\Model\Extension\Myparcel\Product;

require_once DIR_EXTENSION . 'myparcel/bootstrap.php';

/**
 * Reads and writes the per-product customs fields (HS code + country of origin).
 */
class Myparcel extends \Opencart\System\Engine\Model
{
    /**
     * The stored customs fields for a product; empty strings when none are stored.
     *
     * @return array{hs_code: string, country_of_origin: string}
     */
    public function getByProductId(int $productId): array
    {
        $query = $this->db->query(sprintf(
            "SELECT `hs_code`, `country_of_origin` FROM `%smyparcel_product` WHERE `product_id` = %d",
            DB_PREFIX,
            $productId
        ));

        return $query->num_rows
            ? ['hs_code' => (string) $query->row['hs_code'], 'country_of_origin' => (string) $query->row['country_of_origin']]
            : ['hs_code' => '', 'country_of_origin' => ''];
    }

    /**
     * Upsert the customs fields for a product.
     */
    public function save(int $productId, string $hsCode, string $country): void
    {
        $hsCode = $this->db->escape($hsCode);
        $country = $this->db->escape($country);

        $this->db->query(sprintf(
            "INSERT INTO `%smyparcel_product` (`product_id`, `hs_code`, `country_of_origin`)
             VALUES (%d, '%s', '%s')
             ON DUPLICATE KEY UPDATE `hs_code` = '%s', `country_of_origin` = '%s'",
            DB_PREFIX,
            $productId,
            $hsCode,
            $country,
            $hsCode,
            $country
        ));
    }
}
