(function () {
    // The assets partial can be included on both the order list and order info
    // page; the listener is delegated, so it must only ever be attached once.
    if (window.__myparcelOrderActionsLoaded) {
        return;
    }
    window.__myparcelOrderActionsLoaded = true;

    var flashStorageKey = 'myparcel-order-action-flash';

    // Translations, injected by admin/view/template/event/order_assets.twig.
    var i18n = {};
    var i18nElement = document.getElementById('myparcel-i18n');

    if (i18nElement) {
        try {
            i18n = JSON.parse(i18nElement.textContent) || {};
        } catch (e) {
            i18n = {};
        }
    }

    function t(key) {
        return i18n[key] || key;
    }

    function format(template, values) {
        return Object.keys(values).reduce(function (message, key) {
            return message.split('{' + key + '}').join(values[key]);
        }, template);
    }

    function updateTooltip(btn, message) {
        btn.setAttribute('data-bs-original-title', message);

        if (!window.bootstrap
            || !window.bootstrap.Tooltip
            || typeof window.bootstrap.Tooltip.getInstance !== 'function') {
            return;
        }

        var tooltip = window.bootstrap.Tooltip.getInstance(btn);

        if (tooltip && typeof tooltip.setContent === 'function') {
            tooltip.setContent({'.tooltip-inner': message});
        } else {
            btn.setAttribute('title', message);
        }
    }

    function rememberButtonTitle(btn) {
        if (!btn.hasAttribute('data-myparcel-title')) {
            btn.setAttribute(
                'data-myparcel-title',
                btn.getAttribute('data-bs-original-title') || btn.getAttribute('title') || t('action_generic')
            );
        }
    }

    function resetButton(btn) {
        btn.classList.remove('disabled', 'btn-danger', 'btn-success', 'btn-info', 'btn-secondary');
        btn.classList.add(btn.getAttribute('data-myparcel-variant'));

        if (btn.hasAttribute('data-myparcel-title')) {
            updateTooltip(btn, btn.getAttribute('data-myparcel-title'));
        }
    }

    function markFailed(btn, message) {
        resetButton(btn);
        btn.classList.remove(btn.getAttribute('data-myparcel-variant'));
        btn.classList.add('btn-danger');
        updateTooltip(btn, message);
    }

    function contextualMessage(btn, message) {
        var orderId = btn.getAttribute('data-myparcel-order-id');
        var actionNames = {
            export: t('action_export'),
            label: t('action_label'),
            trackTrace: t('action_track')
        };
        var action = actionNames[btn.getAttribute('data-myparcel')] || t('action_generic');

        return orderId ? format(t('order_context'), {
            order_id: orderId,
            action: action,
            message: message
        }) : message;
    }

    function showMessage(btn, message, persistent, variant) {
        var className = persistent ? 'myparcel-export-error' : 'myparcel-action-message';

        document.querySelectorAll('.' + className).forEach(function (alert) {
            alert.remove();
        });

        var alert = document.createElement('div');
        alert.className = 'alert alert-' + variant + ' alert-dismissible fade show ' + className;
        alert.setAttribute('role', 'alert');
        alert.setAttribute('aria-live', variant === 'danger' ? 'assertive' : 'polite');

        var text = document.createElement('span');
        text.textContent = btn ? contextualMessage(btn, message) : message;
        alert.appendChild(text);

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'btn-close';
        close.setAttribute('data-bs-dismiss', 'alert');
        close.setAttribute('aria-label', t('close'));
        alert.appendChild(close);

        var content = document.querySelector('#content > .container-fluid')
            || document.getElementById('content')
            || document.body;

        content.insertBefore(alert, content.firstChild);
    }

    function rememberFlashMessage(btn, message, variant) {
        try {
            window.sessionStorage.setItem(flashStorageKey, JSON.stringify({
                message: contextualMessage(btn, message),
                variant: variant
            }));
        } catch (e) {
            // A disabled sessionStorage only means the post-reload confirmation is unavailable.
        }
    }

    function showRememberedFlashMessage() {
        var raw;

        try {
            raw = window.sessionStorage.getItem(flashStorageKey);
            window.sessionStorage.removeItem(flashStorageKey);
        } catch (e) {
            return;
        }

        if (!raw) {
            return;
        }

        try {
            var flash = JSON.parse(raw);
            var variants = ['success', 'info', 'warning'];

            if (typeof flash.message === 'string' && variants.indexOf(flash.variant) !== -1) {
                showMessage(null, flash.message, false, flash.variant);
            }
        } catch (e) {
            // Ignore stale or malformed session data.
        }
    }

    function errorMessage(response) {
        var contentType = response.headers.get('content-type') || '';

        if (contentType.indexOf('application/json') !== -1) {
            return response.json().then(function (json) {
                return json.error || t('invalid_response');
            }).catch(function () {
                return t('invalid_response');
            });
        }

        return response.text().then(function (text) {
            return text || t('invalid_response');
        });
    }

    function jsonResponse(response) {
        var contentType = response.headers.get('content-type') || '';

        if (response.ok && contentType.indexOf('application/json') !== -1) {
            return response.json().catch(function () {
                throw new Error(t('invalid_response'));
            });
        }

        return errorMessage(response).then(function (message) {
            throw new Error(message);
        });
    }

    function request(btn) {
        rememberButtonTitle(btn);
        btn.classList.add('disabled');

        return fetch(btn.href, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
    }

    function fail(btn, message, persistent) {
        console.error('[MyParcel] ' + message);
        markFailed(btn, message);
        showMessage(btn, message, persistent, 'danger');
    }

    function pending(btn, message) {
        resetButton(btn);
        showMessage(btn, message, false, 'warning');
    }

    function exportOrder(btn) {
        request(btn)
            .then(jsonResponse)
            .then(function (json) {
                if (json.error) {
                    fail(btn, json.error, true);
                    return;
                }

                var message = btn.getAttribute('data-myparcel-confirm') === '1'
                    ? t('export_again_done')
                    : t('export_done');

                rememberFlashMessage(btn, message, 'success');
                location.reload();
            })
            .catch(function (error) {
                fail(btn, error.message || t('export_failed'), true);
            });
    }

    function label(btn) {
        request(btn)
            .then(function (response) {
                var contentType = response.headers.get('content-type') || '';

                if (response.ok && contentType.indexOf('application/pdf') !== -1) {
                    return response.blob().then(function (blob) {
                        var disposition = response.headers.get('content-disposition') || '';
                        var match = /filename="?([^";]+)"?/i.exec(disposition);
                        var filename = match ? match[1].split(/[\\/]/).pop() : 'myparcel-label.pdf';
                        var url = URL.createObjectURL(blob);
                        var download = document.createElement('a');

                        download.href = url;
                        download.download = filename;
                        document.body.appendChild(download);
                        download.click();
                        download.remove();
                        window.setTimeout(function () { URL.revokeObjectURL(url); }, 0);
                        resetButton(btn);
                        showMessage(btn, t('label_done'), false, 'success');
                    });
                }

                return errorMessage(response).then(function (message) {
                    throw new Error(message);
                });
            })
            .catch(function (error) {
                fail(btn, error.message || t('label_failed'), false);
            });
    }

    function trackTrace(btn) {
        var target = window.open('', '_blank');

        if (target) {
            target.opener = null;
        }

        request(btn)
            .then(jsonResponse)
            .then(function (json) {
                if (json.error) {
                    throw new Error(json.error);
                }

                if (json.pending) {
                    if (target) {
                        target.close();
                    }

                    pending(btn, json.message || t('track_pending'));
                    return;
                }

                if (!json.url) {
                    throw new Error(t('track_no_link'));
                }

                if (!target) {
                    throw new Error(t('popup_blocked'));
                }

                target.location.replace(json.url);
                resetButton(btn);
                showMessage(btn, t('track_opened'), false, 'success');
            })
            .catch(function (error) {
                if (target) {
                    target.close();
                }

                fail(btn, error.message || t('track_failed'), false);
            });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('a.myparcel-action');

        if (!btn || btn.classList.contains('disabled')) {
            return;
        }

        var action = btn.getAttribute('data-myparcel');

        if (action !== 'export' && action !== 'label' && action !== 'trackTrace') {
            return;
        }

        e.preventDefault();

        if (action === 'export'
            && btn.getAttribute('data-myparcel-confirm') === '1'
            && !window.confirm(t('confirm_export'))) {
            return;
        }

        if (action === 'export') {
            exportOrder(btn);
        } else if (action === 'label') {
            label(btn);
        } else {
            trackTrace(btn);
        }
    });

    showRememberedFlashMessage();
})();
