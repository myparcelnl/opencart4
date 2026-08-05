<?php

namespace Opencart\Admin\Controller\Extension\Myparcel\Module;

use MyParcelNL\OpenCart\Core\Dto\ContractDefinitions;
use MyParcelNL\OpenCart\Core\Dto\CarrierCatalog;
use MyParcelNL\OpenCart\Core\Enum\Environment;
use MyParcelNL\OpenCart\Core\Helper\CountryOptions;
use MyParcelNL\OpenCart\Core\Helper\OrderToShipmentMapper;
use MyParcelNL\OpenCart\Core\Service\CapabilitiesService;
use MyParcelNL\OpenCart\Core\Service\Carrier\CarrierCatalogService;
use MyParcelNL\OpenCart\Core\Service\Carrier\CarrierPresenter;
use MyParcelNL\OpenCart\Core\Service\Carrier\CarrierResolver;
use MyParcelNL\OpenCart\Core\Service\ContractDefinitionsCache;
use MyParcelNL\OpenCart\Core\Service\DeliveryOptions\CarrierSettingsBuilder;
use MyParcelNL\OpenCart\Core\Service\DefaultCarrierService;
use MyParcelNL\OpenCart\Core\Service\Order\OrderTable;
use MyParcelNL\OpenCart\Core\Service\Product\ProductTable;
use MyParcelNL\OpenCart\Core\Service\Shipment\ShipmentTable;
use MyParcelNL\OpenCart\Core\Service\WhoamiService;
use MyParcelNL\OpenCart\Core\Settings\CheckoutSettings;
use MyParcelNL\OpenCart\Core\Settings\PluginStateStore;
use MyParcelNL\OpenCart\Core\Settings\SchemaMigrator;
use MyParcelNL\OpenCart\Core\Settings\SettingKeys;
use MyParcelNL\OpenCart\Core\Settings\Settings;
use MyParcelNL\OpenCart\Core\Support\OpenCartCompatibility;
use MyParcelNL\Sdk\Client\Generated\CoreApi\ApiException as CoreApiException;
use MyParcelNL\Sdk\Client\Generated\IamApi\ApiException as IamApiException;

require_once DIR_EXTENSION . 'myparcel/bootstrap.php';

/**
 * Admin settings for the MyParcel module: API key validation and carrier
 * configuration import.
 */
class Myparcel extends \Opencart\System\Engine\Controller
{
    /** Must match the OCMOD XML version and its revisioned slot markers. */
    private const OCMOD_REVISION = '0.2.0';

    /** Every generated template marker required for a healthy OCMOD cache. */
    private const OCMOD_SLOTS = [
        'admin/view/template/sale/order.twig' => ['order-assets'],
        'admin/view/template/sale/order_list.twig' => ['order-list-actions'],
        'admin/view/template/sale/order_info.twig' => [
            'order-info-toolbar',
            'order-info-content',
            'order-assets',
        ],
        'admin/view/template/catalog/product_form.twig' => ['product-customs'],
    ];

    /** Single source for installing and removing every OpenCart event hook. */
    private const EVENTS = [
        [
            'code' => 'myparcel_checkout_shipping_method_after',
            'description_key' => 'event_checkout_shipping_method',
            'trigger' => 'catalog/view/checkout/shipping_method/after',
            'action' => 'extension/myparcel/event/checkout.injectDeliveryOptions',
        ],
        [
            'code' => 'myparcel_admin_order_list',
            'description_key' => 'event_admin_order_list',
            'trigger' => 'admin/view/sale/order_list/before',
            'action' => 'extension/myparcel/event/order.prepareList',
        ],
        [
            'code' => 'myparcel_admin_order_page',
            'description_key' => 'event_admin_order_page',
            'trigger' => 'admin/view/sale/order/before',
            'action' => 'extension/myparcel/event/order.prepareOrderPage',
        ],
        [
            'code' => 'myparcel_admin_order_detail',
            'description_key' => 'event_admin_order_detail',
            'trigger' => 'admin/view/sale/order_info/before',
            'action' => 'extension/myparcel/event/order.prepareInfo',
        ],
        [
            'code' => 'myparcel_order_after_add',
            'description_key' => 'event_order_after_add',
            'trigger' => 'catalog/model/checkout/order.addOrder/after',
            'action' => 'extension/myparcel/event/order.saveDeliveryOptions',
        ],
        [
            'code' => 'myparcel_order_after_edit',
            'description_key' => 'event_order_after_edit',
            'trigger' => 'catalog/model/checkout/order.editOrder/after',
            'action' => 'extension/myparcel/event/order.saveDeliveryOptions',
        ],
        [
            'code' => 'myparcel_product_form',
            'description_key' => 'event_product_form',
            'trigger' => 'admin/view/catalog/product_form/before',
            'action' => 'extension/myparcel/event/product.prepareForm',
        ],
        [
            'code' => 'myparcel_product_add',
            'description_key' => 'event_product_add',
            'trigger' => 'admin/model/catalog/product.addProduct/after',
            'action' => 'extension/myparcel/event/product.saveOnAdd',
        ],
        [
            'code' => 'myparcel_product_edit',
            'description_key' => 'event_product_edit',
            'trigger' => 'admin/model/catalog/product.editProduct/after',
            'action' => 'extension/myparcel/event/product.saveOnEdit',
        ],
    ];

    /** Memoised so the controller reuses one cache adapter per request. */
    private ?ContractDefinitionsCache $contractDefinitionsCache = null;

    private ?CarrierPresenter $carrierPresenter = null;

    private ?PluginStateStore $pluginStateStore = null;

    /**
     * Render the module settings page.
     */
    public function index(): void
    {
        $this->load->language('extension/myparcel/module/myparcel');

        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token']),
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module'),
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/myparcel/module/myparcel', 'user_token=' . $this->session->data['user_token']),
        ];

        $data['save'] = $this->url->link('extension/myparcel/module/myparcel.save', 'user_token=' . $this->session->data['user_token']);
        $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');

        // Cross-link to the shipping-method settings (rate, tax class and zones are configured there).
        $data['shipping_link'] = $this->url->link('extension/myparcel/shipping/myparcel', 'user_token=' . $this->session->data['user_token']);

        // Used to build AJAX URLs in JS the OpenCart-core way (avoids Twig escaping & to &amp; inside JS strings).
        $data['user_token'] = $this->session->data['user_token'];

        // OCMOD health check: warn until the modification is registered, enabled
        // and applied (Refresh leaves our slot markers in extension/ocmod/).
        $ocmodStatus = $this->ocmodStatus();
        $data['ocmod_message'] = $ocmodStatus !== null ? $this->language->get('text_ocmod_' . $ocmodStatus) : '';
        $data['modifications_link'] = $this->url->link('marketplace/modification', 'user_token=' . $this->session->data['user_token']);

        $settings = $this->settings();

        $data['module_myparcel_status'] = $this->config->get(SettingKeys::STATUS);
        $data['module_myparcel_api_key'] = $this->config->get(SettingKeys::API_KEY);
        $data['module_myparcel_environment'] = $settings->environment;
        $data['module_myparcel_default_package_type'] = $settings->defaultPackageType;
        $data['module_myparcel_label_format'] = $settings->labelFormat;
        $data['module_myparcel_label_position'] = $settings->labelPosition;
        $data['module_myparcel_default_length'] = $settings->fallbackLength;
        $data['module_myparcel_default_width'] = $settings->fallbackWidth;
        $data['module_myparcel_default_height'] = $settings->fallbackHeight;
        $data['module_myparcel_default_weight'] = $settings->fallbackWeight;
        $data['package_type_options'] = $this->packageTypeOptions();
        $data['label_format_options'] = Settings::labelFormats();
        $data['label_position_options'] = Settings::labelPositions();
        $data['module_myparcel_customs_product_fields'] = $settings->productFieldsEnabled;
        $data['module_myparcel_customs_default_country'] = $settings->defaultCountryOfOrigin;
        $data['module_myparcel_customs_default_hs_code'] = $settings->defaultCustomsCode;
        $data['customs_country_options'] = $this->customsCountryOptions();
        $data['checkout'] = $settings->checkout;
        $data['capabilities_summary'] = $this->contractDefinitionsSummary();
        $data['carriers'] = $this->carrierRows();

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/myparcel/module/myparcel', $data));
    }

    /**
     * Persist the settings; clears the cached contract definitions when the key or environment changes.
     */
    public function save(): void
    {
        OpenCartCompatibility::guardJsonOutput();
        $this->load->language('extension/myparcel/module/myparcel');

        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/myparcel/module/myparcel')) {
            $json['error'] = $this->language->get('error_permission');
        }

        if (!$json) {
            $this->load->model('setting/setting');

            $newKey = trim((string) ($this->request->post[SettingKeys::API_KEY] ?? ''));
            $newEnvironment = $this->normalizeEnvironment($this->request->post[SettingKeys::ENVIRONMENT] ?? null);

            $changed = $newKey !== (string)$this->config->get(SettingKeys::API_KEY)
                || $newEnvironment !== (string)$this->config->get(SettingKeys::ENVIRONMENT);

            $this->pluginStateStore()->save(SettingKeys::MODULE, [
                SettingKeys::STATUS => (int) ($this->request->post[SettingKeys::STATUS] ?? 0),
                SettingKeys::API_KEY => $newKey,
                SettingKeys::ENVIRONMENT => $newEnvironment,
                SettingKeys::DEFAULT_PACKAGE_TYPE => (string) (
                    $this->request->post[SettingKeys::DEFAULT_PACKAGE_TYPE] ?? ''
                ),
                SettingKeys::LABEL_FORMAT => (string) ($this->request->post[SettingKeys::LABEL_FORMAT] ?? ''),
                SettingKeys::LABEL_POSITION => (int) (
                    $this->request->post[SettingKeys::LABEL_POSITION] ?? Settings::DEFAULT_LABEL_POSITION
                ),
                SettingKeys::DEFAULT_LENGTH => (int) ($this->request->post[SettingKeys::DEFAULT_LENGTH] ?? 0),
                SettingKeys::DEFAULT_WIDTH => (int) ($this->request->post[SettingKeys::DEFAULT_WIDTH] ?? 0),
                SettingKeys::DEFAULT_HEIGHT => (int) ($this->request->post[SettingKeys::DEFAULT_HEIGHT] ?? 0),
                SettingKeys::DEFAULT_WEIGHT => (int) ($this->request->post[SettingKeys::DEFAULT_WEIGHT] ?? 0),
                SettingKeys::CUSTOMS_PRODUCT_FIELDS => (int) (
                    $this->request->post[SettingKeys::CUSTOMS_PRODUCT_FIELDS] ?? 0
                ),
                SettingKeys::CUSTOMS_DEFAULT_COUNTRY => (string) (
                    $this->request->post[SettingKeys::CUSTOMS_DEFAULT_COUNTRY] ?? ''
                ),
                SettingKeys::CUSTOMS_DEFAULT_HS_CODE => (string) (
                    $this->request->post[SettingKeys::CUSTOMS_DEFAULT_HS_CODE] ?? ''
                ),
            ]);

            // Checkout widget config is its own group (not key/environment-tied), so always persist it.
            $postedCheckout = $this->request->post[SettingKeys::CHECKOUT] ?? [];
            $this->model_setting_setting->editSetting(SettingKeys::CHECKOUT, [
                SettingKeys::CHECKOUT => CheckoutSettings::fromArray(
                    is_array($postedCheckout) ? $postedCheckout : []
                )->toArray(),
            ]);

            // Contract definitions and derived carrier settings are tied to a specific key + environment.
            if ($changed) {
                $this->model_setting_setting->deleteSetting(SettingKeys::CONTRACT_DEFINITIONS);
                $this->model_setting_setting->deleteSetting(SettingKeys::CARRIERS);
            } else {
                $this->storeCarrierSettings($this->postedCarrierSettings());
            }

            $json['success'] = $this->language->get('text_success');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * AJAX: validate the entered API key against MyParcel; returns the result as JSON.
     */
    public function apiKeyCheck(): void
    {
        OpenCartCompatibility::guardJsonOutput();
        $this->load->language('extension/myparcel/module/myparcel');
        $this->response->addHeader('Content-Type: application/json');

        if (!$this->user->hasPermission('modify', 'extension/myparcel/module/myparcel')) {
            $this->respondInvalid('permission', $this->language->get('error_permission'));
            return;
        }

        $apiKey = trim((string)($this->request->post['api_key'] ?? ''));
        $acceptance = Environment::isAcceptance($this->request->post['environment'] ?? null);

        if ($apiKey === '') {
            $this->respondInvalid('invalid_key', $this->language->get('text_api_key_invalid'));
            return;
        }

        try {
            $principal = (new WhoamiService())->getWhoami($apiKey, $acceptance);
            $shopIds = $principal->getShopIds() ?? [];

            $this->response->setOutput(json_encode([
                'valid' => true,
                'account_id' => $principal->getAccountId(),
                'shop_id' => $shopIds[0] ?? null,
                'platform' => $principal->getPlatform(),
                'message' => $this->language->get('text_api_key_valid'),
            ]));
        } catch (\Throwable $e) {
            $reason = $this->classifyApiError($e);
            $this->respondInvalid($reason, $this->language->get(
                $reason === 'invalid_key' ? 'text_api_key_invalid' : 'text_api_key_transport'
            ));
        }
    }

    /**
     * AJAX: fetch the account's carrier configuration and cache it; returns the result as JSON.
     */
    public function importCapabilities(): void
    {
        OpenCartCompatibility::guardJsonOutput();
        $this->load->language('extension/myparcel/module/myparcel');
        $this->response->addHeader('Content-Type: application/json');

        if (!$this->user->hasPermission('modify', 'extension/myparcel/module/myparcel')) {
            $this->respondInvalid('permission', $this->language->get('error_permission'));
            return;
        }

        // Import uses the saved key/environment, so the admin must save first.
        $apiKey = trim((string)$this->config->get(SettingKeys::API_KEY));
        $acceptance = Environment::isAcceptance($this->config->get(SettingKeys::ENVIRONMENT));

        if ($apiKey === '') {
            $this->respondInvalid('invalid_key', $this->language->get('text_api_key_invalid'));
            return;
        }

        try {
            $principal = (new WhoamiService())->getWhoami($apiKey, $acceptance);
            $shopIds = $principal->getShopIds() ?? [];
            $resolvedShopId = isset($shopIds[0]) ? (int) $shopIds[0] : 0;
            $shopId = $resolvedShopId > 0 ? $resolvedShopId : null;
            $accountId = (string) $principal->getAccountId();
            $environment = $acceptance ? Environment::ACCEPTANCE : Environment::PRODUCTION;
            $items = (new CapabilitiesService())->getContractDefinitions($apiKey, $acceptance);
            $previous = $this->contractDefinitions();
            $previousDefault = null;

            if ($previous !== null
                && $previous->accountId === $accountId
                && $previous->shopId === $shopId
                && $previous->environment === $environment
            ) {
                $previousDefault = $previous->defaultCarrier;
            }

            try {
                $catalog = (new CarrierCatalogService())->getCatalog($acceptance);
            } catch (\Throwable $e) {
                // A public metadata outage must not make account contracts unavailable. Retain
                // the prior catalog for this environment so existing carrier logos stay usable.
                $catalog = $previous !== null && $previous->environment === $environment
                    ? $previous->carrierCatalog
                    : CarrierCatalog::empty();
                $this->log->write(sprintf(
                    '[MyParcel] Carrier catalog refresh failed exception=%s message=%s',
                    $e::class,
                    str_replace(["\r", "\n"], ' ', mb_substr($e->getMessage(), 0, 300))
                ));
            }

            $definitions = ContractDefinitions::fromArray([
                'schema_version' => ContractDefinitions::SCHEMA_VERSION,
                'environment' => $environment,
                'account_id' => $accountId,
                'shop_id' => $shopId,
                'platform' => $principal->getPlatform(),
                'contract_definitions' => $items,
                'carrier_catalog' => $catalog->toArray(),
                'fetched_at' => time(),
                'last_error' => null,
            ]);
            $apiDefaultId = $shopId !== null
                ? (new DefaultCarrierService())->getDefaultCarrierId($apiKey, $acceptance, $shopId)
                : null;
            $apiDefault = $apiDefaultId !== null
                ? (new CarrierResolver())->valueForLegacyId($apiDefaultId, $catalog)
                : null;
            $definitions = $definitions->withDefaultCarrier(DefaultCarrierService::resolveAvailable(
                $apiDefault,
                $previousDefault,
                $definitions->contracts
            ));

            $this->contractDefinitionsCache()->store($this->settingModel(), $definitions);
            $this->storeCarrierSettings(
                (new CarrierSettingsBuilder())->mergeAdminSettings(
                    $definitions->toArray(),
                    $this->carrierSettings()
                )
            );

            $this->response->setOutput(json_encode([
                'valid' => true,
                'carrier_count' => count($items),
                'message' => $this->language->get('text_capabilities_imported'),
            ]));
        } catch (\Throwable $e) {
            $reason = $this->classifyApiError($e);
            $this->contractDefinitionsCache()->storeLastError($this->settingModel(), $reason);
            $this->respondInvalid($reason, $this->language->get(
                $reason === 'invalid_key' ? 'text_api_key_invalid' : 'text_capabilities_error'
            ));
        }
    }

    /**
     * Restore durable settings, migrate the schema and register all hooks.
     */
    public function install(): void
    {
        $this->load->language('extension/myparcel/module/myparcel');
        $this->assertCompatibleVersion();

        try {
            $this->load->model('setting/setting');
            $this->pluginStateStore()->restore(SettingKeys::MODULE, $this->moduleDefaults());

            (new SchemaMigrator($this->pluginStateStore()))->migrate([
                1 => function (): void {
                    (new OrderTable())->ensure($this->db, DB_PREFIX);
                    (new ShipmentTable())->ensure($this->db, DB_PREFIX);
                    (new ProductTable())->ensure($this->db, DB_PREFIX);
                },
            ]);

            $this->registerEvents();
            $this->grantPermissions();
            $this->enableModification();
        } catch (\Throwable $e) {
            $this->rollbackInstallation();
            throw $e;
        }
    }

    /** Register every event idempotently with a translated admin description. */
    private function registerEvents(): void
    {
        $this->load->model('setting/event');
        $this->removeEvents();

        foreach (self::EVENTS as $event) {
            $this->model_setting_event->addEvent([
                'code' => $event['code'],
                'description' => $this->language->get($event['description_key']),
                'trigger' => $event['trigger'],
                'action' => $event['action'],
                'status' => 1,
                'sort_order' => 0,
            ]);
        }
    }

    /**
     * Remove runtime hooks and permissions while preserving configuration and data.
     */
    public function uninstall(): void
    {
        $this->removePermissions();
        $this->removeEvents();
    }

    /** Delete all MyParcel event registrations, including partially installed ones. */
    private function removeEvents(): void
    {
        $this->load->model('setting/event');

        foreach (self::EVENTS as $event) {
            $this->model_setting_event->deleteEventByCode($event['code']);
        }
    }

    /** Reject unsupported OpenCart versions and undo the core's early registration. */
    private function assertCompatibleVersion(): void
    {
        if (version_compare(VERSION, OpenCartCompatibility::MINIMUM_VERSION, '>=')) {
            return;
        }

        $this->removePermissions();
        $this->unregisterModule();

        throw new \RuntimeException(sprintf(
            $this->language->get('error_opencart_version'),
            OpenCartCompatibility::MINIMUM_VERSION,
            VERSION
        ));
    }

    /**
     * Defaults used only for keys missing from both active and durable settings.
     *
     * @return array<string, int|string>
     */
    private function moduleDefaults(): array
    {
        return [
            SettingKeys::STATUS => 0,
            SettingKeys::API_KEY => '',
            SettingKeys::ENVIRONMENT => Environment::PRODUCTION,
            SettingKeys::DEFAULT_PACKAGE_TYPE => CarrierSettingsBuilder::DEFAULT_PACKAGE_TYPE,
            SettingKeys::LABEL_FORMAT => Settings::DEFAULT_LABEL_FORMAT,
            SettingKeys::LABEL_POSITION => Settings::DEFAULT_LABEL_POSITION,
            SettingKeys::DEFAULT_LENGTH => 0,
            SettingKeys::DEFAULT_WIDTH => 0,
            SettingKeys::DEFAULT_HEIGHT => 0,
            SettingKeys::DEFAULT_WEIGHT => 0,
            SettingKeys::CUSTOMS_PRODUCT_FIELDS => 0,
            SettingKeys::CUSTOMS_DEFAULT_COUNTRY => '',
            SettingKeys::CUSTOMS_DEFAULT_HS_CODE => Settings::DEFAULT_CUSTOMS_CODE,
        ];
    }

    /** Grant the installing admin group access to the module and action endpoints. */
    private function grantPermissions(): void
    {
        $this->load->model('user/user_group');
        $this->removePermissions();
        $userGroupId = (int) $this->user->getGroupId();

        foreach ($this->permissionRoutes() as $route) {
            $this->model_user_user_group->addPermission($userGroupId, 'access', $route);
            $this->model_user_user_group->addPermission($userGroupId, 'modify', $route);
        }
    }

    /** Remove MyParcel route permissions from the current admin group. */
    private function removePermissions(): void
    {
        $this->load->model('user/user_group');
        $userGroupId = (int) $this->user->getGroupId();

        foreach ($this->permissionRoutes() as $route) {
            $this->model_user_user_group->removePermission($userGroupId, 'access', $route);
            $this->model_user_user_group->removePermission($userGroupId, 'modify', $route);
        }
    }

    /**
     * Routes whose permissions are owned by the module lifecycle.
     *
     * @return list<string>
     */
    private function permissionRoutes(): array
    {
        return [
            'extension/myparcel/module/myparcel',
            'extension/myparcel/shipment/myparcel',
        ];
    }

    /** Enable the installer-registered modification; Refresh applies it afterwards. */
    private function enableModification(): void
    {
        $this->load->model('setting/modification');
        $modification = $this->model_setting_modification->getModificationByCode('myparcel');

        if ($modification && !$modification['status']) {
            $this->model_setting_modification->editStatus((int) $modification['modification_id'], true);
        }
    }

    /** Undo module-side effects if installation stops after core registration. */
    private function rollbackInstallation(): void
    {
        $this->removeEvents();
        $this->removePermissions();
        $this->unregisterModule();
    }

    /** Remove only the core module registration; durable MyParcel state is unaffected. */
    private function unregisterModule(): void
    {
        $this->load->model('setting/extension');
        $this->model_setting_extension->uninstall('module', 'myparcel');
    }

    /**
     * Normalise the posted environment value to a supported MyParcel environment.
     */
    private function normalizeEnvironment(?string $environment): string
    {
        return Environment::normalize($environment);
    }

    /**
     * Typed view of the saved module settings.
     */
    private function settings(): Settings
    {
        return Settings::fromConfig($this->config);
    }

    /**
     * Selectable default package types, labelled via translation key with a humanised fallback.
     *
     * @return array<string, string> slug => label
     */
    private function packageTypeOptions(): array
    {
        $options = [];

        foreach (OrderToShipmentMapper::packageTypeSlugs() as $slug) {
            $key = "text_package_type_$slug";
            $label = $this->language->get($key);
            $options[$slug] = $label !== $key ? $label : ucwords(str_replace('_', ' ', $slug));
        }

        return $options;
    }

    /**
     * Country options for the customs default country of origin, as ISO-2 code => name.
     *
     * @return array<string, string>
     */
    private function customsCountryOptions(): array
    {
        $this->load->model('localisation/country');

        return CountryOptions::fromOcCountries($this->model_localisation_country->getCountries());
    }

    /**
     * Classify a failed API call: 'invalid_key' for HTTP 401, 'transport_error'
     * otherwise. The exception message is never exposed (it may echo the key).
     */
    private function classifyApiError(\Throwable $e): string
    {
        $unauthorized = ($e instanceof IamApiException || $e instanceof CoreApiException)
            && $e->getCode() === 401;

        return $unauthorized ? 'invalid_key' : 'transport_error';
    }

    /**
     * Output a JSON error with a reason code and message.
     */
    private function respondInvalid(string $reason, string $message): void
    {
        $this->response->setOutput(json_encode([
            'valid' => false,
            'reason' => $reason,
            'message' => $message,
        ]));
    }

    /**
     * The cached contract-definitions blob, or null when nothing is cached.
     */
    private function contractDefinitions(): ?ContractDefinitions
    {
        return $this->contractDefinitionsCache()->get($this->settingModel());
    }

    /**
     * Build the carrier rows for the settings table (label, enabled state, services).
     *
     * @return list<array{
     *     slug: string,
     *     carrier: string,
     *     label: string,
     *     logo: string,
     *     enabled: bool,
     *     services: list<array{key: string, label: string, enabled: bool}>
     * }>
     */
    private function carrierRows(): array
    {
        $definitions = $this->contractDefinitions();

        if ($definitions === null) {
            return [];
        }

        $blob = $definitions->toArray();
        $builder = new CarrierSettingsBuilder();
        $this->carrierPresenter = new CarrierPresenter($definitions->carrierCatalog);
        $supported = $builder->supportedCarriers($blob);
        $settings = $builder->mergeAdminSettings($blob, $this->carrierSettings());
        $rows = [];

        foreach ($supported as $slug => $carrier) {
            $rowSettings = $settings[$slug] ?? ['enabled' => false, 'services' => []];
            $enabledServices = $rowSettings['services'] ?? [];
            $services = [];

            foreach ($carrier['services'] as $service => $isSupported) {
                if (!$isSupported) {
                    continue;
                }

                $services[] = [
                    'key' => $service,
                    'label' => $this->serviceLabel($service),
                    'enabled' => in_array($service, $enabledServices, true),
                ];
            }

            $rows[] = [
                'slug' => $slug,
                'carrier' => $carrier['carrier'],
                'label' => $this->carrierLabel($carrier['carrier']),
                'logo' => $this->carrierLogo($slug),
                'enabled' => !empty($rowSettings['enabled']),
                'services' => $services,
            ];
        }

        return $rows;
    }

    /**
     * The stored per-carrier admin config (enabled + services), keyed by slug.
     *
     * @return array<string, array{enabled: bool, services: list<string>}>
     */
    private function carrierSettings(): array
    {
        $this->load->model('setting/setting');
        $stored = $this->model_setting_setting->getSetting(SettingKeys::CARRIERS);
        $settings = $stored[SettingKeys::CARRIERS] ?? [];

        return (new CarrierSettingsBuilder())->normaliseAdminSettings(is_array($settings) ? $settings : []);
    }

    /**
     * Persist the per-carrier admin config.
     *
     * @param array<string, array{enabled: bool, services: list<string>}> $settings
     */
    private function storeCarrierSettings(array $settings): void
    {
        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting(SettingKeys::CARRIERS, [
            SettingKeys::CARRIERS => $settings,
        ]);
    }

    /**
     * The posted carrier config, normalised and re-merged against the cached blob.
     *
     * @return array<string, array{enabled: bool, services: list<string>}>
     */
    private function postedCarrierSettings(): array
    {
        $posted = $this->request->post[SettingKeys::CARRIERS] ?? [];
        $settings = (new CarrierSettingsBuilder())->normaliseAdminSettings(is_array($posted) ? $posted : []);
        $definitions = $this->contractDefinitions();

        return $definitions === null
            ? $settings
            : (new CarrierSettingsBuilder())->mergeAdminSettings($definitions->toArray(), $settings);
    }

    /**
     * OCMOD state for the settings-page warning: 'missing', 'disabled', 'stale'
     * or null when the modification is registered, enabled and applied.
     */
    private function ocmodStatus(): ?string
    {
        $this->load->model('setting/modification');
        $modification = $this->model_setting_modification->getModificationByCode('myparcel');

        if (!$modification) {
            return 'missing';
        }

        if (!$modification['status']) {
            return 'disabled';
        }

        if (($modification['version'] ?? '') !== self::OCMOD_REVISION) {
            return 'stale';
        }

        // Refresh writes every patched core template below extension/ocmod/.
        // Revisioned markers make an old cache distinguishable after updates.
        foreach (self::OCMOD_SLOTS as $file => $slots) {
            $path = DIR_EXTENSION . 'ocmod/' . $file;
            $contents = is_file($path) ? file_get_contents($path) : false;

            if (!is_string($contents)) {
                return 'stale';
            }

            foreach ($slots as $slot) {
                if (!str_contains($contents, "myparcel-slot:$slot@" . self::OCMOD_REVISION)) {
                    return 'stale';
                }
            }
        }

        return null;
    }

    /**
     * Display label for a carrier, via the shared presenter.
     */
    private function carrierLabel(string $carrier): string
    {
        $this->carrierPresenter ??= new CarrierPresenter();

        return $this->carrierPresenter->nameForValue($carrier);
    }

    /** Return the public logo URL for a Delivery Options carrier slug. */
    private function carrierLogo(string $slug): string
    {
        $this->carrierPresenter ??= new CarrierPresenter();

        return $this->carrierPresenter->logoUrl($slug);
    }

    /**
     * Display label for a carrier service, from its translation key (falls back to the raw name).
     */
    private function serviceLabel(string $service): string
    {
        $key = 'text_carrier_service_' . $service;
        $label = $this->language->get($key);

        return $label !== $key ? $label : $service;
    }

    /**
     * Summary of the cached contract definitions for the settings page, or null when none.
     *
     * @return array{
     *     carrier_count: int,
     *     fetched_at: int|null,
     *     environment: string|null,
     *     last_error: array{timestamp: int, reason: string}|null
     * }|null
     */
    private function contractDefinitionsSummary(): ?array
    {
        return $this->contractDefinitionsCache()->summary($this->contractDefinitions());
    }

    /**
     * Shared, memoised contract-definitions cache adapter.
     */
    private function contractDefinitionsCache(): ContractDefinitionsCache
    {
        return $this->contractDefinitionsCache ??= new ContractDefinitionsCache();
    }

    /** Shared adapter for the durable update snapshot and active setting groups. */
    private function pluginStateStore(): PluginStateStore
    {
        return $this->pluginStateStore ??= new PluginStateStore($this->settingModel());
    }

    /**
     * Load and return OpenCart's setting model.
     *
     * @return object model_setting_setting
     */
    private function settingModel(): object
    {
        $this->load->model('setting/setting');

        return $this->model_setting_setting;
    }
}
