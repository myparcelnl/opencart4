<?php
// Heading
$_['heading_title'] = 'MyParcel';

// Text
$_['text_extension'] = 'Extensions';
$_['text_success'] = 'Success: You have modified the MyParcel module.';
$_['text_edit'] = 'Edit MyParcel Module';
$_['text_advanced'] = 'Advanced';
$_['text_environment_production'] = 'Production';
$_['text_environment_acceptance'] = 'Acceptance (test)';
$_['text_testing'] = 'Testing…';
$_['text_api_key_valid'] = 'API key is valid';
$_['text_api_key_invalid'] = 'API key is invalid';
$_['text_api_key_transport'] = 'Could not reach the MyParcel API';
$_['text_account'] = 'Account';
$_['text_shop'] = 'shop';

// Entry
$_['entry_status'] = 'Status';
$_['entry_api_key'] = 'API key';
$_['entry_environment'] = 'Environment';
$_['entry_default_package_type'] = 'Default package type';
$_['entry_label_format'] = 'Label format';
$_['entry_label_position'] = 'Label position';
$_['entry_fallback_dimensions'] = 'Fallback package size';
$_['entry_fallback_weight'] = 'Fallback weight';
$_['entry_length'] = 'Length';
$_['entry_width'] = 'Width';
$_['entry_height'] = 'Height';

$_['tab_general'] = 'General';
$_['tab_shipment_defaults'] = 'Shipment defaults';
$_['tab_carriers'] = 'Carriers';
$_['tab_checkout'] = 'Checkout';
$_['tab_customs'] = 'Customs';

$_['entry_do_enabled'] = 'Delivery options';
$_['entry_delivery_days_window'] = 'Delivery days window';
$_['entry_drop_off_delay'] = 'Drop-off delay';
$_['entry_pickup_default_view'] = 'Pickup locations view';
$_['entry_allow_pickup_view_selection'] = 'Allow list/map switch';
$_['entry_exclude_parcel_lockers'] = 'Exclude parcel lockers';
$_['entry_compact_view'] = 'Compact view';
$_['entry_pop_up_map'] = 'Pickup map in pop-up';
$_['help_do_enabled'] = 'Show the MyParcel delivery options widget in the checkout.';
$_['help_delivery_days_window'] = 'Number of days shown for delivery-date selection. Use 0 to hide the date selector; available dates still depend on the carrier.';
$_['help_drop_off_delay'] = 'Days between order and hand-off to the carrier (0 = none).';
$_['text_view_list'] = 'List';
$_['text_view_map'] = 'Map';

$_['entry_product_fields'] = 'Product customs fields';
$_['entry_customs_contents'] = 'Package contents';
$_['entry_customs_country'] = 'Default country of origin';
$_['entry_customs_code'] = 'Default HS code';
$_['text_product_customs'] = 'MyParcel customs';
$_['entry_product_hs_code'] = 'HS code';
$_['entry_product_country'] = 'Country of origin';
$_['help_product_fields'] = 'Adds HS code and country of origin fields to the product editor for customs declarations on shipments outside the EU.';
$_['help_customs_contents'] = 'Default description of the package contents for customs declarations on shipments outside the EU.';
$_['help_customs_country'] = 'Fallback country of origin for customs mapping when a product has none. The store country is used when this is left empty.';
$_['help_customs_code'] = 'Fallback HS (harmonised system) code for customs mapping when a product has none. The MyParcel default 000000 is used when this is left empty.';
$_['text_customs_contents_commercial_goods'] = 'Commercial goods';
$_['text_customs_contents_commercial_samples'] = 'Commercial samples';
$_['text_customs_contents_documents'] = 'Documents';
$_['text_customs_contents_gifts'] = 'Gifts';
$_['text_customs_contents_return_shipment'] = 'Returned goods';
$_['text_none'] = '— none —';

$_['text_shipment_defaults'] = 'Shipment defaults';
$_['text_package_type_package'] = 'Package';
$_['text_package_type_mailbox'] = 'Mailbox package';
$_['text_package_type_letter'] = 'Unfranked letter';
$_['text_package_type_digital_stamp'] = 'Digital stamp';
$_['text_package_type_pallet'] = 'Pallet';
$_['text_package_type_package_small'] = 'Small package';
$_['text_package_type_envelope'] = 'Envelope';

// Help
$_['help_environment'] = 'Use acceptance only to test against the MyParcel test environment. Default is production.';
$_['help_default_package_type'] = 'Used as the package type in checkout and as the fallback when an order has no delivery option chosen.';
$_['help_label_format'] = 'A6 prints one label per page; A4 places labels on a sheet.';
$_['help_label_position'] = 'Position on the A4 sheet (1-4). Ignored for A6.';
$_['help_fallback_dimensions'] = 'Length, width and height in cm, used only when the order\'s products have no usable dimensions. Some carriers (e.g. Poste Italiane, InPost) require them.';
$_['help_fallback_weight'] = 'Weight in grams, used only when the order\'s products have no weight. Leave at 0 to use a technical minimum of 1 g. Some carriers require more, such as UPS (at least 50 g).';

// Button
$_['button_save'] = 'Save';
$_['button_back'] = 'Back';
$_['button_test'] = 'Test API key';
$_['button_show'] = 'Show / hide';
$_['button_import'] = 'Import carrier configuration';

// Cross-link to the shipping-method settings
$_['button_shipping_settings'] = 'Shipping settings';
$_['help_shipping_link'] = 'Configure the MyParcel shipping rate, tax class and delivery zones';

// Capabilities
$_['text_carrier_config'] = 'Carrier configuration';
$_['entry_capabilities'] = 'Capabilities';
$_['text_save_first'] = 'Capabilities are fetched with your saved API key. A successful key test saves your settings automatically.';
$_['text_importing'] = 'Importing…';
$_['text_capabilities_imported'] = 'Carrier configuration updated';
$_['text_capabilities_error'] = 'Could not import carrier configuration';
$_['text_capabilities_none'] = 'No carrier configuration imported yet.';
$_['text_carriers'] = 'carriers';
$_['text_last_imported'] = 'last imported';
$_['text_carriers_empty'] = 'Import carrier configuration before selecting carriers.';
$_['text_carrier_service_standard'] = 'Standard delivery';
$_['text_carrier_service_pickup'] = 'Pickup locations';
$_['text_carrier_service_morning'] = 'Morning delivery';
$_['text_carrier_service_evening'] = 'Evening delivery';
$_['text_carrier_service_express'] = 'Express delivery';
$_['text_carrier_service_same_day'] = 'Same-day delivery';
$_['text_carrier_service_signature'] = 'Signature';
$_['text_carrier_service_only_recipient'] = 'Only recipient';

// Columns
$_['column_carrier'] = 'Carrier';
$_['column_services'] = 'Services';

// Entry
$_['entry_carriers'] = 'Carriers';

// Error
$_['error_permission'] = 'Warning: You do not have permission to modify the MyParcel module.';

// Help
$_['help_carriers'] = 'Carriers are based on the imported MyParcel capabilities. Standard delivery and pickup are enabled by default; premium services must be enabled deliberately.';

// Order actions (admin order list + order detail)
$_['button_export_order'] = 'Export to MyParcel';
$_['button_export_again'] = 'Export again (creates an additional shipment)';
$_['button_label_latest'] = 'Download label for latest shipment #%d';
$_['button_track_latest'] = 'Open track & trace for latest shipment #%d';
$_['button_label_shipment'] = 'Download label for shipment #%d';
$_['button_track_shipment'] = 'Open track & trace for shipment #%d';
$_['button_close'] = 'Close';
$_['text_shipment_count'] = '%d shipments';
$_['text_shipment_count_help'] = 'Open the order details to view and manage each shipment.';
$_['text_shipments_heading'] = 'MyParcel shipments';
$_['text_shipments_intro'] = 'Every export creates a separate shipment. The toolbar actions use the newest shipment; use the actions below for a specific shipment.';
$_['column_shipment'] = 'Shipment';
$_['column_barcode'] = 'Barcode';
$_['column_tracking'] = 'Tracking';
$_['column_created'] = 'Created';
$_['column_actions'] = 'Actions';
$_['text_tracking_ready'] = 'Ready';
$_['text_tracking_processing'] = 'Carrier processing';
$_['text_tracking_unavailable'] = 'Not available yet';

// Shipment endpoint messages
$_['error_order_id'] = 'No order id.';
$_['error_order_not_found'] = 'Order not found.';
$_['error_not_exported'] = 'This order has not been exported to MyParcel yet.';
$_['error_no_shipment_returned'] = 'MyParcel returned no shipment.';
$_['error_export_failed'] = 'Could not export the order to MyParcel.';
$_['error_export_phone_advice'] = 'How to fix: the selected carrier requires a valid recipient phone number. Add it under the order\'s customer details and try again.';
$_['error_export_dimensions_advice'] = 'How to fix: the selected carrier could not accept the parcel\'s dimensions or weight. Check the product\'s length, width, height and weight — or the fallback package size in the MyParcel settings — against the carrier\'s limits.';
$_['error_default_carrier_missing'] = 'No default carrier is available. Re-import the carriers in the MyParcel settings, or select a carrier through Delivery Options before exporting.';
$_['error_recipient_incomplete'] = 'This order cannot be exported because the recipient address is incomplete. Complete these fields in the order: %s.';
$_['text_recipient_field_country'] = 'country';
$_['text_recipient_field_street'] = 'street';
$_['text_recipient_field_postal_code'] = 'postal code';
$_['text_recipient_field_city'] = 'city';
$_['text_recipient_field_person_or_company'] = 'recipient name or company';
$_['error_customs_empty_items'] = 'This non-EU order has no products that can be added to a customs declaration.';
$_['error_customs_country_invalid'] = 'Customs data for "%s" contains an invalid country of origin. Select a valid country on the product or in the MyParcel settings.';
$_['error_customs_quantity_invalid'] = 'The quantity for "%s" cannot be used in a customs declaration. Check the order line and try again.';
$_['error_customs_country_missing'] = 'Customs data for "%s" is incomplete. Set a country of origin on the product or configure a default in the MyParcel settings.';
$_['error_customs_too_many_items'] = 'The customs declaration contains more than 100 product lines. Split the order into smaller shipments before exporting.';
$_['error_customs_currency'] = 'Customs declarations require values in EUR, but this store uses %s. Use EUR as the store currency before exporting non-EU orders.';
$_['error_tracktrace_fetch'] = 'Could not fetch track & trace: %s';
$_['error_shipment_missing'] = 'Shipment #%d could not be found in MyParcel. Check the selected environment or export the order again.';
$_['text_tracktrace_concept'] = 'Shipment #%d is still a concept, so track & trace is not available yet. Download its label first, then try again.';
$_['text_tracktrace_processing'] = 'Shipment #%d has a label, but track & trace is not available yet. The carrier may still be processing it; please try again shortly.';

// Strings for admin/view/javascript/myparcel/order-actions.js
$_['text_js_action_generic'] = 'MyParcel';
$_['text_js_action_export'] = 'Export';
$_['text_js_action_label'] = 'Label';
$_['text_js_action_track'] = 'Track & trace';
$_['text_js_order_context'] = 'Order #{order_id} — {action}: {message}';
$_['text_js_invalid_response'] = 'MyParcel returned an invalid response.';
$_['text_js_session_expired'] = 'Your admin session has expired. Please log in again.';
$_['text_js_export_failed'] = 'The export request failed.';
$_['text_js_export_done'] = 'Order exported successfully to MyParcel. You can now download the label.';
$_['text_js_export_again_done'] = 'Additional shipment created successfully. You can now download the label for the newest shipment or view every shipment in the order details.';
$_['text_js_label_failed'] = 'Could not download the label.';
$_['text_js_label_done'] = 'Label downloaded successfully. You can now open track & trace; the carrier may need a short while to make it available.';
$_['text_js_track_failed'] = 'Could not fetch track & trace.';
$_['text_js_track_pending'] = 'Track & trace is not available yet.';
$_['text_js_track_no_link'] = 'MyParcel did not return a track & trace link.';
$_['text_js_popup_blocked'] = 'Could not open track & trace. Please allow pop-ups and try again.';
$_['text_js_track_opened'] = 'Track & trace opened in a new tab.';
$_['text_js_confirm_export'] = 'This order already has a shipment. Export again and create an additional shipment? You can manage every shipment from the order details page.';
$_['text_js_close'] = 'Close';

// OCMOD health check (settings page)
$_['text_ocmod_heading'] = 'MyParcel admin panels are not active';
$_['text_ocmod_missing'] = 'The OCMOD modification that renders the MyParcel order buttons, shipments card and customs fields is not registered. Reinstall the extension via Extensions → Installer.';
$_['text_ocmod_disabled'] = 'The MyParcel OCMOD modification is disabled. Enable it under Extensions → Modifications, then click Refresh.';
$_['text_ocmod_stale'] = 'The MyParcel OCMOD modification has not been applied to the admin templates yet. Go to Extensions → Modifications and click Refresh.';
$_['button_open_modifications'] = 'Open Modifications';

// Postal-code health check (settings page)
$_['text_postcode_heading'] = 'Postal code is not required at checkout';
$_['text_postcode_warning'] = 'OpenCart does not require a postal code for %s (%s), so the checkout accepts orders MyParcel cannot ship. Enable "Postcode Required" for the countries you ship to.';
$_['button_open_countries'] = 'Open Countries';
$_['error_opencart_version'] = 'MyParcel requires OpenCart %s or newer; this store runs %s.';

// Event descriptions shown in OpenCart admin
$_['event_checkout_shipping_method'] = 'MyParcel adds Delivery Options to the checkout shipping methods.';
$_['event_admin_order_list'] = 'MyParcel adds shipment actions to the admin order list.';
$_['event_admin_order_page'] = 'MyParcel loads the order action handler.';
$_['event_admin_order_detail'] = 'MyParcel adds shipment actions and details to an order.';
$_['event_order_after_add'] = 'MyParcel stores Delivery Options after order creation.';
$_['event_order_after_edit'] = 'MyParcel stores Delivery Options after an order update.';
$_['event_product_form'] = 'MyParcel adds customs fields to the product form.';
$_['event_product_add'] = 'MyParcel stores customs data after product creation.';
$_['event_product_edit'] = 'MyParcel stores customs data after a product update.';
