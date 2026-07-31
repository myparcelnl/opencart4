<?php

namespace Opencart\Admin\Controller\Extension\Myparcel\Event;

use MyParcelNL\OpenCart\Core\Dto\DeliveryOptionsDto;
use MyParcelNL\OpenCart\Core\Service\Carrier\CarrierPresenter;
use MyParcelNL\OpenCart\Core\Service\ContractDefinitionsCache;
use MyParcelNL\OpenCart\Core\Settings\SettingKeys;

require_once DIR_EXTENSION . 'myparcel/bootstrap.php';

/**
 * Fills the MyParcel view data for the admin order pages. The OCMOD slots in the
 * core templates (ocmod/myparcel.ocmod.xml) render what these handlers put in
 * $data; the handlers never touch rendered output.
 */
class Order extends \Opencart\System\Engine\Controller
{
    private const EXTENSION_ROUTE = 'extension/myparcel/';

    private const LANGUAGE_ROUTE = self::EXTENSION_ROUTE . 'module/myparcel';

    private const SHIPMENT_MODEL_ROUTE = self::EXTENSION_ROUTE . 'shipment/myparcel';

    private const SHIPMENT_ACTION_ROUTE = self::SHIPMENT_MODEL_ROUTE . '.';

    private const EVENT_VIEW_ROUTE = self::EXTENSION_ROUTE . 'event/';

    private const ACTIONS_SCRIPT = 'admin/view/javascript/myparcel/order-actions.js';

    private const ORDER_INFO_ROUTE = 'sale/order.info';

    private ?CarrierPresenter $carriers = null;

    /**
     * Event `admin/view/sale/order/before`: the i18n payload + click handler,
     * loaded once on the outer list page so it survives the list's AJAX reloads.
     *
     * @param string $route OpenCart view route.
     * @param array<string, mixed> $data Order-page template data.
     * @param string $code Optional inline Twig source supplied by OpenCart.
     * @param string $output Rendered output override supplied by OpenCart.
     */
    public function prepareOrderPage(&$route, &$data, &$code, &$output): void
    {
        if (!$this->config->get(SettingKeys::STATUS)) {
            return;
        }

        $data['myparcel_order_assets'] = $this->assets();
    }

    /**
     * Event `admin/view/sale/order_list/before`: the action buttons per order row.
     *
     * @param string $route OpenCart view route.
     * @param array<string, mixed> $data Order-list template data.
     * @param string $code Optional inline Twig source supplied by OpenCart.
     * @param string $output Rendered output override supplied by OpenCart.
     */
    public function prepareList(&$route, &$data, &$code, &$output): void
    {
        if (!$this->config->get(SettingKeys::STATUS) || empty($data['orders']) || !is_array($data['orders'])) {
            return;
        }

        $ids = [];

        foreach ($data['orders'] as $order) {
            $ids[] = (int) ($order['order_id'] ?? 0);
        }

        $this->loadShipmentModel();
        $states = $this->model_extension_myparcel_shipment_myparcel->statesByOrderIds($ids);
        $userToken = $this->userToken();

        foreach ($data['orders'] as &$order) {
            $orderId = (int) ($order['order_id'] ?? 0);
            $order['myparcel_actions'] = $this->actions($orderId, $states[$orderId] ?? null, $userToken);
        }
        unset($order);
    }

    /**
     * Event `admin/view/sale/order_info/before`: toolbar actions, the last export
     * error, the shipments card and the click-handler assets.
     *
     * @param string $route OpenCart view route.
     * @param array<string, mixed> $data Order-info template data.
     * @param string $code Optional inline Twig source supplied by OpenCart.
     * @param string $output Rendered output override supplied by OpenCart.
     */
    public function prepareInfo(&$route, &$data, &$code, &$output): void
    {
        if (!$this->config->get(SettingKeys::STATUS)) {
            return;
        }

        $orderId = (int) ($this->request->get['order_id'] ?? 0);

        if ($orderId <= 0) {
            return;
        }

        $this->loadShipmentModel();
        $orderRow = $this->model_extension_myparcel_shipment_myparcel->getOrderRow($orderId);
        $shipments = $this->model_extension_myparcel_shipment_myparcel->getShipmentsByOrderId($orderId);
        $userToken = $this->userToken();
        $lastError = trim((string) ($orderRow['last_error'] ?? ''));

        // The toolbar buttons act on the latest shipment; the card below lists them all.
        $latest = $shipments[0] ?? null;
        $state = $orderRow === null ? null : [
            'shipment_id' => 0,
            'shipment_count' => 0,
            'status' => '',
            'carrier' => '',
            'delivery_options' => (string) ($orderRow['delivery_options'] ?? ''),
            'last_error' => $lastError,
        ];

        if ($latest !== null && !empty($latest['shipment_id'])) {
            $state = [
                'shipment_id' => (int) $latest['shipment_id'],
                'shipment_count' => count($shipments),
                'status' => (string) ($latest['status'] ?? ''),
                'carrier' => (string) ($latest['carrier'] ?? ''),
                'delivery_options' => (string) ($orderRow['delivery_options'] ?? ''),
                'last_error' => $lastError,
            ];
        }

        $tracktrace = null;

        if ($latest !== null && !empty($latest['tracktrace_url'])) {
            $tracktrace = [
                'url' => (string) $latest['tracktrace_url'],
                'barcode' => (string) ($latest['barcode'] ?? ''),
            ];
        }

        $data['myparcel_order_toolbar'] = $this->actions($orderId, $state, $userToken, $tracktrace);
        $data['myparcel_order_content'] = $this->infoContent($orderId, $shipments, $lastError, $userToken);
        $data['myparcel_order_assets'] = $this->assets();
    }

    /**
     * Render the action cluster for one order: export always, label + track for
     * the latest shipment once there is one, plus count and carrier badges.
     *
     * @param array{
     *     shipment_id: int,
     *     shipment_count: int,
     *     carrier: string,
     *     delivery_options: string,
     *     last_error: string
     * }|null $state
     * @param array{url: string, barcode: string}|null $tracktrace
     */
    private function actions(int $orderId, ?array $state, string $userToken, ?array $tracktrace = null): string
    {
        $this->loadLanguage();

        $exported = $state !== null && $state['shipment_id'] > 0;
        $lastError = trim((string) ($state['last_error'] ?? ''));
        $defaultTitle = $this->language->get($exported ? 'button_export_again' : 'button_export_order');

        // Once a shipment exists, the export button says so and asks the click
        // handler to confirm before appending another shipment.
        $buttons = [$this->button(
            'export',
            $orderId,
            $userToken,
            $lastError !== '' ? $lastError : $defaultTitle,
            0,
            $exported,
            $lastError !== ''
        )];

        if ($exported) {
            $shipmentId = (int) $state['shipment_id'];
            $buttons[] = $this->button(
                'label',
                $orderId,
                $userToken,
                sprintf($this->language->get('button_label_latest'), $shipmentId)
            );
            $buttons[] = $this->button(
                'trackTrace',
                $orderId,
                $userToken,
                sprintf($this->language->get('button_track_latest'), $shipmentId)
            );
        }

        $count = (int) ($state['shipment_count'] ?? 0);
        $countBadge = null;

        // Link multi-shipment orders to the full shipment list on the order detail page.
        if ($count >= 2) {
            $countBadge = [
                'href' => $this->url->link(self::ORDER_INFO_ROUTE, "user_token=$userToken&order_id=$orderId", true),
                'label' => sprintf($this->language->get('text_shipment_count'), $count),
                'title' => $this->language->get('text_shipment_count_help'),
            ];
        }

        return $this->renderActions([
            'buttons' => $buttons,
            'tracktrace' => $tracktrace,
            'count_badge' => $countBadge,
            'carrier' => $this->carrierBadge($this->carrierSlug($state)),
        ]);
    }

    /**
     * Render the order_actions partial. Buttons and the count badge are
     * pre-rendered through load->view because OpenCart's per-render Twig
     * loader cannot resolve {% include %} route names for extension templates.
     *
     * @param array{
     *     buttons: list<array{
     *         href: string,
     *         action: string,
     *         order_id: int,
     *         title: string,
     *         confirm: bool,
     *         failed: bool
     *     }>,
     *     tracktrace: array{url: string, barcode: string}|null,
     *     count_badge: array{href: string, label: string, title: string}|null,
     *     carrier: array{name: string, logo: string}|null
     * } $vars
     */
    private function renderActions(array $vars): string
    {
        $vars['buttons'] = array_map(
            fn (array $button): string => $this->load->view(self::EVENT_VIEW_ROUTE . 'order_action_button', ['button' => $button]),
            $vars['buttons']
        );
        $vars['count_badge'] = $vars['count_badge'] !== null
            ? $this->load->view(self::EVENT_VIEW_ROUTE . 'order_shipment_count_badge', ['badge' => $vars['count_badge']])
            : null;

        return $this->load->view(self::EVENT_VIEW_ROUTE . 'order_actions', $vars);
    }

    /**
     * Semantic data for one action. Its visual treatment belongs to Twig.
     *
     * @return array{
     *     href: string,
     *     action: string,
     *     order_id: int,
     *     title: string,
     *     confirm: bool,
     *     failed: bool
     * }
     */
    private function button(
        string $method,
        int $orderId,
        string $userToken,
        string $title,
        int $shipmentId = 0,
        bool $confirmRepeat = false,
        bool $failed = false
    ): array
    {
        $query = "user_token=$userToken&order_id=$orderId";

        if ($shipmentId > 0) {
            $query .= "&shipment_id=$shipmentId";
        }

        return [
            'href' => $this->url->link(self::SHIPMENT_ACTION_ROUTE . $method, $query, true),
            'action' => $method,
            'order_id' => $orderId,
            'title' => $title,
            'confirm' => $confirmRepeat,
            'failed' => $failed,
        ];
    }

    /**
     * Render the order-detail extras: the last export error and the card listing
     * every shipment with per-shipment label and track actions.
     *
     * @param list<array{
     *     id: int|string,
     *     order_id: int|string,
     *     shipment_id: int|string|null,
     *     reference: string|null,
     *     carrier: string|null,
     *     barcode: string|null,
     *     tracktrace_url: string|null,
     *     status: string,
     *     created_at: string,
     *     updated_at: string
     * }> $shipments
     */
    private function infoContent(int $orderId, array $shipments, string $lastError, string $userToken): string
    {
        $this->loadLanguage();

        $rows = [];

        foreach ($shipments as $shipment) {
            $shipmentId = (int) ($shipment['shipment_id'] ?? 0);

            $actions = $this->renderActions([
                'buttons' => [
                    $this->button(
                        'label',
                        $orderId,
                        $userToken,
                        sprintf($this->language->get('button_label_shipment'), $shipmentId),
                        $shipmentId
                    ),
                    $this->button(
                        'trackTrace',
                        $orderId,
                        $userToken,
                        sprintf($this->language->get('button_track_shipment'), $shipmentId),
                        $shipmentId
                    ),
                ],
                'tracktrace' => null,
                'count_badge' => null,
                'carrier' => null,
            ]);

            $rows[] = [
                'id' => $shipmentId,
                'barcode' => (string) ($shipment['barcode'] ?? ''),
                'track_url' => (string) ($shipment['tracktrace_url'] ?? ''),
                'created_at' => (string) ($shipment['created_at'] ?? ''),
                'actions' => $actions,
            ];
        }

        return $this->load->view(self::EVENT_VIEW_ROUTE . 'order_info_content', [
            'error' => $lastError,
            'shipments' => $rows,
            'text' => [
                'close' => $this->language->get('button_close'),
                'heading' => $this->language->get('text_shipments_heading'),
                'intro' => $this->language->get('text_shipments_intro'),
                'column_shipment' => $this->language->get('column_shipment'),
                'column_barcode' => $this->language->get('column_barcode'),
                'column_tracking' => $this->language->get('column_tracking'),
                'column_created' => $this->language->get('column_created'),
                'column_actions' => $this->language->get('column_actions'),
                'tracking_ready' => $this->language->get('text_tracking_ready'),
                'tracking_processing' => $this->language->get('text_tracking_processing'),
                'tracking_unavailable' => $this->language->get('text_tracking_unavailable'),
            ],
        ]);
    }

    /**
     * Render the i18n payload + script tag for the delegated click handler. The
     * extension directory sits in the web root, so the file is served relative to
     * the catalog URL; filemtime busts browser caches when the plugin is updated.
     */
    private function assets(): string
    {
        $this->loadLanguage();

        // All text_js_* keys are passed automatically. New JS translations
        // therefore need one language entry, not a second allowlist here.
        $i18n = $this->language->all('text_js');

        $file = self::EXTENSION_ROUTE . self::ACTIONS_SCRIPT;
        $path = DIR_EXTENSION . 'myparcel/' . self::ACTIONS_SCRIPT;

        return $this->load->view(self::EVENT_VIEW_ROUTE . 'order_assets', [
            // JSON_HEX_TAG keeps the payload from breaking out of its script element.
            'i18n' => json_encode($i18n, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE),
            'src' => HTTP_CATALOG . $file . '?v=' . (is_file($path) ? (int) filemtime($path) : 0),
        ]);
    }

    /**
     * The carrier slug to badge: the carrier stored on export, else the one chosen in the
     * Delivery Options. Null when neither is known.
     *
     * @param array{carrier?: string, delivery_options?: string}|null $state
     */
    private function carrierSlug(?array $state): ?string
    {
        if ($state === null) {
            return null;
        }

        if (!empty($state['carrier'])) {
            return (string) $state['carrier'];
        }

        $decoded = json_decode($state['delivery_options'] ?? '', true);

        return is_array($decoded) ? DeliveryOptionsDto::fromJson($decoded)->carrier : null;
    }

    /**
     * View data for the carrier badge: public logo when available, name otherwise, null
     * when no carrier is known.
     *
     * @return array{name: string, logo: string}|null
     */
    private function carrierBadge(?string $carrier): ?array
    {
        if ($carrier === null || $carrier === '') {
            return null;
        }

        $this->carriers ??= $this->carrierPresenter();

        return [
            'name' => $this->carriers->nameForSlug($carrier),
            'logo' => $this->carriers->logoUrl($carrier),
        ];
    }

    /** Build carrier presentation from the catalog cached during capability import. */
    private function carrierPresenter(): CarrierPresenter
    {
        $this->load->model('setting/setting');
        $definitions = (new ContractDefinitionsCache())->get($this->model_setting_setting);

        return new CarrierPresenter($definitions?->carrierCatalog);
    }

    /** Load the shared admin language file used by all order fragments. */
    private function loadLanguage(): void
    {
        $this->load->language(self::LANGUAGE_ROUTE);
    }

    /** Load the shipment model through the centralized extension route. */
    private function loadShipmentModel(): void
    {
        $this->load->model(self::SHIPMENT_MODEL_ROUTE);
    }

    /** Read the current admin token used to construct action links. */
    private function userToken(): string
    {
        return (string) ($this->request->get['user_token'] ?? '');
    }
}
