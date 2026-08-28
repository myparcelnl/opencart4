<?php

namespace Opencart\Catalog\Controller\Extension\Myparcel\Event;

use MyParcelNL\OpenCart\Core\Dto\ContractDefinitions;
use MyParcelNL\OpenCart\Core\Service\ContractDefinitionsCache;
use MyParcelNL\OpenCart\Core\Service\DeliveryOptions\CarrierSettingsBuilder;
use MyParcelNL\OpenCart\Core\Service\DeliveryOptions\PlatformResolver;
use MyParcelNL\OpenCart\Core\Settings\SettingKeys;
use MyParcelNL\OpenCart\Core\Settings\Settings;

require_once DIR_EXTENSION . 'myparcel/bootstrap.php';

/**
 * Injects the MyParcel Delivery Options widget into the checkout shipping-method
 * step and hands the adapter the storefront proxy + session-endpoint URLs.
 */
class Checkout extends \Opencart\System\Engine\Controller
{
    private const TOKEN_KEY = 'myparcel_delivery_options_token';

    private const LANGUAGE_PREFIX = 'myparcel_delivery_options';

    /**
     * Event handler for `catalog/view/checkout/shipping_method/after`: append the
     * widget config + adapter to the view output, only when the module and the
     * MyParcel shipping method are enabled and at least one carrier is configured.
     *
     * @param string $route OpenCart view route.
     * @param array<string, mixed> $data Checkout template data.
     * @param string $output Rendered checkout HTML.
     */
    public function injectDeliveryOptions(&$route, &$data, &$output): void
    {
        if (!$this->config->get(SettingKeys::STATUS) || !$this->config->get(SettingKeys::SHIPPING_STATUS)) {
            return;
        }

        $settings = Settings::fromConfig($this->config);
        $checkout = $settings->checkout;

        if (!$checkout->deliveryOptionsEnabled) {
            return;
        }

        $this->load->model('setting/setting');
        $definitions = (new ContractDefinitionsCache())->get($this->model_setting_setting);

        if ($definitions === null) {
            return;
        }

        $carrierSettingsBuilder = new CarrierSettingsBuilder();
        $packageType = $carrierSettingsBuilder->widgetPackageType($settings->defaultPackageType);
        $carrierSettings = $this->carrierSettings($definitions, $packageType);

        if ($carrierSettings === []) {
            return;
        }

        $this->load->language('extension/myparcel/checkout/delivery_options', self::LANGUAGE_PREFIX);

        $token = $this->session->data[self::TOKEN_KEY] ?? '';

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(16));
            $this->session->data[self::TOKEN_KEY] = $token;
        }

        $language = 'language=' . $this->config->get('config_language');

        $viewData = [
            // Shown instead of an empty block until the shopper's address is known.
            'myparcel_address_hint' => $this->language->get(self::LANGUAGE_PREFIX . '_hint_address'),
            'myparcel_config' => [

                'config' => array_merge($carrierSettingsBuilder->globalAllowFlags($carrierSettings), [
                    'platform' => PlatformResolver::toWidget($definitions->platform),
                    'proxyCapabilities' => $this->url->link(
                        'extension/myparcel/proxy',
                        $language . '&host=core&path=shipments/capabilities',
                        true
                    ),
                    'carrierSettings' => $carrierSettings,
                    'packageType' => $packageType,
                ], $checkout->toWidgetConfig()),
                'strings' => $this->strings(),
            ],
            'myparcel_oc4_config' => [
                'token' => $token,
                'shippingCode' => 'myparcel.myparcel',
                'selector' => '#myparcel-delivery-options',
                'stateUrl' => $this->url->link('extension/myparcel/checkout/delivery_options.state', $language, true),
                'saveUrl' => $this->url->link('extension/myparcel/checkout/delivery_options.save', $language, true),
                'clearUrl' => $this->url->link('extension/myparcel/checkout/delivery_options.clear', $language, true),
                // OpenCart core checkout endpoints the widget drives directly.
                'quoteUrl' => $this->url->link('checkout/shipping_method.quote', $language, true),
                'shippingSaveUrl' => $this->url->link('checkout/shipping_method.save', $language, true),
                'confirmUrl' => $this->url->link('checkout/confirm.confirm', $language, true),
                'adapterUrl' => 'extension/myparcel/catalog/view/javascript/myparcel/delivery-options.js',
                'assets' => [
                    'leafletCss' => 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.5.1/leaflet.css',
                    'deliveryOptionsCss' => 'https://cdn.jsdelivr.net/npm/@myparcel-dev/delivery-options@7/dist/style.css',
                    'leafletJs' => 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.5.1/leaflet.js',
                    'deliveryOptionsJs' => 'https://cdn.jsdelivr.net/npm/@myparcel-dev/delivery-options@7/dist/myparcel.js',
                ],
            ],
        ];

        $output .= $this->load->view('extension/myparcel/checkout/delivery_options', $viewData);
    }

    /**
     * Build widget carrier settings from the imported definitions and saved choices.
     *
     * @return array<string, array<string, bool|int|string>>
     */
    private function carrierSettings(ContractDefinitions $definitions, string $packageType): array
    {
        $storedCarriers = $this->model_setting_setting->getSetting(SettingKeys::CARRIERS);
        $carrierConfig = $storedCarriers[SettingKeys::CARRIERS] ?? [];

        return (new CarrierSettingsBuilder())->build(
            $definitions->toArray(),
            is_array($carrierConfig) ? $carrierConfig : [],
            $packageType
        );
    }

    /**
     * Return the prefixed widget translations for the active storefront language.
     *
     * @return array<string, string>
     */
    private function strings(): array
    {
        return $this->language->all(self::LANGUAGE_PREFIX . '_text');
    }
}
