<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Service\Shipment;

use MyParcelNL\OpenCart\Core\Service\ApiConfigFactory;
use MyParcelNL\Sdk\Collection\ShipmentCollection;
use MyParcelNL\Sdk\Services\Labels\ShipmentLabelsService;
use MyParcelNL\Sdk\Services\Shipment\ShipmentCreateService;
use MyParcelNL\Sdk\Services\Shipment\ShipmentQueryService;

/** Builds the three shipment SDK clients from one API configuration. */
final class ShipmentApiService
{
    private ?string $host;

    /** Resolve one environment-specific host for every shipment SDK service. */
    public function __construct(
        private string $apiKey,
        bool $acceptance,
        ?ApiConfigFactory $configFactory = null
    ) {
        $this->host = ($configFactory ?? new ApiConfigFactory())->coreHost($acceptance);
    }

    /**
     * Create the supplied shipments through the SDK.
     *
     * @return array<int, string|null> MyParcel shipment id => order reference.
     */
    public function create(ShipmentCollection $shipments): array
    {
        return (new ShipmentCreateService($this->apiKey, null, $this->host))->create($shipments);
    }

    /** Fetch the ready label PDF for the supplied shipment ids. */
    public function labels(array $shipmentIds, int|string $positions): string
    {
        return (new ShipmentLabelsService($this->apiKey, null, null, $this->host))
            ->setPdfOfLabels($shipmentIds, $positions);
    }

    /**
     * Query shipments for an OpenCart order and request the consumer-portal link.
     *
     * @return array<int, object>
     */
    public function shipmentsForOrder(int $orderId): array
    {
        return (new ShipmentQueryService($this->apiKey, null, $this->host))->query([
            'reference_identifier' => (string) $orderId,
            'link_consumer_portal' => true,
            'size' => null,
        ]);
    }
}
