<?php

namespace Opencart\Admin\Controller\Extension\Myparcel\Event;

use MyParcelNL\OpenCart\Core\Helper\CountryOptions;
use MyParcelNL\OpenCart\Core\Helper\HsCode;
use MyParcelNL\OpenCart\Core\Service\Product\ProductTable;
use MyParcelNL\OpenCart\Core\Settings\SettingKeys;
use MyParcelNL\Sdk\Services\CountryCodes;

require_once DIR_EXTENSION . 'myparcel/bootstrap.php';

/**
 * Adds the MyParcel customs fields (HS code + country of origin) to the product
 * editor when product customs fields are enabled, and stores them per product on
 * save. The fieldset renders through the OCMOD slot in catalog/product_form.twig.
 */
class Product extends \Opencart\System\Engine\Controller
{
    /**
     * OpenCart's core language event copies every loaded language key into the
     * data of any view rendered afterwards. Loading with a prefix keeps generic
     * keys such as heading_title from overriding the host page's own text.
     */
    private const LANGUAGE_PREFIX = 'myparcel';

    /**
     * Event `admin/view/catalog/product_form/before`: the customs fieldset for the Data tab.
     *
     * @param string $route OpenCart view route.
     * @param array<string, mixed> $data Product-form template data.
     * @param string $code Optional inline Twig source supplied by OpenCart.
     * @param string $output Rendered output override supplied by OpenCart.
     */
    public function prepareForm(&$route, &$data, &$code, &$output): void
    {
        if (!$this->config->get(SettingKeys::CUSTOMS_PRODUCT_FIELDS)) {
            return;
        }

        $this->load->language('extension/myparcel/module/myparcel', self::LANGUAGE_PREFIX);
        $this->load->model('extension/myparcel/product/myparcel');
        $this->load->model('localisation/country');

        $stored = $this->model_extension_myparcel_product_myparcel->getByProductId((int) ($data['product_id'] ?? 0));

        $countries = [];

        foreach (CountryOptions::fromOcCountries($this->model_localisation_country->getCountries()) as $isoCode => $name) {
            $countries[] = [
                'code' => $isoCode,
                'name' => $name,
                'selected' => $isoCode === $stored['country_of_origin'],
            ];
        }

        $data['myparcel_customs_fields'] = $this->load->view('extension/myparcel/event/product_customs_fields', [
            'text_legend' => $this->text('text_product_customs'),
            'text_hs_code' => $this->text('entry_product_hs_code'),
            'text_hs_code_help' => $this->text('help_product_hs_code'),
            'text_country' => $this->text('entry_product_country'),
            'text_none' => $this->text('text_none'),
            'hs_code' => $stored['hs_code'],
            'countries' => $countries,
        ]);
    }

    /** Read one of our own language keys through the collision-free prefix. */
    private function text(string $key): string
    {
        return $this->language->get(self::LANGUAGE_PREFIX . '_' . $key);
    }

    /**
     * Event `admin/model/catalog/product.addProduct/after`: store fields for the new product.
     *
     * @param string $route OpenCart model route.
     * @param array<int, mixed> $args Arguments passed to addProduct().
     * @param mixed $output Product id returned by addProduct().
     */
    public function saveOnAdd(&$route, &$args, &$output): void
    {
        $data = $args[0] ?? null;
        $productId = (int) $output;

        $this->store($productId, is_array($data) ? $data : []);
    }

    /**
     * Event `admin/model/catalog/product.editProduct/after`: store fields for the edited product.
     *
     * @param string $route OpenCart model route.
     * @param array<int, mixed> $args Arguments passed to editProduct().
     * @param mixed $output Result returned by editProduct().
     */
    public function saveOnEdit(&$route, &$args, &$output): void
    {
        $data = $args[1] ?? null;
        $this->store((int) ($args[0] ?? 0), is_array($data) ? $data : []);
    }

    /**
     * Validate and persist the customs fields from the posted product data.
     *
     * @param array<string, mixed> $data Posted product data.
     */
    private function store(int $productId, array $data): void
    {
        if ($productId <= 0 || !$this->config->get(SettingKeys::CUSTOMS_PRODUCT_FIELDS)) {
            return;
        }

        // Country is posted, so keep only a country the SDK knows; else clear it.
        $country = strtoupper(trim((string) ($data['myparcel_country_of_origin'] ?? '')));

        if (!in_array($country, CountryCodes::ALL, true)) {
            $country = '';
        }

        $this->load->model('extension/myparcel/product/myparcel');
        $this->model_extension_myparcel_product_myparcel->save(
            $productId,
            mb_substr(HsCode::normalize((string) ($data['myparcel_hs_code'] ?? '')), 0, ProductTable::HS_CODE_LENGTH),
            $country
        );
    }
}
