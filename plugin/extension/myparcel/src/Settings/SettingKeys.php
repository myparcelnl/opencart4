<?php

declare(strict_types=1);

namespace MyParcelNL\OpenCart\Core\Settings;

/**
 * Central registry of the OpenCart setting codes the module reads and writes.
 *
 * OpenCart's editSetting() replaces a whole group at once. The active module
 * and shipping groups therefore remain separate. STATE is an update-safe
 * snapshot because OpenCart deletes those active groups during uninstall.
 */
final class SettingKeys
{
    /** Setting group holding the module status, API key and environment. */
    public const MODULE = 'module_myparcel';

    /** Durable snapshot used to restore active settings after an update. */
    public const STATE = 'myparcel_state';

    public const STATUS = 'module_myparcel_status';

    public const API_KEY = 'module_myparcel_api_key';

    public const ENVIRONMENT = 'module_myparcel_environment';

    /** Package type used by checkout and as export fallback when checkout has no selection. */
    public const DEFAULT_PACKAGE_TYPE = 'module_myparcel_default_package_type';

    /** Default label format ('A4' or 'A6') and the A4 sheet position (1-4). */
    public const LABEL_FORMAT = 'module_myparcel_label_format';

    public const LABEL_POSITION = 'module_myparcel_label_position';

    /** Optional default package size in cm, sent when an order's products carry no dimensions. */
    public const DEFAULT_LENGTH = 'module_myparcel_default_length';

    public const DEFAULT_WIDTH = 'module_myparcel_default_width';

    public const DEFAULT_HEIGHT = 'module_myparcel_default_height';

    /** Optional fallback weight in grams for orders without product weight. */
    public const DEFAULT_WEIGHT = 'module_myparcel_default_weight';

    /** Offer HS code + country of origin as product attributes in the product editor. */
    public const CUSTOMS_PRODUCT_FIELDS = 'module_myparcel_customs_product_fields';

    /** Customs declaration fallbacks for non-EU shipment mapping. */
    public const CUSTOMS_DEFAULT_COUNTRY = 'module_myparcel_customs_default_country';

    public const CUSTOMS_DEFAULT_HS_CODE = 'module_myparcel_customs_default_hs_code';

    /** Default contents category for customs declarations. */
    public const CUSTOMS_CONTENTS_TYPE = 'module_myparcel_customs_contents_type';

    /** Per-carrier admin config (enabled state + selected services). */
    public const CARRIERS = 'module_myparcel_carriers';

    /** Delivery-options widget configuration shown in the checkout. */
    public const CHECKOUT = 'module_myparcel_checkout';

    /** Imported contract-definitions blob (capabilities-endpoint response). */
    public const CONTRACT_DEFINITIONS = 'module_myparcel_capabilities';

    /** Active OpenCart shipping-method setting group and its keys. */
    public const SHIPPING = 'shipping_myparcel';

    public const SHIPPING_NAME = 'shipping_myparcel_name';

    public const SHIPPING_RATE = 'shipping_myparcel_rate';

    public const SHIPPING_TAX_CLASS_ID = 'shipping_myparcel_tax_class_id';

    public const SHIPPING_GEO_ZONE_ID = 'shipping_myparcel_geo_zone_id';

    public const SHIPPING_STATUS = 'shipping_myparcel_status';

    public const SHIPPING_SORT_ORDER = 'shipping_myparcel_sort_order';
}
