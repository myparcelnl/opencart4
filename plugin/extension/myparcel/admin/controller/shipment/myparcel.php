<?php

namespace Opencart\Admin\Controller\Extension\Myparcel\Shipment;

use MyParcelNL\OpenCart\Core\Dto\DeliveryOptionsDto;
use MyParcelNL\OpenCart\Core\Enum\Environment;
use MyParcelNL\OpenCart\Core\Helper\DimensionResolver;
use MyParcelNL\OpenCart\Core\Helper\OrderDtoBuilder;
use MyParcelNL\OpenCart\Core\Helper\OrderToShipmentMapper;
use MyParcelNL\OpenCart\Core\Helper\ProductData;
use MyParcelNL\OpenCart\Core\Helper\ProductExportDataResolver;
use MyParcelNL\OpenCart\Core\Helper\WeightResolver;
use MyParcelNL\OpenCart\Core\Service\ContractDefinitionsCache;
use MyParcelNL\OpenCart\Core\Service\DeliveryOptions\CarrierSettingsBuilder;
use MyParcelNL\OpenCart\Core\Service\ExportErrorMessageEnhancer;
use MyParcelNL\OpenCart\Core\Service\Shipment\CustomsDeclarationException;
use MyParcelNL\OpenCart\Core\Service\Shipment\CustomsDeclarationFromOrder;
use MyParcelNL\OpenCart\Core\Service\Shipment\MissingRecipientFieldsException;
use MyParcelNL\OpenCart\Core\Service\Shipment\ShipmentApiFailure;
use MyParcelNL\OpenCart\Core\Service\Shipment\ShipmentApiFailureLogger;
use MyParcelNL\OpenCart\Core\Service\Shipment\ShipmentApiService;
use MyParcelNL\OpenCart\Core\Service\Shipment\ShipmentExportValidator;
use MyParcelNL\OpenCart\Core\Settings\SettingKeys;
use MyParcelNL\OpenCart\Core\Settings\Settings;
use MyParcelNL\OpenCart\Core\Support\OpenCartCompatibility;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\ShipmentDefsShipmentStatus;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\ShipmentParametersPaperSize;
use MyParcelNL\Sdk\Collection\ShipmentCollection;

require_once DIR_EXTENSION . 'myparcel/bootstrap.php';

/**
 * Admin endpoint that exports a single OpenCart order to MyParcel as a shipment.
 */
class Myparcel extends \Opencart\System\Engine\Controller
{
    private ?ShipmentApiService $shipmentApiService = null;

    private ?ShipmentApiFailureLogger $failureLogger = null;

    /**
     * AJAX: build a Shipment from the order and create it at MyParcel; returns JSON.
     * Each failure emits an error payload and stops.
     */
    public function export(): void
    {
        OpenCartCompatibility::guardJsonOutput();
        $this->load->language('extension/myparcel/module/myparcel');
        $this->response->addHeader('Content-Type: application/json');

        $orderId = (int) ($this->request->get['order_id'] ?? 0);

        if (($error = $this->exportValidationError($orderId)) !== null) {
            $this->response->setOutput(json_encode(['error' => $error]));
            return;
        }

        $this->load->model('sale/order');
        $order = $this->model_sale_order->getOrder($orderId);
        $products = $this->model_sale_order->getProducts($orderId);

        if (!$order) {
            $this->response->setOutput(json_encode(['error' => $this->language->get('error_order_not_found')]));
            return;
        }

        $this->load->model('extension/myparcel/shipment/myparcel');

        // An order can have multiple shipments — every export appends a new one, in
        // line with the other MyParcel plugins. The checkout stashed the chosen
        // Delivery Options on the order-level row; they apply to every shipment.
        $orderRow = $this->model_extension_myparcel_shipment_myparcel->getOrderRow($orderId);
        $deliveryOptions = $this->deliveryOptions($orderRow);

        $settings = $this->settings();

        // order_product carries no weight, dimensions or customs attributes; load
        // them for all products in one pass and attach the export values per line.
        $productData = ProductData::load($this->db, (int) $this->config->get('config_language_id'), $products);
        $products = (new WeightResolver())->attachWeights($productData, $products);
        $products = (new ProductExportDataResolver())->attach($productData, $products);
        $storeDefaults = $this->storeDefaults($order);

        $orderDto = (new OrderDtoBuilder())->build($order, $products, $storeDefaults['currency']);

        // The carrier the shipment uses, as a widget slug, stored so the order list can show its
        // logo even for orders without a checkout Delivery Options selection. The DO carrier is
        // shopper-supplied and later selects presentation data, so only keep it when it is a known
        // slug; otherwise use the account default imported from MyParcel (never trust raw input).
        $valuesBySlug = (new CarrierSettingsBuilder())->carrierValuesBySlug();
        $doSlug = $deliveryOptions?->carrier;
        $selectedCarrier = $doSlug !== null && isset($valuesBySlug[$doSlug])
            ? $valuesBySlug[$doSlug]
            : null;
        [$accountDefault, $shopId] = $this->accountContext();
        $defaultCarrier = $accountDefault ?? $selectedCarrier;

        if ($defaultCarrier === null) {
            $message = $this->language->get('error_default_carrier_missing');
            $this->model_extension_myparcel_shipment_myparcel->markFailed($orderId, $message);
            $this->response->setOutput(json_encode(['error' => $message]));
            return;
        }

        $defaultSlug = array_flip($valuesBySlug)[$defaultCarrier] ?? null;
        $carrierSlug = ($doSlug !== null && isset($valuesBySlug[$doSlug])) ? $doSlug : $defaultSlug;

        // Dimensions from the products (cm), falling back to the configured default box.
        $dimensions = (new DimensionResolver())->resolve($productData, $products) ?? $this->defaultDimensions();

        $fallbackReporter = function (string $diagnostic) use ($orderId): void {
            $this->logMapperFallback($orderId, $diagnostic);
        };
        $defaultPackageType = OrderToShipmentMapper::packageTypeValue($settings->defaultPackageType, $fallbackReporter);

        try {
            $shipment = (new OrderToShipmentMapper(
                $defaultCarrier,
                $defaultPackageType,
                $shopId,
                $dimensions,
                $settings->fallbackWeight,
                $fallbackReporter,
                new CustomsDeclarationFromOrder(
                    defaultCustomsCode: $settings->defaultCustomsCode,
                    defaultCountryOfOrigin: $settings->defaultCountryOfOrigin ?: $storeDefaults['country'],
                    contentsType: $settings->customsContentsType
                )
            ))
                ->mapOrderToShipment($orderDto, $deliveryOptions);
        } catch (CustomsDeclarationException $e) {
            $message = $this->customsErrorMessage($e);
            $this->model_extension_myparcel_shipment_myparcel->markFailed($orderId, $message);
            $this->response->setOutput(json_encode(['error' => $message]));
            return;
        } catch (MissingRecipientFieldsException $e) {
            $message = $this->recipientErrorMessage($e);
            $this->model_extension_myparcel_shipment_myparcel->markFailed($orderId, $message);
            $this->response->setOutput(json_encode(['error' => $message]));
            return;
        } catch (\InvalidArgumentException $e) {
            $this->logApiFailure('map', $orderId, null, $e);
            $message = $this->language->get('error_export_failed');
            $this->model_extension_myparcel_shipment_myparcel->markFailed($orderId, $message);
            $this->response->setOutput(json_encode(['error' => $message]));
            return;
        }

        $collection = new ShipmentCollection();
        $collection->push($shipment);

        try {
            // Returns [shipment_id => reference_identifier].
            $created = $this->shipmentApi()->create($collection);
        } catch (\Throwable $e) {
            $this->logApiFailure('export', $orderId, null, $e);
            $message = (new ExportErrorMessageEnhancer())->enhance(
                ShipmentApiFailure::fromThrowable($e)->message(),
                $this->language->get('error_export_phone_advice'),
                $this->language->get('error_export_dimensions_advice')
            );
            $this->model_extension_myparcel_shipment_myparcel->markFailed($orderId, $message);
            $this->response->setOutput(json_encode(['error' => $message]));
            return;
        }

        if ($created === []) {
            $this->model_extension_myparcel_shipment_myparcel->markFailed($orderId, $this->language->get('error_no_shipment_returned'));
            $this->response->setOutput(json_encode(['error' => $this->language->get('error_export_failed')]));
            return;
        }

        $shipmentId = (int) array_key_first($created);
        $this->model_extension_myparcel_shipment_myparcel->markExported($orderId, $shipmentId, $created[$shipmentId] ?? null, $carrierSlug);

        // FR-009: pull track & trace right away. The carrier may not have registered the
        // shipment yet, so this is best-effort and never turns a successful export into a failure.
        try {
            $this->fetchTrackTrace($orderId, $shipmentId);
        } catch (\Throwable $e) {
            // The track button can fetch it later; retain diagnostics for the failed call.
            $this->logApiFailure('track_trace', $orderId, $shipmentId, $e);
        }

        $this->response->setOutput(json_encode([
            'success' => true,
            'shipments' => $created,
        ]));
    }

    /**
     * AJAX: fetch the label PDF for an exported order and stream it to the browser.
     */
    public function label(): void
    {
        // Also guards the PDF body: notice output would corrupt the download.
        OpenCartCompatibility::guardJsonOutput();
        $this->load->language('extension/myparcel/module/myparcel');

        if (!$this->user->hasPermission('modify', 'extension/myparcel/shipment/myparcel')) {
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode(['error' => $this->language->get('error_permission')]));
            return;
        }

        $orderId = (int) ($this->request->get['order_id'] ?? 0);
        $shipmentId = $orderId > 0 ? $this->shipmentIdFor($orderId) : null;

        if ($shipmentId === null) {
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode(['error' => $this->language->get('error_not_exported')]));
            return;
        }

        // setPdfOfLabels derives the format from $positions: an A4 sheet position (1-4) prints on
        // A4, anything else prints A6 (one label per page). It also waits for the label to be
        // generated and returns the ready PDF bytes.
        $settings = $this->settings();
        $positions = $settings->labelFormat === ShipmentParametersPaperSize::A4
            ? $settings->labelPosition
            : ShipmentParametersPaperSize::A6;

        try {
            $pdf = $this->shipmentApi()->labels([$shipmentId], $positions);
        } catch (\Throwable $e) {
            $this->logApiFailure('label', $orderId, $shipmentId, $e);
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode(['error' => ShipmentApiFailure::fromThrowable($e)->message()]));
            return;
        }

        // This authenticated download is same-origin, so OpenCart's global CORS headers
        // are unnecessary. Keep its response object to avoid MAMP/mod_fastcgi issues
        // with direct echo + exit while still returning the raw PDF bytes.
        header_remove('Access-Control-Allow-Origin');
        header_remove('Access-Control-Allow-Credentials');
        header_remove('Access-Control-Allow-Methods');
        header_remove('Access-Control-Allow-Headers');
        header_remove('Access-Control-Max-Age');
        $this->response->addHeader('Content-Type: application/pdf');
        $this->response->addHeader(sprintf(
            'Content-Disposition: attachment; filename="myparcel-label-%d-%d.pdf"',
            $orderId,
            $shipmentId
        ));
        $this->response->setOutput($pdf);
    }

    /** Refresh and open an exported order's track & trace. */
    public function trackTrace(): void
    {
        OpenCartCompatibility::guardJsonOutput();
        $this->load->language('extension/myparcel/module/myparcel');
        $ajax = $this->isAjaxRequest();

        if ($ajax) {
            $this->response->addHeader('Content-Type: application/json');
        }

        if (!$this->user->hasPermission('modify', 'extension/myparcel/shipment/myparcel')) {
            $this->trackTraceError($this->language->get('error_permission'), $ajax);
            return;
        }

        $orderId = (int) ($this->request->get['order_id'] ?? 0);
        $shipmentId = $orderId > 0 ? $this->shipmentIdFor($orderId) : null;

        if ($shipmentId === null) {
            $this->trackTraceError($this->language->get('error_not_exported'), $ajax);
            return;
        }

        try {
            $result = $this->fetchTrackTrace($orderId, $shipmentId);
        } catch (\Throwable $e) {
            $this->logApiFailure('track_trace', $orderId, $shipmentId, $e);
            $this->trackTraceError(sprintf($this->language->get('error_tracktrace_fetch'), ShipmentApiFailure::fromThrowable($e)->message()), $ajax);
            return;
        }

        if ($result === null) {
            $this->trackTraceError(sprintf($this->language->get('error_shipment_missing'), $shipmentId), $ajax);
            return;
        }

        $url = $result['tracktrace_url'] ?? '';

        if ($url !== '') {
            if ($ajax) {
                $this->response->setOutput(json_encode(['success' => true, 'url' => $url]));
            } else {
                $this->response->redirect($url);
            }

            return;
        }

        if ($result['status'] === ShipmentDefsShipmentStatus::PENDING_CONCEPT) {
            $message = sprintf($this->language->get('text_tracktrace_concept'), $shipmentId);
        } else {
            $message = sprintf($this->language->get('text_tracktrace_processing'), $shipmentId);
        }

        $this->trackTracePending($message, $ajax);
    }

    /** Return a track & trace error in the format expected by the caller. */
    private function trackTraceError(string $message, bool $ajax): void
    {
        $this->response->setOutput($ajax ? json_encode(['error' => $message]) : $message);
    }

    /** Return the normal not-yet-available state in the format expected by the caller. */
    private function trackTracePending(string $message, bool $ajax): void
    {
        $this->response->setOutput($ajax ? json_encode(['pending' => true, 'message' => $message]) : $message);
    }

    /** True when an admin action was requested through JavaScript. */
    private function isAjaxRequest(): bool
    {
        return strtolower((string) ($this->request->server['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    /** Return the translated request validation failure, if any. */
    private function exportValidationError(int $orderId): ?string
    {
        $key = (new ShipmentExportValidator())->errorLanguageKey(
            $this->user->hasPermission('modify', 'extension/myparcel/shipment/myparcel'),
            $orderId,
            $this->apiKey()
        );

        return $key === null ? null : $this->language->get($key);
    }

    /** Build all shipment SDK calls from the current admin configuration. */
    private function shipmentApi(): ShipmentApiService
    {
        return $this->shipmentApiService ??= new ShipmentApiService(
            $this->apiKey(),
            Environment::isAcceptance((string) $this->config->get(SettingKeys::ENVIRONMENT))
        );
    }

    /** Read the trimmed API key used by all shipment SDK calls in this request. */
    private function apiKey(): string
    {
        return trim((string) $this->config->get(SettingKeys::API_KEY));
    }

    /** Log an API call failure with safe operational context only. */
    private function logApiFailure(string $action, int $orderId, ?int $shipmentId, \Throwable $exception): void
    {
        $this->failureLogger()->log($action, $orderId, $shipmentId, $exception);
    }

    /** Log the mapper's documented non-sensitive fallback diagnostic. */
    private function logMapperFallback(int $orderId, string $diagnostic): void
    {
        $this->failureLogger()->logMapperFallback($orderId, $diagnostic);
    }

    /** Reuse one logger with the OpenCart writer already configured. */
    private function failureLogger(): ShipmentApiFailureLogger
    {
        return $this->failureLogger ??= new ShipmentApiFailureLogger(
            function (string $message): void {
                $this->log->write($message);
            }
        );
    }

    /**
     * Fetch a shipment's barcode + consumer-portal URL and store them.
     * Returns the stored pair, or null when the carrier has not registered it yet.
     *
     * @return array{barcode: string|null, tracktrace_url: string|null, status: int}|null
     */
    private function fetchTrackTrace(int $orderId, int $shipmentId): ?array
    {
        $shipments = $this->shipmentApi()->shipmentsForOrder($orderId);

        foreach ($shipments as $shipment) {
            if ((int) $shipment->getId() !== $shipmentId) {
                continue;
            }

            $barcode = trim((string) $shipment->getBarcode());
            $url = trim((string) $shipment->getLinkConsumerPortal());
            $status = (int) (string) $shipment->getStatus();

            if ($barcode !== '' || $url !== '') {
                $this->model_extension_myparcel_shipment_myparcel->updateTrackTrace($shipmentId, $barcode, $url);
            }

            return ['barcode' => $barcode ?: null, 'tracktrace_url' => $url ?: null, 'status' => $status];
        }

        return null;
    }

    /**
     * Typed view of the saved module settings.
     */
    private function settings(): Settings
    {
        return Settings::fromConfig($this->config);
    }

    /**
     * Currency and country defaults for the store that received the order.
     *
     * @param array<string, mixed> $order
     * @return array{currency: string, country: string}
     */
    private function storeDefaults(array $order): array
    {
        $storeSettings = [];
        $storeId = (int) ($order['store_id'] ?? 0);

        if ($storeId > 0) {
            $this->load->model('setting/setting');
            $storeSettings = $this->model_setting_setting->getSetting('config', $storeId);
        }

        // order_product.price is stored in OpenCart's base currency. The order's
        // currency_code/currency_value only describe how that base value was shown
        // to the customer, so customs must use the order store's base currency.
        $currency = strtoupper(trim((string) (
            $storeSettings['config_currency'] ?? $this->config->get('config_currency')
        )));
        $countryId = (int) (
            $storeSettings['config_country_id'] ?? $this->config->get('config_country_id')
        );

        $this->load->model('localisation/country');
        $country = $countryId > 0 ? $this->model_localisation_country->getCountry($countryId) : [];

        return [
            'currency' => $currency,
            'country' => strtoupper(trim((string) ($country['iso_code_2'] ?? ''))),
        ];
    }

    /** Translate a customs validation reason without coupling the core service to OpenCart. */
    private function customsErrorMessage(CustomsDeclarationException $exception): string
    {
        $key = match ($exception->reason()) {
            CustomsDeclarationException::EMPTY_ITEMS => 'error_customs_empty_items',
            CustomsDeclarationException::INVALID_COUNTRY_OF_ORIGIN => 'error_customs_country_invalid',
            CustomsDeclarationException::INVALID_QUANTITY => 'error_customs_quantity_invalid',
            CustomsDeclarationException::MISSING_COUNTRY_OF_ORIGIN => 'error_customs_country_missing',
            CustomsDeclarationException::TOO_MANY_ITEMS => 'error_customs_too_many_items',
            CustomsDeclarationException::UNSUPPORTED_CURRENCY => 'error_customs_currency',
            default => 'error_export_failed',
        };
        $message = $this->language->get($key);

        return $exception->context() !== '' ? sprintf($message, $exception->context()) : $message;
    }

    /** Translate structured missing-recipient fields for the current admin locale. */
    private function recipientErrorMessage(MissingRecipientFieldsException $exception): string
    {
        $languageKeys = [
            MissingRecipientFieldsException::COUNTRY => 'text_recipient_field_country',
            MissingRecipientFieldsException::STREET => 'text_recipient_field_street',
            MissingRecipientFieldsException::POSTAL_CODE => 'text_recipient_field_postal_code',
            MissingRecipientFieldsException::CITY => 'text_recipient_field_city',
            MissingRecipientFieldsException::PERSON_OR_COMPANY => 'text_recipient_field_person_or_company',
        ];
        $labels = [];

        foreach ($exception->fields() as $field) {
            $labels[] = $this->language->get($languageKeys[$field] ?? $field);
        }

        return sprintf($this->language->get('error_recipient_incomplete'), implode(', ', $labels));
    }

    /**
     * Default carrier and shop id imported from the MyParcel account.
     *
     * A cached default is accepted only while its carrier still has a contract.
     *
     * @return array{0: string|null, 1: int|null}
     */
    private function accountContext(): array
    {
        $this->load->model('setting/setting');
        $definitions = (new ContractDefinitionsCache())->get($this->model_setting_setting);

        if ($definitions === null) {
            return [null, null];
        }

        $shopId = $definitions->shopId ?? 0;
        $available = array_column($definitions->contracts, 'carrier');
        $carrier = $definitions->defaultCarrier;

        return [
            $carrier !== null && in_array($carrier, $available, true) ? $carrier : null,
            $shopId > 0 ? $shopId : null,
        ];
    }

    /**
     * Configured default package size (cm), or null unless all three are set.
     *
     * @return array{length: int, width: int, height: int}|null
     */
    private function defaultDimensions(): ?array
    {
        $length = (int) $this->config->get(SettingKeys::DEFAULT_LENGTH);
        $width = (int) $this->config->get(SettingKeys::DEFAULT_WIDTH);
        $height = (int) $this->config->get(SettingKeys::DEFAULT_HEIGHT);

        if ($length > 0 && $width > 0 && $height > 0) {
            return ['length' => $length, 'width' => $width, 'height' => $height];
        }

        return null;
    }

    /**
     * The MyParcel shipment id an action works on: the shipment_id from the request
     * (validated to belong to the order), or the order's latest shipment when none
     * was requested. Null when the order has no matching shipment. Loads the
     * shipment model so callers can reuse it afterwards.
     */
    private function shipmentIdFor(int $orderId): ?int
    {
        $this->load->model('extension/myparcel/shipment/myparcel');

        $requested = (int) ($this->request->get['shipment_id'] ?? 0);

        if ($requested > 0) {
            $row = $this->model_extension_myparcel_shipment_myparcel->getShipmentForOrder($orderId, $requested);

            return $row !== null ? $requested : null;
        }

        $row = $this->model_extension_myparcel_shipment_myparcel->getLatestShipment($orderId);

        return $row !== null && !empty($row['shipment_id']) ? (int) $row['shipment_id'] : null;
    }

    /**
     * Decode the Delivery Options the checkout stashed on the order-level row.
     *
     * @param array<string, mixed>|null $row
     */
    private function deliveryOptions(?array $row): ?DeliveryOptionsDto
    {
        if ($row === null || empty($row['delivery_options'])) {
            return null;
        }

        $decoded = json_decode((string) $row['delivery_options'], true);

        return is_array($decoded) && $decoded !== [] ? DeliveryOptionsDto::fromJson($decoded) : null;
    }
}
