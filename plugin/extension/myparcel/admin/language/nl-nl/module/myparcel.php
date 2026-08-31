<?php
// Heading
$_['heading_title'] = 'MyParcel';

// Text
$_['text_extension'] = 'Extensies';
$_['text_success'] = 'Succes: je hebt de MyParcel-module aangepast.';
$_['text_edit'] = 'MyParcel-module bewerken';
$_['text_advanced'] = 'Geavanceerd';
$_['text_environment_production'] = 'Productie';
$_['text_environment_acceptance'] = 'Acceptatie (test)';
$_['text_testing'] = 'Bezig met testen…';
$_['text_api_key_valid'] = 'API-sleutel is geldig';
$_['text_api_key_invalid'] = 'API-sleutel is ongeldig';
$_['text_api_key_transport'] = 'Kon de MyParcel-API niet bereiken';
$_['text_account'] = 'Account';
$_['text_shop'] = 'shop';

// Entry
$_['entry_status'] = 'Status';
$_['entry_api_key'] = 'API-sleutel';
$_['entry_environment'] = 'Omgeving';
$_['entry_default_package_type'] = 'Standaard pakkettype';
$_['entry_label_format'] = 'Labelformaat';
$_['entry_label_position'] = 'Labelpositie';
$_['entry_fallback_dimensions'] = 'Terugval-pakketgrootte';
$_['entry_fallback_weight'] = 'Terugvalgewicht';
$_['entry_length'] = 'Lengte';
$_['entry_width'] = 'Breedte';
$_['entry_height'] = 'Hoogte';

$_['tab_general'] = 'Algemeen';
$_['tab_shipment_defaults'] = 'Verzend-standaarden';
$_['tab_carriers'] = 'Vervoerders';
$_['tab_checkout'] = 'Checkout';
$_['tab_customs'] = 'Douane';

$_['entry_do_enabled'] = 'Bezorgopties';
$_['entry_delivery_days_window'] = 'Bezorg-dagen-venster';
$_['entry_drop_off_delay'] = 'Drop-off-vertraging';
$_['entry_pickup_default_view'] = 'Afhaallocaties-weergave';
$_['entry_allow_pickup_view_selection'] = 'Lijst/kaart-wissel toestaan';
$_['entry_exclude_parcel_lockers'] = 'Parcel lockers uitsluiten';
$_['entry_compact_view'] = 'Compacte weergave';
$_['entry_pop_up_map'] = 'Afhaalkaart in pop-up';
$_['help_do_enabled'] = 'Toon de MyParcel-bezorgopties-widget in de checkout.';
$_['help_delivery_days_window'] = 'Aantal dagen dat wordt getoond voor de bezorgdatumkeuze. Gebruik 0 om de datumkeuze te verbergen; beschikbare datums blijven afhankelijk van de vervoerder.';
$_['help_drop_off_delay'] = 'Dagen tussen bestelling en overdracht aan de vervoerder (0 = geen).';
$_['text_view_list'] = 'Lijst';
$_['text_view_map'] = 'Kaart';

$_['entry_product_fields'] = 'Douanevelden op product';
$_['entry_customs_contents'] = 'Inhoud van het pakket';
$_['entry_customs_country'] = 'Standaard land van oorsprong';
$_['entry_customs_code'] = 'Standaard HS-code';
$_['text_product_customs'] = 'MyParcel douane';
$_['entry_product_hs_code'] = 'HS-code';
$_['entry_product_country'] = 'Land van oorsprong';
$_['help_product_fields'] = 'Voegt HS-code en land van oorsprong toe aan het productscherm voor douaneverklaringen bij zendingen buiten de EU.';
$_['help_customs_contents'] = 'Standaard omschrijving van de pakketinhoud voor douaneverklaringen bij zendingen buiten de EU.';
$_['help_customs_country'] = 'Terugval-land van oorsprong voor douane-mapping wanneer een product er geen heeft. Als dit leeg blijft, wordt het land van de shop gebruikt.';
$_['help_customs_code'] = 'Terugval-HS-code (geharmoniseerd systeem) voor douane-mapping wanneer een product er geen heeft. Als dit leeg blijft, wordt de MyParcel-standaard 000000 gebruikt.';
$_['text_customs_contents_commercial_goods'] = 'Commerciële goederen';
$_['text_customs_contents_commercial_samples'] = 'Commerciële monsters';
$_['text_customs_contents_documents'] = 'Documenten';
$_['text_customs_contents_gifts'] = 'Geschenken';
$_['text_customs_contents_return_shipment'] = 'Retourgoederen';
$_['text_none'] = '— geen —';

$_['text_shipment_defaults'] = 'Verzend-standaarden';
$_['text_package_type_package'] = 'Pakket';
$_['text_package_type_mailbox'] = 'Brievenbuspakje';
$_['text_package_type_letter'] = 'Ongefrankeerde brief';
$_['text_package_type_digital_stamp'] = 'Digitale postzegel';
$_['text_package_type_pallet'] = 'Pallet';
$_['text_package_type_package_small'] = 'Klein pakket';
$_['text_package_type_envelope'] = 'Envelop';

// Help
$_['help_environment'] = 'Zet alleen op acceptatie om tegen de testomgeving van MyParcel te testen. Standaard is productie.';
$_['help_default_package_type'] = 'Wordt gebruikt als pakkettype in de checkout en als terugvalwaarde wanneer een order geen gekozen bezorgoptie heeft.';
$_['help_label_format'] = 'A6 print één label per pagina; A4 plaatst labels op een vel.';
$_['help_label_position'] = 'Positie op het A4-vel (1-4). Niet van toepassing bij A6.';
$_['help_fallback_dimensions'] = 'Lengte, breedte en hoogte in cm, alleen gebruikt wanneer de producten van een order geen bruikbare afmetingen hebben. Sommige carriers (bijv. Poste Italiane, InPost) vereisen ze.';
$_['help_fallback_weight'] = 'Gewicht in gram, alleen gebruikt wanneer de producten van een order geen gewicht hebben. Laat op 0 voor het technische minimum van 1 g. Sommige vervoerders vereisen meer, zoals UPS (minimaal 50 g).';

// Button
$_['button_save'] = 'Opslaan';
$_['button_back'] = 'Terug';
$_['button_test'] = 'API-sleutel testen';
$_['button_show'] = 'Tonen / verbergen';
$_['button_import'] = 'Vervoerder-configuratie importeren';

// Cross-link naar de verzendmethode-instellingen
$_['button_shipping_settings'] = 'Verzendinstellingen';
$_['help_shipping_link'] = 'Stel het MyParcel-verzendtarief, de belastingklasse en bezorgzones in';

// Capabilities
$_['text_carrier_config'] = 'Vervoerder-configuratie';
$_['entry_capabilities'] = 'Capabilities';
$_['text_save_first'] = 'Capabilities worden opgehaald met je opgeslagen API-sleutel. Een geslaagde sleuteltest slaat je instellingen automatisch op.';
$_['text_importing'] = 'Bezig met importeren…';
$_['text_capabilities_imported'] = 'Vervoerder-configuratie bijgewerkt';
$_['text_capabilities_error'] = 'Kon de vervoerder-configuratie niet importeren';
$_['text_capabilities_none'] = 'Nog geen vervoerder-configuratie geïmporteerd.';
$_['text_carriers'] = 'vervoerders';
$_['text_last_imported'] = 'laatst geïmporteerd';
$_['text_carriers_empty'] = 'Importeer eerst de vervoerder-configuratie voordat je vervoerders selecteert.';
$_['text_carrier_service_standard'] = 'Standaard bezorging';
$_['text_carrier_service_pickup'] = 'Afhaallocaties';
$_['text_carrier_service_morning'] = 'Ochtendbezorging';
$_['text_carrier_service_evening'] = 'Avondbezorging';
$_['text_carrier_service_express'] = 'Expressbezorging';
$_['text_carrier_service_same_day'] = 'Same-day bezorging';
$_['text_carrier_service_signature'] = 'Handtekening';
$_['text_carrier_service_only_recipient'] = 'Alleen huisadres';

// Columns
$_['column_carrier'] = 'Vervoerder';
$_['column_services'] = 'Diensten';

// Entry
$_['entry_carriers'] = 'Vervoerders';

// Error
$_['error_permission'] = 'Waarschuwing: je hebt geen rechten om de MyParcel-module aan te passen.';

// Help
$_['help_carriers'] = 'Vervoerders zijn gebaseerd op de geïmporteerde MyParcel-capabilities. Standaard bezorging en afhaallocaties staan standaard aan; premiumdiensten zet je bewust aan.';

// Orderacties (admin orderlijst + orderdetail)
$_['button_export_order'] = 'Exporteren naar MyParcel';
$_['button_export_again'] = 'Opnieuw exporteren (maakt een extra zending aan)';
$_['button_label_latest'] = 'Label downloaden voor nieuwste zending #%d';
$_['button_track_latest'] = 'Track & trace openen voor nieuwste zending #%d';
$_['button_label_shipment'] = 'Label downloaden voor zending #%d';
$_['button_track_shipment'] = 'Track & trace openen voor zending #%d';
$_['button_close'] = 'Sluiten';
$_['text_shipment_count'] = '%d zendingen';
$_['text_shipment_count_help'] = 'Open de orderdetails om elke zending te bekijken en te beheren.';
$_['text_shipments_heading'] = 'MyParcel-zendingen';
$_['text_shipments_intro'] = 'Elke export maakt een aparte zending aan. De werkbalkknoppen gebruiken de nieuwste zending; gebruik de acties hieronder voor een specifieke zending.';
$_['column_shipment'] = 'Zending';
$_['column_barcode'] = 'Barcode';
$_['column_tracking'] = 'Tracking';
$_['column_created'] = 'Aangemaakt';
$_['column_actions'] = 'Acties';
$_['text_tracking_ready'] = 'Beschikbaar';
$_['text_tracking_processing'] = 'Vervoerder verwerkt';
$_['text_tracking_unavailable'] = 'Nog niet beschikbaar';

// Meldingen van de shipment-endpoints
$_['error_order_id'] = 'Geen order-id.';
$_['error_order_not_found'] = 'Order niet gevonden.';
$_['error_not_exported'] = 'Deze order is nog niet naar MyParcel geëxporteerd.';
$_['error_no_shipment_returned'] = 'MyParcel gaf geen zending terug.';
$_['error_export_failed'] = 'Kon de order niet naar MyParcel exporteren.';
$_['error_export_phone_advice'] = 'Oplossing: de gekozen vervoerder vereist een geldig telefoonnummer van de ontvanger. Vul dit in bij de klantgegevens van de order en probeer opnieuw.';
$_['error_export_dimensions_advice'] = 'Oplossing: de gekozen vervoerder accepteert de afmetingen of het gewicht van het pakket niet. Controleer lengte, breedte, hoogte en gewicht van het product — of de terugval-pakketgrootte in de MyParcel-instellingen — tegen de limieten van de vervoerder.';
$_['error_default_carrier_missing'] = 'Er is geen standaardvervoerder beschikbaar. Importeer de vervoerders opnieuw in de MyParcel-instellingen, of kies vóór het exporteren een vervoerder via Delivery Options.';
$_['error_recipient_incomplete'] = 'Deze order kan niet worden geëxporteerd omdat het adres van de ontvanger niet compleet is. Vul deze velden in bij de order: %s.';
$_['text_recipient_field_country'] = 'land';
$_['text_recipient_field_street'] = 'straat';
$_['text_recipient_field_postal_code'] = 'postcode';
$_['text_recipient_field_city'] = 'plaats';
$_['text_recipient_field_person_or_company'] = 'naam ontvanger of bedrijfsnaam';
$_['error_customs_empty_items'] = 'Deze order buiten de EU bevat geen producten die aan een douaneverklaring kunnen worden toegevoegd.';
$_['error_customs_country_invalid'] = 'De douanegegevens van "%s" bevatten een ongeldig land van oorsprong. Kies een geldig land bij het product of in de MyParcel-instellingen.';
$_['error_customs_quantity_invalid'] = 'Het aantal van "%s" kan niet in een douaneverklaring worden gebruikt. Controleer de orderregel en probeer opnieuw.';
$_['error_customs_country_missing'] = 'De douanegegevens van "%s" zijn niet compleet. Stel een land van oorsprong in bij het product of configureer een standaard in de MyParcel-instellingen.';
$_['error_customs_too_many_items'] = 'De douaneverklaring bevat meer dan 100 productregels. Verdeel de order over kleinere zendingen voordat je exporteert.';
$_['error_customs_currency'] = 'Douaneverklaringen vereisen bedragen in EUR, maar deze shop gebruikt %s. Stel EUR in als shopvaluta voordat je orders buiten de EU exporteert.';
$_['error_tracktrace_fetch'] = 'Kon track & trace niet ophalen: %s';
$_['error_shipment_missing'] = 'Zending #%d is niet gevonden in MyParcel. Controleer de gekozen omgeving of exporteer de order opnieuw.';
$_['text_tracktrace_concept'] = 'Zending #%d is nog een concept, dus track & trace is nog niet beschikbaar. Download eerst het label en probeer het daarna opnieuw.';
$_['text_tracktrace_processing'] = 'Zending #%d heeft een label, maar track & trace is nog niet beschikbaar. De vervoerder verwerkt de zending mogelijk nog; probeer het zo weer.';

// Teksten voor admin/view/javascript/myparcel/order-actions.js
$_['text_js_action_generic'] = 'MyParcel';
$_['text_js_action_export'] = 'Export';
$_['text_js_action_label'] = 'Label';
$_['text_js_action_track'] = 'Track & trace';
$_['text_js_order_context'] = 'Bestelling #{order_id} — {action}: {message}';
$_['text_js_invalid_response'] = 'MyParcel gaf een ongeldig antwoord terug.';
$_['text_js_session_expired'] = 'Je beheersessie is verlopen. Log opnieuw in.';
$_['text_js_export_failed'] = 'Het exportverzoek is mislukt.';
$_['text_js_export_done'] = 'Order succesvol geëxporteerd naar MyParcel. Je kunt nu het label downloaden.';
$_['text_js_export_again_done'] = 'Extra zending succesvol aangemaakt. Je kunt nu het label van de nieuwste zending downloaden of alle zendingen bekijken via de orderdetails.';
$_['text_js_label_failed'] = 'Kon het label niet downloaden.';
$_['text_js_label_done'] = 'Label succesvol gedownload. Je kunt nu track & trace openen; het kan even duren voordat de vervoerder die beschikbaar maakt.';
$_['text_js_track_failed'] = 'Kon track & trace niet ophalen.';
$_['text_js_track_pending'] = 'Track & trace is nog niet beschikbaar.';
$_['text_js_track_no_link'] = 'MyParcel gaf geen track & trace-link terug.';
$_['text_js_popup_blocked'] = 'Kon track & trace niet openen. Sta pop-ups toe en probeer het opnieuw.';
$_['text_js_track_opened'] = 'Track & trace is in een nieuw tabblad geopend.';
$_['text_js_confirm_export'] = 'Deze order heeft al een zending. Opnieuw exporteren en een extra zending aanmaken? Je beheert alle zendingen op de orderdetailpagina.';
$_['text_js_close'] = 'Sluiten';

// OCMOD-healthcheck (instellingenpagina)
$_['text_ocmod_heading'] = 'MyParcel-adminpanelen zijn niet actief';
$_['text_ocmod_missing'] = 'De OCMOD-modificatie die de MyParcel-orderknoppen, zendingenkaart en douanevelden toont is niet geregistreerd. Installeer de extensie opnieuw via Extensies → Installer.';
$_['text_ocmod_disabled'] = 'De MyParcel OCMOD-modificatie staat uit. Zet hem aan onder Extensies → Modifications en klik daarna op Refresh.';
$_['text_ocmod_stale'] = 'De MyParcel OCMOD-modificatie is nog niet toegepast op de admin-templates. Ga naar Extensies → Modifications en klik op Refresh.';
$_['button_open_modifications'] = 'Modifications openen';

// Postcode-controle (instellingenpagina)
$_['text_postcode_heading'] = 'Postcode is niet verplicht in de checkout';
$_['text_postcode_warning'] = 'OpenCart vereist geen postcode voor %s (%s); de checkout accepteert daardoor orders die MyParcel niet kan verzenden. Zet "Postcode verplicht" aan voor de landen waarnaar je verzendt.';
$_['button_open_countries'] = 'Landen openen';
$_['error_opencart_version'] = 'MyParcel vereist OpenCart %s of nieuwer; deze shop draait %s.';

// Eventbeschrijvingen in de OpenCart-admin
$_['event_checkout_shipping_method'] = 'MyParcel voegt Delivery Options toe aan de verzendmethoden in de checkout.';
$_['event_admin_order_list'] = 'MyParcel voegt zendingacties toe aan de orderlijst.';
$_['event_admin_order_page'] = 'MyParcel laadt de afhandeling van orderacties.';
$_['event_admin_order_detail'] = 'MyParcel voegt zendingacties en -details toe aan een order.';
$_['event_order_after_add'] = 'MyParcel bewaart Delivery Options nadat een order is aangemaakt.';
$_['event_order_after_edit'] = 'MyParcel bewaart Delivery Options nadat een order is bijgewerkt.';
$_['event_product_form'] = 'MyParcel voegt douanevelden toe aan het productformulier.';
$_['event_product_add'] = 'MyParcel bewaart douanegegevens nadat een product is aangemaakt.';
$_['event_product_edit'] = 'MyParcel bewaart douanegegevens nadat een product is bijgewerkt.';
