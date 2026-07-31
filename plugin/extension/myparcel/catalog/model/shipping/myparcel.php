<?php

namespace Opencart\Catalog\Model\Extension\Myparcel\Shipping;

use MyParcelNL\OpenCart\Core\Settings\SettingKeys;

require_once DIR_EXTENSION . 'myparcel/bootstrap.php';

/**
 * Offers the MyParcel shipping method (code `myparcel.myparcel`) in checkout,
 * priced from the shipping-extension settings and gated by its geo zone.
 */
class Myparcel extends \Opencart\System\Engine\Model
{
    /**
     * Return the MyParcel shipping quote for the address, or an empty array when
     * the address falls outside the configured geo zone.
     *
     * @param array<string, mixed> $address
     * @return array<string, mixed>
     */
    public function getQuote(array $address): array
    {
        $this->load->language('extension/myparcel/shipping/myparcel');

        $query = $this->db->query(sprintf(
            "
                SELECT *
                FROM `%szone_to_geo_zone`
                WHERE `geo_zone_id` = '%d'
                    AND `country_id` = '%d'
                    AND (`zone_id` = '%d' OR `zone_id` = '0')
            ",
            DB_PREFIX,
            (int)$this->config->get(SettingKeys::SHIPPING_GEO_ZONE_ID),
            (int)$address['country_id'],
            (int)$address['zone_id']
        ));

        if (!$this->config->get(SettingKeys::SHIPPING_GEO_ZONE_ID)) {
            $status = true;
        } elseif ($query->num_rows) {
            $status = true;
        } else {
            $status = false;
        }

        $method_data = [];

        if ($status) {
            $cost = (float)$this->config->get(SettingKeys::SHIPPING_RATE);
            $taxClassId = (int)$this->config->get(SettingKeys::SHIPPING_TAX_CLASS_ID);
            $name = trim((string)$this->config->get(SettingKeys::SHIPPING_NAME)) ?: $this->language->get('text_description');

            $quote_data = [];

            $quote_data['myparcel'] = [
                'code' => 'myparcel.myparcel',
                'name' => $name,
                'cost' => $cost,
                'tax_class_id' => $taxClassId,
                'text' => $this->currency->format(
                    $this->tax->calculate($cost, $taxClassId, $this->config->get('config_tax')),
                    $this->session->data['currency']
                ),
            ];

            $method_data = [
                'code' => 'myparcel',
                'name' => $this->language->get('heading_title'),
                'quote' => $quote_data,
                'sort_order' => (int)$this->config->get(SettingKeys::SHIPPING_SORT_ORDER),
                'error' => false,
            ];
        }

        return $method_data;
    }
}
