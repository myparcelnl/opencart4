(function (window, document, $) {
  'use strict';

  if (!$) {
    return;
  }

  var config = window.MyParcelOpenCartConfig || {};
  var widgetConfig = window.MyParcelConfig || {};
  var state = window.MyParcelOpenCartState || {
    initialized: false,
    rendered: false,
    syncTimer: null,
    clearRequest: null,
    pendingSave: null,
    selecting: false,
    selectionRun: 0,
    lastAddressKey: null
  };

  window.MyParcelConfig = widgetConfig;
  window.MyParcelOpenCartState = state;
  state.selecting = Boolean(state.selecting);
  state.selectionRun = Number(state.selectionRun) || 0;

  if (!config.token || !config.stateUrl || !config.saveUrl || !config.clearUrl) {
    return;
  }

  if (state.initialized) {
    scheduleSync(100);
    return;
  }

  state.initialized = true;

  polyfillCustomEvent();
  loadAssets().always(function () {
    bindEvents();
    scheduleSync(100);
  });

  // --- events --------------------------------------------------------------

  function bindEvents() {
    // Re-sync when the shopper changes the shipping method by hand (OC4 modal).
    $(document).on('change', 'input[name="shipping_method"]', function () {
      scheduleSync(150);
    });

    $(document).ajaxComplete(function (_event, _xhr, settings) {
      var url = String(settings && settings.url ? settings.url : '');

      // A new address invalidates the previous choice and any selection still
      // in flight for the old address.
      if (isAddressUrl(url)) {
        cancelSelection();
        scheduleSync(500);
        return;
      }

      // A manual method save (not one we triggered ourselves): reconcile.
      if (url.indexOf('checkout/shipping_method.save') !== -1 && !state.selecting) {
        scheduleSync(250);
      }
    });

    observeConfirmReloads();

    // The widget reports the shopper's choice on `document`.
    document.addEventListener('myparcel_updated_delivery_options', function (event) {
      onDeliveryOptionChosen(event.detail || {});
    });
  }

  // Widget-first flow: choosing a delivery option drives the shipping method.
  function onDeliveryOptionChosen(detail) {
    var hidden = document.getElementById('input-myparcel-delivery-options');
    var runId;

    // Empty choice (deselected): clear and cancel any in-flight selection.
    if (!detail || isEmpty(detail)) {
      cancelSelection();
      return;
    }

    runId = state.selectionRun += 1;

    if (hidden) {
      hidden.value = JSON.stringify(detail);
    }

    setConfirmDisabled(true);

    // 1) select the MyParcel shipping method, then 2) persist the delivery
    // options. The session endpoint intentionally refuses to save unless
    // MyParcel is the active shipping method.

    selectMyParcelShippingMethod(runId).done(function () {
      if (runId !== state.selectionRun) {
        return;
      }

      saveSelection(detail).done(function (json) {
        if (runId !== state.selectionRun) {
          return;
        }

        if (json && json.success && json.saved !== false) {
          reloadConfirm();
          return;
        }

        resetSelection();
      }).fail(function () {
        if (runId === state.selectionRun) {
          resetSelection();
        }
      }).always(function () {
        if (runId === state.selectionRun) {
          setConfirmDisabled(false);
        }
      });
    }).fail(function () {
      if (runId === state.selectionRun) {
        resetSelection();
        setConfirmDisabled(false);
      }
    });
  }

  // --- delivery options widget --------------------------------------------

  function sync() {
    var container = document.getElementById('myparcel-delivery-options-container');

    if (!container) {
      return;
    }

    $.ajax({
      url: config.stateUrl,
      type: 'post',
      dataType: 'json',
      contentType: 'application/json',
      data: JSON.stringify({
        token: config.token
      })
    }).done(function (json) {
      // The widget needs an address; nothing else gates it (widget-first).
      if (!json || !json.address) {
        hideWidget();
        return;
      }

      container.hidden = false;

      // Only (re)render on first show or when the address actually changed.
      // Re-firing the update event on an unchanged address makes the widget
      // re-render and drop the shopper's current selection (Magento guards the
      // same way with an address-equality check).
      var addressKey = JSON.stringify(json.address);

      if (!state.rendered) {
        window.MyParcelConfig.address = json.address;
        triggerWidgetEvent('myparcel_render_delivery_options');
        state.rendered = true;
        state.lastAddressKey = addressKey;
      } else if (addressKey !== state.lastAddressKey) {
        window.MyParcelConfig.address = json.address;
        triggerWidgetEvent('myparcel_update_delivery_options');
        state.lastAddressKey = addressKey;
      }

      // Shopper switched to a non-MyParcel method (not mid-selection): drop our
      // orphaned choice, but keep the widget visible so they can pick again.
      var sessionCode = String(json.shipping_code || '');
      if (!state.selecting && sessionCode !== '' && sessionCode !== config.shippingCode) {
        cancelSelection();
      }
    }).fail(function () {
      hideWidget();
    });
  }

  // --- OC4 shipping-method selection --------------------------------------

  // Mirror what the OC4 shipping modal does, without the modal: quote() fills
  // session.shipping_methods (required by save()), then save() selects MyParcel.
  // The caller reloads the confirm block only after the delivery-options choice
  // has also been persisted. The runId is re-checked after every async hop so a
  // cancelled selection (address change, deselect) cannot still mutate the
  // session shipping method or the hidden checkout inputs.
  function selectMyParcelShippingMethod(runId) {
    var deferred = $.Deferred();

    if (!config.quoteUrl || !config.shippingSaveUrl) {
      return deferred.reject().promise();
    }

    state.selecting = true;

    $.ajax({
      url: config.quoteUrl,
      type: 'get',
      dataType: 'json'
    }).done(function (quoteJson) {
      var label = methodLabel(quoteJson);

      if (runId !== state.selectionRun) {
        state.selecting = false;
        deferred.reject();
        return;
      }

      if (quoteJson && quoteJson.redirect) {
        window.location = quoteJson.redirect;
        state.selecting = false;
        deferred.reject(quoteJson);
        return;
      }

      if (quoteJson && quoteJson.error) {
        state.selecting = false;
        deferred.reject(quoteJson);
        return;
      }

      $.ajax({
        url: config.shippingSaveUrl,
        type: 'post',
        dataType: 'json',
        data: {
          shipping_method: config.shippingCode
        }
      }).done(function (saveJson) {
        if (runId !== state.selectionRun) {
          deferred.reject();
          return;
        }

        if (saveJson && saveJson.redirect) {
          window.location = saveJson.redirect;
          deferred.reject(saveJson);
          return;
        }

        if (saveJson && saveJson.success) {
          $('#input-shipping-code').val(config.shippingCode);

          if (label) {
            $('#input-shipping-method').val(label);
          }

          $('#input-payment-method').val('');
          deferred.resolve(saveJson);
          return;
        }

        deferred.reject(saveJson);
      }).fail(function (xhr) {
        deferred.reject(xhr);
      }).always(function () {
        state.selecting = false;
      });
    }).fail(function (xhr) {
      state.selecting = false;
      deferred.reject(xhr);
    });

    return deferred.promise();
  }

  // Find the MyParcel quote label ("name - price") in a quote() response.
  function methodLabel(quoteJson) {
    var methods = quoteJson && quoteJson.shipping_methods ? quoteJson.shipping_methods : {};

    for (var key in methods) {
      var quotes = methods[key] && methods[key].quote ? methods[key].quote : {};

      for (var q in quotes) {
        if (quotes[q] && quotes[q].code === config.shippingCode) {
          return quotes[q].name + ' - ' + quotes[q].text;
        }
      }
    }

    return '';
  }

  function reloadConfirm() {
    if (config.confirmUrl) {
      $('#checkout-confirm').load(config.confirmUrl);
    }
  }

  // --- session persistence -------------------------------------------------

  function saveSelection(detail) {
    if (state.pendingSave && state.pendingSave.readyState !== 4) {
      state.pendingSave.abort();
    }

    state.pendingSave = $.ajax({
      url: config.saveUrl,
      type: 'post',
      dataType: 'json',
      contentType: 'application/json',
      data: JSON.stringify({
        token: config.token,
        delivery_options: detail
      })
    });

    return state.pendingSave;
  }

  // Drop our delivery-option choice locally + in the session, without hiding
  // the widget (widget-first: it stays available for a new pick).
  function resetSelection() {
    var hidden = document.getElementById('input-myparcel-delivery-options');

    if (hidden) {
      hidden.value = '';
    }

    if (state.rendered) {
      triggerWidgetEvent('myparcel_unselect_delivery_options');
    }

    clearRemoteSelection();
  }

  // Invalidate any in-flight selection (e.g. the address changed, or the shopper
  // switched to another method mid-pick) so its async callbacks can't persist a
  // now-stale choice, and re-enable the confirm button.
  function cancelSelection() {
    state.selectionRun += 1;

    // Abort an in-flight delivery-options save. Note: abort() only cancels
    // client-side — a request that already reached PHP may still be processed
    // after the clear below. The runId checks keep the UI consistent; the
    // server-side window is small and self-heals on the next save/clear.
    if (state.pendingSave && state.pendingSave.readyState !== 4) {
      state.pendingSave.abort();
    }

    setConfirmDisabled(false);
    resetSelection();
  }

  function clearRemoteSelection() {
    if (state.clearRequest && state.clearRequest.readyState !== 4) {
      return;
    }

    state.clearRequest = $.ajax({
      url: config.clearUrl,
      type: 'post',
      dataType: 'json',
      contentType: 'application/json',
      data: JSON.stringify({
        token: config.token
      })
    });
  }

  // --- widget event helpers ------------------------------------------------

  function triggerWidgetEvent(name) {
    var selector = config.selector || '#myparcel-delivery-options';
    var event = new window.CustomEvent(name, {
      bubbles: true,
      cancelable: false,
      detail: {
        address: window.MyParcelConfig.address || {},
        config: window.MyParcelConfig.config || {},
        strings: window.MyParcelConfig.strings || {},
        selector: selector
      }
    });

    // The widget listens on `document` (not document.body); dispatch there so it
    // actually receives the render/update events.
    document.dispatchEvent(event);
  }

  function hideWidget() {
    var container = document.getElementById('myparcel-delivery-options-container');

    if (state.rendered) {
      triggerWidgetEvent('myparcel_hide_delivery_options');
    }

    if (container) {
      container.hidden = true;
    }

    state.rendered = false;
  }

  function setConfirmDisabled(disabled) {
    $('#checkout-confirm button, #checkout-confirm input[type="submit"]').prop('disabled', disabled);
  }

  // --- asset loading (CDN) -------------------------------------------------

  function loadAssets() {
    var assets = config.assets || {};
    var chain = $.Deferred().resolve().promise();

    chain = chain.then(function () {
      return loadStyle('myparcel-leaflet-css', assets.leafletCss);
    }).then(function () {
      return loadStyle('myparcel-delivery-options-css', assets.deliveryOptionsCss);
    }).then(function () {
      return loadScript('myparcel-leaflet-js', assets.leafletJs);
    }).then(function () {
      return loadScript('myparcel-delivery-options-js', assets.deliveryOptionsJs);
    });

    return chain;
  }

  function loadStyle(id, href) {
    if (!href || document.getElementById(id)) {
      return $.Deferred().resolve().promise();
    }

    var deferred = $.Deferred();
    var link = document.createElement('link');

    link.id = id;
    link.rel = 'stylesheet';
    link.href = href;
    link.onload = function () {
      deferred.resolve();
    };
    link.onerror = function () {
      deferred.resolve();
    };

    document.head.appendChild(link);

    return deferred.promise();
  }

  function loadScript(id, src) {
    if (!src || document.getElementById(id)) {
      return $.Deferred().resolve().promise();
    }

    var deferred = $.Deferred();
    var script = document.createElement('script');

    script.id = id;
    script.src = src;
    script.onload = function () {
      deferred.resolve();
    };
    script.onerror = function () {
      deferred.resolve();
    };

    document.head.appendChild(script);

    return deferred.promise();
  }

  // --- misc ----------------------------------------------------------------

  function scheduleSync(delay) {
    window.clearTimeout(state.syncTimer);
    state.syncTimer = window.setTimeout(sync, delay || 0);
  }

  function isAddressUrl(url) {
    return url.indexOf('checkout/shipping_address.save') !== -1
      || url.indexOf('checkout/shipping_address.address') !== -1
      || url.indexOf('checkout/payment_address.save') !== -1
      || url.indexOf('checkout/payment_address.address') !== -1
      || url.indexOf('checkout/register.save') !== -1;
  }

  function observeConfirmReloads() {
    var target = document.getElementById('checkout-confirm');

    if (!target || !window.MutationObserver) {
      return;
    }

    var observer = new window.MutationObserver(function () {
      scheduleSync(200);
    });

    observer.observe(target, {
      childList: true,
      subtree: false
    });
  }

  function isEmpty(obj) {
    for (var key in obj) {
      if (Object.prototype.hasOwnProperty.call(obj, key)) {
        return false;
      }
    }

    return true;
  }

  function polyfillCustomEvent() {
    if (typeof window.CustomEvent === 'function') {
      return;
    }

    window.CustomEvent = function (event, params) {
      var customEvent;

      params = params || {bubbles: false, cancelable: false, detail: null};
      customEvent = document.createEvent('CustomEvent');
      customEvent.initCustomEvent(event, params.bubbles, params.cancelable, params.detail);

      return customEvent;
    };
  }
})(window, document, window.jQuery);
