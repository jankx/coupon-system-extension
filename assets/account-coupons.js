(function () {
    'use strict';

    var COUPON = window.jankxCoupon || { restUrl: '' };

    function api(path, method, data) {
        return fetch(COUPON.restUrl + path, {
            method: method || 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': COUPON.nonce || ''
            },
            credentials: 'same-origin',
            body: data ? JSON.stringify(data) : undefined
        }).then(function (res) {
            return res.json().catch(function () {
                return { success: false, message: COUPON.i18n.error || 'Request failed.' };
            });
        });
    }

    /* ------------------------------------------------------------------
     * My Account tabs
     * ---------------------------------------------------------------- */
    function initTabs(scope) {
        var tabs = scope.querySelectorAll('.jankx-coupon-tab');
        if (!tabs.length) {
            return;
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var key = tab.getAttribute('data-coupon-tab');

                scope.querySelectorAll('.jankx-coupon-tab').forEach(function (t) {
                    t.classList.remove('is-active');
                    t.setAttribute('aria-selected', 'false');
                });
                tab.classList.add('is-active');
                tab.setAttribute('aria-selected', 'true');

                scope.querySelectorAll('.jankx-coupon-panel').forEach(function (panel) {
                    var active = panel.getAttribute('data-coupon-panel') === key;
                    panel.classList.toggle('is-active', active);
                });
            });
        });
    }

    /* ------------------------------------------------------------------
     * Collect + copy buttons
     * ---------------------------------------------------------------- */
    function initActions(scope) {
        scope.querySelectorAll('.jankx-coupon-collect').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-coupon-id');
                if (!id) {
                    return;
                }

                btn.disabled = true;
                api('coupons/' + id + '/collect').then(function (res) {
                    if (!res.success) {
                        alert(res.message || COUPON.i18n.error || 'Error.');
                        btn.disabled = false;
                        return;
                    }
                    window.location.reload();
                });
            });
        });

        scope.querySelectorAll('.jankx-coupon-copy').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var code = btn.getAttribute('data-coupon-code') || '';

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(code).then(function () {
                        btn.textContent = COUPON.i18n.copied || 'Copied!';
                    });
                } else {
                    var input = document.createElement('input');
                    input.value = code;
                    document.body.appendChild(input);
                    input.select();
                    document.execCommand('copy');
                    document.body.removeChild(input);
                    btn.textContent = COUPON.i18n.copied || 'Copied!';
                }
            });
        });
    }

    /* ------------------------------------------------------------------
     * Cart coupon apply / remove
     * ---------------------------------------------------------------- */
    function initCartCoupon() {
        var form = document.querySelector('.jankx-coupon-form');
        if (!form) {
            return;
        }

        var message = form.querySelector('.jankx-coupon-message');

        function showMessage(text, isError) {
            if (!message) {
                return;
            }
            message.textContent = text || '';
            message.classList.toggle('is-error', !!isError);
        }

        var applyBtn = form.querySelector('.jankx-coupon-apply');
        if (applyBtn) {
            applyBtn.addEventListener('click', function () {
                var input = form.querySelector('.jankx-coupon-code');
                var code = (input && input.value) ? input.value.trim() : '';

                if (!code) {
                    showMessage(COUPON.i18n.enterCode || 'Please enter a code.', true);
                    return;
                }

                applyBtn.disabled = true;
                showMessage('');
                api('cart/apply', { code: code }).then(function (res) {
                    if (!res.success) {
                        showMessage(res.message || COUPON.i18n.error || 'Error.', true);
                        applyBtn.disabled = false;
                        return;
                    }
                    window.location.reload();
                });
            });
        }

        var removeBtn = form.querySelector('.jankx-coupon-remove');
        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                removeBtn.disabled = true;
                api('cart/remove').then(function (res) {
                    if (!res.success) {
                        showMessage(res.message || COUPON.i18n.error || 'Error.', true);
                        removeBtn.disabled = false;
                        return;
                    }
                    window.location.reload();
                });
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.jankx-tab-coupons').forEach(function (scope) {
            initTabs(scope);
            initActions(scope);
        });
        initCartCoupon();
    });
})();
