<?php
// Heading
$_['heading_title'] = 'MyParcel';

// Text
$_['text_extension'] = 'Estensioni';
$_['text_success'] = 'Operazione riuscita: il modulo MyParcel è stato modificato.';
$_['text_edit'] = 'Modifica modulo MyParcel';
$_['text_advanced'] = 'Avanzate';
$_['text_environment_production'] = 'Produzione';
$_['text_environment_acceptance'] = 'Accettazione (test)';
$_['text_testing'] = 'Verifica in corso…';
$_['text_api_key_valid'] = 'La chiave API è valida';
$_['text_api_key_invalid'] = 'La chiave API non è valida';
$_['text_api_key_transport'] = 'Impossibile raggiungere l\'API MyParcel';
$_['text_account'] = 'Account';
$_['text_shop'] = 'negozio';

// Entry
$_['entry_status'] = 'Stato';
$_['entry_api_key'] = 'Chiave API';
$_['entry_environment'] = 'Ambiente';
$_['entry_default_package_type'] = 'Tipo di pacco predefinito';
$_['entry_label_format'] = 'Formato etichetta';
$_['entry_label_position'] = 'Posizione etichetta';
$_['entry_fallback_dimensions'] = 'Dimensioni pacco di fallback';
$_['entry_fallback_weight'] = 'Peso di fallback';
$_['entry_length'] = 'Lunghezza';
$_['entry_width'] = 'Larghezza';
$_['entry_height'] = 'Altezza';

$_['tab_general'] = 'Generale';
$_['tab_shipment_defaults'] = 'Valori predefiniti spedizione';
$_['tab_carriers'] = 'Corrieri';
$_['tab_checkout'] = 'Checkout';
$_['tab_customs'] = 'Dogana';

$_['entry_do_enabled'] = 'Opzioni di consegna';
$_['entry_delivery_days_window'] = 'Intervallo dei giorni di consegna';
$_['entry_drop_off_delay'] = 'Ritardo di affidamento';
$_['entry_pickup_default_view'] = 'Vista dei punti di ritiro';
$_['entry_allow_pickup_view_selection'] = 'Consenti il passaggio elenco/mappa';
$_['entry_exclude_parcel_lockers'] = 'Escludi i locker';
$_['entry_compact_view'] = 'Vista compatta';
$_['entry_pop_up_map'] = 'Mappa dei punti di ritiro in un pop-up';
$_['help_do_enabled'] = 'Mostra il widget delle opzioni di consegna MyParcel nel checkout.';
$_['help_delivery_days_window'] = 'Numero di giorni mostrati per la scelta della data di consegna. Usa 0 per nascondere il selettore; le date disponibili dipendono comunque dal corriere.';
$_['help_drop_off_delay'] = 'Giorni tra l\'ordine e l\'affidamento al corriere (0 = nessuno).';
$_['text_view_list'] = 'Elenco';
$_['text_view_map'] = 'Mappa';

$_['entry_product_fields'] = 'Campi doganali del prodotto';
$_['entry_customs_contents'] = 'Contenuto del pacco';
$_['entry_customs_country'] = 'Paese di origine predefinito';
$_['entry_customs_code'] = 'Codice HS predefinito';
$_['text_product_customs'] = 'Dogana MyParcel';
$_['entry_product_hs_code'] = 'Codice HS';
$_['entry_product_country'] = 'Paese di origine';
$_['help_product_fields'] = 'Aggiunge i campi codice HS e paese di origine all\'editor del prodotto per le dichiarazioni doganali delle spedizioni fuori dall\'UE.';
$_['help_customs_contents'] = 'Descrizione predefinita del contenuto del pacco per le dichiarazioni doganali delle spedizioni fuori dall\'UE.';
$_['help_customs_country'] = 'Paese di origine di fallback per la mappatura doganale quando il prodotto non ne ha uno. Se lasciato vuoto, viene usato il paese del negozio.';
$_['help_customs_code'] = 'Codice HS (sistema armonizzato) di fallback per la mappatura doganale quando il prodotto non ne ha uno. Se lasciato vuoto, viene usato il valore predefinito MyParcel 000000.';
$_['text_customs_contents_commercial_goods'] = 'Merci commerciali';
$_['text_customs_contents_commercial_samples'] = 'Campioni commerciali';
$_['text_customs_contents_documents'] = 'Documenti';
$_['text_customs_contents_gifts'] = 'Regali';
$_['text_customs_contents_return_shipment'] = 'Merci restituite';
$_['text_none'] = '— nessuno —';

$_['text_shipment_defaults'] = 'Valori predefiniti spedizione';
$_['text_package_type_package'] = 'Pacco';
$_['text_package_type_mailbox'] = 'Pacco per cassetta postale';
$_['text_package_type_letter'] = 'Lettera non affrancata';
$_['text_package_type_digital_stamp'] = 'Francobollo digitale';
$_['text_package_type_pallet'] = 'Pallet';
$_['text_package_type_package_small'] = 'Pacco piccolo';
$_['text_package_type_envelope'] = 'Busta';

// Help
$_['help_environment'] = 'Usa l\'ambiente di accettazione solo per eseguire test nell\'ambiente di prova MyParcel. Il valore predefinito è produzione.';
$_['help_default_package_type'] = 'Usato come tipo di pacco nel checkout e come valore di fallback quando per un ordine non è stata scelta alcuna opzione di consegna.';
$_['help_label_format'] = 'A6 stampa un\'etichetta per pagina; A4 dispone le etichette su un foglio.';
$_['help_label_position'] = 'Posizione sul foglio A4 (1-4). Ignorata per A6.';
$_['help_fallback_dimensions'] = 'Lunghezza, larghezza e altezza in cm, usate solo quando i prodotti dell\'ordine non hanno dimensioni utilizzabili. Alcuni corrieri, come Poste Italiane e InPost, le richiedono.';
$_['help_fallback_weight'] = 'Peso in grammi, usato solo quando i prodotti dell\'ordine non hanno peso. Lascia 0 per usare il minimo tecnico di 1 g. Alcuni corrieri richiedono un peso maggiore, come UPS (almeno 50 g).';

// Button
$_['button_save'] = 'Salva';
$_['button_back'] = 'Indietro';
$_['button_test'] = 'Verifica chiave API';
$_['button_show'] = 'Mostra / nascondi';
$_['button_import'] = 'Importa configurazione corrieri';

// Cross-link to the shipping-method settings
$_['button_shipping_settings'] = 'Impostazioni di spedizione';
$_['help_shipping_link'] = 'Configura la tariffa di spedizione MyParcel, la classe fiscale e le zone di consegna';

// Capabilities
$_['text_carrier_config'] = 'Configurazione corrieri';
$_['entry_capabilities'] = 'Capabilities';
$_['text_save_first'] = 'Le capabilities vengono recuperate con la chiave API salvata. Un test riuscito della chiave salva automaticamente le impostazioni.';
$_['text_importing'] = 'Importazione in corso…';
$_['text_capabilities_imported'] = 'Configurazione corrieri aggiornata';
$_['text_capabilities_error'] = 'Impossibile importare la configurazione corrieri';
$_['text_capabilities_none'] = 'Nessuna configurazione corrieri ancora importata.';
$_['text_carriers'] = 'corrieri';
$_['text_last_imported'] = 'ultimo aggiornamento';
$_['text_carriers_empty'] = 'Importa la configurazione corrieri prima di selezionare i corrieri.';
$_['text_carrier_service_standard'] = 'Consegna standard';
$_['text_carrier_service_pickup'] = 'Punti di ritiro';
$_['text_carrier_service_morning'] = 'Consegna mattutina';
$_['text_carrier_service_evening'] = 'Consegna serale';
$_['text_carrier_service_express'] = 'Consegna express';
$_['text_carrier_service_same_day'] = 'Consegna in giornata';
$_['text_carrier_service_signature'] = 'Firma';
$_['text_carrier_service_only_recipient'] = 'Solo destinatario';

// Columns
$_['column_carrier'] = 'Corriere';
$_['column_services'] = 'Servizi';

// Entry
$_['entry_carriers'] = 'Corrieri';

// Error
$_['error_permission'] = 'Attenzione: non disponi dei permessi per modificare il modulo MyParcel.';

// Help
$_['help_carriers'] = 'I corrieri si basano sulle capabilities MyParcel importate. La consegna standard e i punti di ritiro sono abilitati per impostazione predefinita; i servizi premium devono essere abilitati esplicitamente.';

// Order actions (admin order list + order detail)
$_['button_export_order'] = 'Esporta su MyParcel';
$_['button_export_again'] = 'Esporta di nuovo (crea una spedizione aggiuntiva)';
$_['button_label_latest'] = 'Scarica l\'etichetta dell\'ultima spedizione #%d';
$_['button_track_latest'] = 'Apri il track & trace dell\'ultima spedizione #%d';
$_['button_label_shipment'] = 'Scarica l\'etichetta della spedizione #%d';
$_['button_track_shipment'] = 'Apri il track & trace della spedizione #%d';
$_['button_close'] = 'Chiudi';
$_['text_shipment_count'] = '%d spedizioni';
$_['text_shipment_count_help'] = 'Apri i dettagli dell\'ordine per visualizzare e gestire ogni spedizione.';
$_['text_shipments_heading'] = 'Spedizioni MyParcel';
$_['text_shipments_intro'] = 'Ogni esportazione crea una spedizione separata. Le azioni della barra degli strumenti usano la spedizione più recente; usa le azioni qui sotto per una spedizione specifica.';
$_['column_shipment'] = 'Spedizione';
$_['column_barcode'] = 'Codice a barre';
$_['column_tracking'] = 'Tracking';
$_['column_created'] = 'Creata';
$_['column_actions'] = 'Azioni';
$_['text_tracking_ready'] = 'Disponibile';
$_['text_tracking_processing'] = 'In elaborazione dal corriere';
$_['text_tracking_unavailable'] = 'Non ancora disponibile';

// Shipment endpoint messages
$_['error_order_id'] = 'ID ordine mancante.';
$_['error_order_not_found'] = 'Ordine non trovato.';
$_['error_not_exported'] = 'Questo ordine non è ancora stato esportato su MyParcel.';
$_['error_no_shipment_returned'] = 'MyParcel non ha restituito alcuna spedizione.';
$_['error_export_failed'] = 'Impossibile esportare l\'ordine su MyParcel.';
$_['error_export_phone_advice'] = 'Come risolvere: il corriere selezionato richiede un numero di telefono valido del destinatario. Inseriscilo nei dati cliente dell\'ordine e riprova.';
$_['error_export_dimensions_advice'] = 'Come risolvere: il corriere selezionato non accetta le dimensioni o il peso del pacco. Verifica lunghezza, larghezza, altezza e peso del prodotto — o le dimensioni pacco di fallback nelle impostazioni MyParcel — rispetto ai limiti del corriere.';
$_['error_default_carrier_missing'] = 'Non è disponibile alcun corriere predefinito. Importa nuovamente i corrieri nelle impostazioni MyParcel oppure seleziona un corriere tramite le Opzioni di consegna prima dell\'esportazione.';
$_['error_recipient_incomplete'] = 'Questo ordine non può essere esportato perché l\'indirizzo del destinatario è incompleto. Compila questi campi nell\'ordine: %s.';
$_['text_recipient_field_country'] = 'paese';
$_['text_recipient_field_street'] = 'via';
$_['text_recipient_field_postal_code'] = 'codice postale';
$_['text_recipient_field_city'] = 'città';
$_['text_recipient_field_person_or_company'] = 'nome del destinatario o azienda';
$_['error_customs_empty_items'] = 'Questo ordine con destinazione fuori dall\'UE non contiene prodotti che possano essere aggiunti a una dichiarazione doganale.';
$_['error_customs_country_invalid'] = 'I dati doganali di "%s" contengono un paese di origine non valido. Seleziona un paese valido nel prodotto o nelle impostazioni MyParcel.';
$_['error_customs_quantity_invalid'] = 'La quantità di "%s" non può essere usata in una dichiarazione doganale. Controlla la riga dell\'ordine e riprova.';
$_['error_customs_country_missing'] = 'I dati doganali di "%s" sono incompleti. Imposta un paese di origine nel prodotto oppure configura un valore predefinito nelle impostazioni MyParcel.';
$_['error_customs_too_many_items'] = 'La dichiarazione doganale contiene più di 100 righe prodotto. Suddividi l\'ordine in spedizioni più piccole prima dell\'esportazione.';
$_['error_customs_currency'] = 'Le dichiarazioni doganali richiedono importi in EUR, ma questo negozio usa %s. Imposta EUR come valuta del negozio prima di esportare ordini fuori dall\'UE.';
$_['error_tracktrace_fetch'] = 'Impossibile recuperare il track & trace: %s';
$_['error_shipment_missing'] = 'La spedizione #%d non è stata trovata in MyParcel. Controlla l\'ambiente selezionato oppure esporta nuovamente l\'ordine.';
$_['text_tracktrace_concept'] = 'La spedizione #%d è ancora una bozza, quindi il track & trace non è ancora disponibile. Scarica prima l\'etichetta e riprova.';
$_['text_tracktrace_processing'] = 'La spedizione #%d ha un\'etichetta, ma il track & trace non è ancora disponibile. Il corriere potrebbe essere ancora in fase di elaborazione; riprova tra poco.';

// Strings for admin/view/javascript/myparcel/order-actions.js
$_['text_js_action_generic'] = 'MyParcel';
$_['text_js_action_export'] = 'Esportazione';
$_['text_js_action_label'] = 'Etichetta';
$_['text_js_action_track'] = 'Track & trace';
$_['text_js_order_context'] = 'Ordine #{order_id} — {action}: {message}';
$_['text_js_invalid_response'] = 'MyParcel ha restituito una risposta non valida.';
$_['text_js_session_expired'] = 'La sessione di amministrazione è scaduta. Accedi di nuovo.';
$_['text_js_export_failed'] = 'La richiesta di esportazione non è riuscita.';
$_['text_js_export_done'] = 'Ordine esportato correttamente su MyParcel. Ora puoi scaricare l\'etichetta.';
$_['text_js_export_again_done'] = 'Spedizione aggiuntiva creata correttamente. Ora puoi scaricare l\'etichetta della spedizione più recente o visualizzare tutte le spedizioni nei dettagli dell\'ordine.';
$_['text_js_label_failed'] = 'Impossibile scaricare l\'etichetta.';
$_['text_js_label_done'] = 'Etichetta scaricata correttamente. Ora puoi aprire il track & trace; il corriere potrebbe impiegare qualche istante per renderlo disponibile.';
$_['text_js_track_failed'] = 'Impossibile recuperare il track & trace.';
$_['text_js_track_pending'] = 'Il track & trace non è ancora disponibile.';
$_['text_js_track_no_link'] = 'MyParcel non ha restituito un link di track & trace.';
$_['text_js_popup_blocked'] = 'Impossibile aprire il track & trace. Consenti i pop-up e riprova.';
$_['text_js_track_opened'] = 'Il track & trace è stato aperto in una nuova scheda.';
$_['text_js_confirm_export'] = 'Questo ordine ha già una spedizione. Esportarlo di nuovo e creare una spedizione aggiuntiva? Puoi gestire tutte le spedizioni dalla pagina dei dettagli dell\'ordine.';
$_['text_js_close'] = 'Chiudi';

// OCMOD health check (settings page)
$_['text_ocmod_heading'] = 'I pannelli di amministrazione MyParcel non sono attivi';
$_['text_ocmod_missing'] = 'La modifica OCMOD che mostra i pulsanti ordine MyParcel, il pannello spedizioni e i campi doganali non è registrata. Reinstalla l\'estensione tramite Estensioni → Installer.';
$_['text_ocmod_disabled'] = 'La modifica OCMOD MyParcel è disabilitata. Abilitala in Estensioni → Modifiche, quindi fai clic su Aggiorna.';
$_['text_ocmod_stale'] = 'La modifica OCMOD MyParcel non è ancora stata applicata ai template di amministrazione. Vai a Estensioni → Modifiche e fai clic su Aggiorna.';
$_['button_open_modifications'] = 'Apri Modifiche';

// Controllo codice postale (pagina impostazioni)
$_['text_postcode_heading'] = 'Il codice postale non è obbligatorio nel checkout';
$_['text_postcode_warning'] = 'OpenCart non richiede un codice postale per %s (%s); il checkout accetta quindi ordini che MyParcel non può spedire. Attiva "CAP obbligatorio" per i paesi verso cui spedisci.';
$_['button_open_countries'] = 'Apri paesi';
$_['error_opencart_version'] = 'MyParcel richiede OpenCart %s o versione successiva; questo negozio usa la versione %s.';

// Event descriptions shown in OpenCart admin
$_['event_checkout_shipping_method'] = 'MyParcel aggiunge le Opzioni di consegna ai metodi di spedizione del checkout.';
$_['event_admin_order_list'] = 'MyParcel aggiunge le azioni di spedizione all\'elenco ordini.';
$_['event_admin_order_page'] = 'MyParcel carica il gestore delle azioni dell\'ordine.';
$_['event_admin_order_detail'] = 'MyParcel aggiunge azioni e dettagli di spedizione a un ordine.';
$_['event_order_after_add'] = 'MyParcel salva le Opzioni di consegna dopo la creazione dell\'ordine.';
$_['event_order_after_edit'] = 'MyParcel salva le Opzioni di consegna dopo l\'aggiornamento dell\'ordine.';
$_['event_product_form'] = 'MyParcel aggiunge i campi doganali al modulo del prodotto.';
$_['event_product_add'] = 'MyParcel salva i dati doganali dopo la creazione del prodotto.';
$_['event_product_edit'] = 'MyParcel salva i dati doganali dopo l\'aggiornamento del prodotto.';
