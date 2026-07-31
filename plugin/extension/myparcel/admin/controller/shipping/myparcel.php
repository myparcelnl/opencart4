<?php

namespace Opencart\Admin\Controller\Extension\Myparcel\Shipping;

use MyParcelNL\OpenCart\Core\Support\OpenCartCompatibility;
use MyParcelNL\OpenCart\Core\Settings\PluginStateStore;
use MyParcelNL\OpenCart\Core\Settings\SettingKeys;

require_once DIR_EXTENSION . 'myparcel/bootstrap.php';

/** Admin settings and lifecycle hooks for the MyParcel shipping method. */
class Myparcel extends \Opencart\System\Engine\Controller
{
    /** FR-010 MVP flat shipping rate; the merchant overrides it in the shipping settings. */
    private const DEFAULT_RATE = 6.95;

    /** Render the shipping-method settings page. */
    public function index(): void
    {
        $this->load->language('extension/myparcel/shipping/myparcel');

        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token']),
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping'),
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/myparcel/shipping/myparcel', 'user_token=' . $this->session->data['user_token']),
        ];

        $data['save'] = $this->url->link('extension/myparcel/shipping/myparcel.save', 'user_token=' . $this->session->data['user_token']);
        $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping');

        // Cross-link to the module settings (API key and carrier configuration are managed there).
        $data['module_link'] = $this->url->link('extension/myparcel/module/myparcel', 'user_token=' . $this->session->data['user_token']);

        $data['shipping_myparcel_name'] = $this->config->get(SettingKeys::SHIPPING_NAME) ?: $this->language->get('text_default_name');
        $data['shipping_myparcel_rate'] = $this->config->get(SettingKeys::SHIPPING_RATE);
        $data['shipping_myparcel_tax_class_id'] = $this->config->get(SettingKeys::SHIPPING_TAX_CLASS_ID);

        $this->load->model('localisation/tax_class');
        $data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();

        $data['shipping_myparcel_geo_zone_id'] = $this->config->get(SettingKeys::SHIPPING_GEO_ZONE_ID);

        $this->load->model('localisation/geo_zone');
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        $data['shipping_myparcel_status'] = $this->config->get(SettingKeys::SHIPPING_STATUS);
        $data['shipping_myparcel_sort_order'] = $this->config->get(SettingKeys::SHIPPING_SORT_ORDER);

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/myparcel/shipping/myparcel', $data));
    }

    /** Persist the active settings and their durable update snapshot. */
    public function save(): void
    {
        $this->load->language('extension/myparcel/shipping/myparcel');

        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/myparcel/shipping/myparcel')) {
            $json['error'] = $this->language->get('error_permission');
        }

        if (!$json) {
            $this->load->model('setting/setting');

            (new PluginStateStore($this->model_setting_setting))->save(SettingKeys::SHIPPING, [
                SettingKeys::SHIPPING_NAME => trim((string) (
                    $this->request->post[SettingKeys::SHIPPING_NAME] ?? ''
                )),
                SettingKeys::SHIPPING_RATE => (float) (
                    $this->request->post[SettingKeys::SHIPPING_RATE] ?? 0
                ),
                SettingKeys::SHIPPING_TAX_CLASS_ID => (int) (
                    $this->request->post[SettingKeys::SHIPPING_TAX_CLASS_ID] ?? 0
                ),
                SettingKeys::SHIPPING_GEO_ZONE_ID => (int) (
                    $this->request->post[SettingKeys::SHIPPING_GEO_ZONE_ID] ?? 0
                ),
                SettingKeys::SHIPPING_STATUS => (int) (
                    $this->request->post[SettingKeys::SHIPPING_STATUS] ?? 0
                ),
                SettingKeys::SHIPPING_SORT_ORDER => (int) (
                    $this->request->post[SettingKeys::SHIPPING_SORT_ORDER] ?? 0
                ),
            ]);

            $json['success'] = $this->language->get('text_success');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /** Restore durable settings and grant the required admin permissions. */
    public function install(): void
    {
        $this->load->language('extension/myparcel/shipping/myparcel');
        $this->assertCompatibleVersion();

        try {
            $this->load->model('setting/setting');
            (new PluginStateStore($this->model_setting_setting))->restore(SettingKeys::SHIPPING, $this->defaults());
            $this->grantPermissions();
        } catch (\Throwable $e) {
            $this->rollbackInstallation();
            throw $e;
        }
    }

    /** Remove runtime permissions while preserving update-safe shipping settings. */
    public function uninstall(): void
    {
        $this->removePermissions();
    }

    /** Reject unsupported OpenCart versions and undo the core's early registration. */
    private function assertCompatibleVersion(): void
    {
        if (version_compare(VERSION, OpenCartCompatibility::MINIMUM_VERSION, '>=')) {
            return;
        }

        $this->rollbackInstallation();

        throw new \RuntimeException(sprintf(
            $this->language->get('error_opencart_version'),
            OpenCartCompatibility::MINIMUM_VERSION,
            VERSION
        ));
    }

    /**
     * Defaults used only when no active or durable value exists.
     *
     * @return array<string, float|int|string>
     */
    private function defaults(): array
    {
        return [
            SettingKeys::SHIPPING_NAME => $this->language->get('text_default_name'),
            SettingKeys::SHIPPING_RATE => self::DEFAULT_RATE,
            SettingKeys::SHIPPING_TAX_CLASS_ID => 0,
            SettingKeys::SHIPPING_GEO_ZONE_ID => 0,
            SettingKeys::SHIPPING_STATUS => 0,
            SettingKeys::SHIPPING_SORT_ORDER => 0,
        ];
    }

    /** Keep the current admin group's shipping-route permissions idempotent. */
    private function grantPermissions(): void
    {
        $this->load->model('user/user_group');
        $this->removePermissions();
        $userGroupId = (int) $this->user->getGroupId();
        $route = 'extension/myparcel/shipping/myparcel';
        $this->model_user_user_group->addPermission($userGroupId, 'access', $route);
        $this->model_user_user_group->addPermission($userGroupId, 'modify', $route);
    }

    /** Remove the shipping-route permissions added by OpenCart and this installer. */
    private function removePermissions(): void
    {
        $this->load->model('user/user_group');
        $userGroupId = (int) $this->user->getGroupId();
        $route = 'extension/myparcel/shipping/myparcel';
        $this->model_user_user_group->removePermission($userGroupId, 'access', $route);
        $this->model_user_user_group->removePermission($userGroupId, 'modify', $route);
    }

    /** Undo the core registration if install cannot complete. */
    private function rollbackInstallation(): void
    {
        $this->removePermissions();
        $this->load->model('setting/extension');
        $this->model_setting_extension->uninstall('shipping', 'myparcel');
    }
}
