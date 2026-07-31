<?php
namespace Opencart\Catalog\Controller\Extension\Myparcel\Event;

require_once DIR_EXTENSION . 'myparcel/bootstrap.php';

/**
 * Persists the shopper's Delivery Options selection onto the order after it is
 * created or edited. Registered on the order addOrder/editOrder `after` events.
 */
class Order extends \Opencart\System\Engine\Controller
{
    /**
     * Read the Delivery Options JSON stashed in the session during checkout and
     * upsert it onto the order-level MyParcel row — only when MyParcel is the chosen
     * shipping method. Clears the session stash either way.
     *
     * @param string $route OpenCart model route.
     * @param array<int, mixed> $args Arguments passed to the order model method.
     * @param mixed $output Result returned by the order model method.
     */
    public function saveDeliveryOptions(&$route, &$args, &$output): void
    {
        $orderId = $this->orderId($args, $output);
        $orderData = $this->orderData($args);

        if ($orderId <= 0 || $orderData === []) {
            return;
        }

        $shippingCode = (string)($orderData['shipping_method']['code'] ?? '');

        if ($shippingCode !== 'myparcel.myparcel') {
            unset($this->session->data['myparcel_delivery_options']);

            // The order row may already exist (OC4 adds the order when the confirm
            // block first loads); drop any pending Delivery Options written for it.
            $this->load->model('extension/myparcel/checkout/delivery_options');
            $this->model_extension_myparcel_checkout_delivery_options->deleteDeliveryOptions($orderId);
            return;
        }

        $json = $this->session->data['myparcel_delivery_options'] ?? null;

        if (!is_string($json) || $json === '') {
            return;
        }

        // Defensive: the save endpoint only stashes valid JSON, but never let a
        // broken stash linger for the rest of the session.
        $decoded = json_decode($json, true);

        if (!is_array($decoded) || $decoded === []) {
            unset($this->session->data['myparcel_delivery_options']);
            return;
        }

        $canonicalJson = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (!is_string($canonicalJson)) {
            unset($this->session->data['myparcel_delivery_options']);
            return;
        }

        $this->load->model('extension/myparcel/checkout/delivery_options');
        $this->model_extension_myparcel_checkout_delivery_options->saveDeliveryOptions($orderId, $canonicalJson);

        unset($this->session->data['myparcel_delivery_options']);
    }

    /**
     * Resolve the order id from an addOrder result or editOrder arguments.
     *
     * @param array<int, mixed> $args
     * @param mixed $output Raw result supplied by OpenCart's model event.
     */
    private function orderId(array $args, mixed $output): int
    {
        if (is_numeric($output)) {
            return (int)$output;
        }

        if (isset($args[0]) && is_numeric($args[0])) {
            return (int)$args[0];
        }

        return 0;
    }

    /**
     * Resolve the order payload from addOrder or editOrder event arguments.
     *
     * @param array<int, mixed> $args
     *
     * @return array<string, mixed>
     */
    private function orderData(array $args): array
    {
        if (isset($args[0]) && is_array($args[0])) {
            return $args[0];
        }

        if (isset($args[1]) && is_array($args[1])) {
            return $args[1];
        }

        return [];
    }
}
