<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Service\Shipment;

use MyParcelNL\OpenCart\Core\Dto\OrderDto;
use MyParcelNL\OpenCart\Core\Dto\OrderItemDto;
use MyParcelNL\OpenCart\Core\Settings\Settings;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\RefShipmentCustomsDeclaration;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\RefShipmentCustomsDeclarationItem;
use MyParcelNL\Sdk\Client\Generated\CoreApi\Model\RefTypesMoney;
use MyParcelNL\Sdk\Services\CountryCodes;
use MyParcelNL\Sdk\Services\CountryService;

/**
 * Builds the generated Core API customs declaration for destinations outside
 * MyParcel's local/EU shipping zones.
 */
final class CustomsDeclarationFromOrder
{
    public const DEFAULT_CUSTOMS_CODE = Settings::DEFAULT_CUSTOMS_CODE;

    private const DESCRIPTION_MAX_LENGTH = 50;
    private const MAX_ITEMS = 100;
    private const MAX_QUANTITY = 99999;

    private string $defaultCustomsCode;

    private string $defaultCountryOfOrigin;

    private int $contentsType;

    private CountryService $countryService;

    /** Configure order-level fallbacks used when a product has no customs values. */
    public function __construct(
        string $defaultCustomsCode = self::DEFAULT_CUSTOMS_CODE,
        string $defaultCountryOfOrigin = '',
        ?CountryService $countryService = null,
        int $contentsType = Settings::DEFAULT_CUSTOMS_CONTENTS_TYPE
    ) {
        $this->defaultCustomsCode = trim($defaultCustomsCode) ?: self::DEFAULT_CUSTOMS_CODE;
        $this->defaultCountryOfOrigin = strtoupper(trim($defaultCountryOfOrigin));
        $this->contentsType = in_array($contentsType, Settings::customsContentsTypes(), true)
            ? $contentsType
            : Settings::DEFAULT_CUSTOMS_CONTENTS_TYPE;
        $this->countryService = $countryService ?? new CountryService();
    }

    /**
     * Return null for local/EU shipments, otherwise a complete Core API declaration.
     *
     * `$shipmentWeight` is the final physical shipment weight, including the
     * configured fallback when product weights are empty.
     */
    public function create(OrderDto $order, int $shipmentWeight): ?RefShipmentCustomsDeclaration
    {
        if (!$this->countryService->isRow(strtoupper($order->recipient->cc))) {
            return null;
        }

        $items = [];

        foreach ($order->items as $orderItem) {
            if (!$orderItem->requiresShipping) {
                continue;
            }

            $items[] = $this->item($orderItem);
        }

        if (count($items) > self::MAX_ITEMS) {
            throw CustomsDeclarationException::tooManyItems();
        }

        if ($items === []) {
            throw CustomsDeclarationException::emptyItems();
        }

        return (new RefShipmentCustomsDeclaration())
            ->setContents($this->contentsType)
            ->setInvoice(mb_substr($order->reference, 0, 150))
            ->setWeight(max(1, $shipmentWeight))
            ->setItems($items);
    }

    /** Map one OpenCart order line to the generated Core API customs item. */
    private function item(OrderItemDto $item): RefShipmentCustomsDeclarationItem
    {
        $description = trim($item->description);
        $description = mb_substr($description !== '' ? $description : 'Order item', 0, self::DESCRIPTION_MAX_LENGTH);

        if ($item->quantity < 1 || $item->quantity > self::MAX_QUANTITY) {
            throw CustomsDeclarationException::invalidQuantity($description);
        }

        $country = strtoupper(trim($item->countryOfOrigin)) ?: $this->defaultCountryOfOrigin;

        if ($country === '') {
            throw CustomsDeclarationException::missingCountryOfOrigin($description);
        }

        if (!in_array($country, CountryCodes::ALL, true)) {
            throw CustomsDeclarationException::invalidCountryOfOrigin($description);
        }

        $classification = trim($item->hsCode) ?: $this->defaultCustomsCode;
        $valueInCents = (int) round($item->value * 100);

        // Match the PDK order model: value and weight describe one product,
        // while amount carries the number of units on this order line. The
        // customs money model only accepts EUR; like the WooCommerce plugin
        // the store amount is passed through unconverted, so a non-EUR store
        // exports declarations with the amount labelled as EUR.
        return (new RefShipmentCustomsDeclarationItem())
            ->setDescription($description)
            ->setAmount($item->quantity)
            ->setWeight(max(0, $item->weight))
            ->setItemValue(
                (new RefTypesMoney())
                    ->setCurrency(RefTypesMoney::CURRENCY_EUR)
                    ->setAmount($valueInCents)
            )
            ->setClassification($classification)
            ->setCountry($country);
    }
}
