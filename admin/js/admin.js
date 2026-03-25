/**
 * VincoCRM Admin JavaScript
 *
 * Handles: setup wizard steps, settings save, connection test, disconnect.
 * No jQuery — vanilla JS only.
 * All server-sourced data rendered via textContent (XSS-safe).
 *
 * @package VincoCRM
 */

(function () {
    'use strict';

    var cfg = window.vincoCRM || {};

    if (!cfg.ajaxUrl || !cfg.nonce) {
        return; // Missing config — cannot operate.
    }

    // ── Helpers ──

    function ajax(action, data) {
        data = data || {};
        var body = new URLSearchParams();
        body.append('action', action);
        body.append('nonce', cfg.nonce);
        for (var k in data) {
            if (Object.prototype.hasOwnProperty.call(data, k)) {
                body.append(k, data[k]);
            }
        }
        return fetch(cfg.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: body,
        }).then(function (r) {
            return r.json();
        }).catch(function () {
            return { success: false, data: { message: cfg.strings?.error || 'Network error. Please try again.' } };
        });
    }

    function $(sel, ctx) {
        return (ctx || document).querySelector(sel);
    }

    function $$(sel, ctx) {
        return Array.from((ctx || document).querySelectorAll(sel));
    }

    function escHtml(s) {
        var el = document.createElement('span');
        el.textContent = String(s || '');
        return el.innerHTML;
    }

    function showMsg(el, text, type) {
        if (!el) return;
        el.textContent = text || '';
        el.className = 'vincocrm-wizard__message ' + (type || 'error');
    }

    function setLoading(btn, loading) {
        if (!btn) return;
        btn.disabled = loading;
        if (loading) {
            btn.dataset.origText = btn.textContent;
            var spinner = document.createElement('span');
            spinner.className = 'vincocrm-spinner';
            btn.textContent = '';
            btn.appendChild(spinner);
            btn.appendChild(document.createTextNode(' ' + (cfg.strings?.connecting || 'Loading...')));
        } else {
            btn.textContent = btn.dataset.origText || btn.textContent;
        }
    }

    function showNotice(message, type) {
        type = type || 'success';
        var existing = $('.vincocrm-notice');
        if (existing) existing.remove();

        var notice = document.createElement('div');
        notice.className = 'notice notice-' + type + ' is-dismissible vincocrm-notice';
        notice.setAttribute('role', 'alert');
        notice.setAttribute('aria-live', 'polite');
        var p = document.createElement('p');
        p.textContent = message; // Safe: textContent, not innerHTML.
        notice.appendChild(p);

        var wrap = $('.vincocrm-wrap') || $('.wrap');
        if (wrap) {
            wrap.insertBefore(notice, wrap.firstChild.nextSibling);
            setTimeout(function () { notice.remove(); }, 4000);
        }
    }

    // ── Setup Wizard ──

    function initWizard() {
        var steps = $$('.vincocrm-wizard__step');
        var currentStep = 1;
        var workspacesData = [];
        var selectedWorkspace = null;

        function goToStep(step) {
            currentStep = step;
            $$('.vincocrm-wizard__panel').forEach(function (p) { p.style.display = 'none'; });
            var panel = $('#vincocrm-step-' + step) || $('#vincocrm-step-complete');
            if (panel) panel.style.display = '';

            steps.forEach(function (s) {
                var n = parseInt(s.dataset.step, 10);
                s.classList.toggle('active', n === step);
                s.classList.toggle('completed', n < step);
                if (n === step) {
                    s.setAttribute('aria-current', 'step');
                } else {
                    s.removeAttribute('aria-current');
                }
            });
        }

        // Step 1: Login
        var loginBtn = $('#vincocrm-login-btn');
        if (loginBtn) {
            loginBtn.addEventListener('click', async function () {
                var apiUrl = ($('#vincocrm-api-url') || {}).value;
                apiUrl = apiUrl ? apiUrl.trim().replace(/\/+$/, '') : '';
                var email = ($('#vincocrm-email') || {}).value || '';
                var password = ($('#vincocrm-password') || {}).value || '';
                var msgEl = $('#vincocrm-login-message');

                if (!apiUrl || !email.trim() || !password) {
                    showMsg(msgEl, 'All fields are required.');
                    return;
                }

                setLoading(loginBtn, true);
                showMsg(msgEl, '', '');

                var res = await ajax('vincocrm_wizard_login', {
                    api_url: apiUrl,
                    email: email.trim(),
                    password: password,
                });

                setLoading(loginBtn, false);

                if (res.success) {
                    if (res.data.requiresMfa) {
                        var mfaField = $('.vincocrm-mfa-field');
                        if (mfaField) mfaField.style.display = '';
                        showMsg(msgEl, 'Enter your MFA code.', 'info');
                        return;
                    }

                    workspacesData = res.data.workspaces || [];
                    renderWorkspaces(workspacesData);
                    goToStep(2);
                } else {
                    showMsg(msgEl, res.data?.message || 'Login failed.');
                }
            });
        }

        // Step 2: Select Workspace — XSS-safe DOM construction.
        function renderWorkspaces(list) {
            var container = $('#vincocrm-workspaces-list');
            if (!container) return;
            container.textContent = '';

            if (list.length === 0) {
                var p = document.createElement('p');
                p.textContent = 'No workspaces found. Please create one in VincoCRM first.';
                container.appendChild(p);
                return;
            }

            list.forEach(function (ws) {
                var item = document.createElement('label');
                item.className = 'vincocrm-workspace-item';

                var radio = document.createElement('input');
                radio.type = 'radio';
                radio.name = 'vincocrm_workspace';
                radio.value = ws.id || '';
                radio.dataset.name = ws.name || '';

                var nameSpan = document.createElement('span');
                nameSpan.className = 'vincocrm-workspace-item__name';
                nameSpan.textContent = ws.name || ws.id || '';

                var roleSpan = document.createElement('span');
                roleSpan.className = 'vincocrm-workspace-item__role';
                roleSpan.textContent = ws.role || '';

                item.appendChild(radio);
                item.appendChild(nameSpan);
                item.appendChild(roleSpan);
                container.appendChild(item);
            });

            $$('input[name="vincocrm_workspace"]', container).forEach(function (radio) {
                radio.addEventListener('change', function () {
                    selectedWorkspace = { id: radio.value, name: radio.dataset.name };
                    $$('.vincocrm-workspace-item', container).forEach(function (i) { i.classList.remove('selected'); });
                    radio.closest('.vincocrm-workspace-item').classList.add('selected');
                    var btn = $('#vincocrm-select-workspace-btn');
                    if (btn) btn.disabled = false;
                });
            });
        }

        var selectWsBtn = $('#vincocrm-select-workspace-btn');
        if (selectWsBtn) {
            selectWsBtn.addEventListener('click', async function () {
                if (!selectedWorkspace) return;
                var msgEl = $('#vincocrm-workspace-message');

                setLoading(selectWsBtn, true);

                var res = await ajax('vincocrm_wizard_select_workspace', {
                    workspace_id: selectedWorkspace.id,
                    workspace_name: selectedWorkspace.name,
                });

                setLoading(selectWsBtn, false);

                if (res.success) {
                    goToStep(3);
                } else {
                    showMsg(msgEl, res.data?.message || 'Failed to select workspace.');
                }
            });
        }

        // Step 3: Widget
        var widgetBtn = $('#vincocrm-connect-widget-btn');
        if (widgetBtn) {
            widgetBtn.addEventListener('click', async function () {
                var msgEl = $('#vincocrm-widget-message');
                setLoading(widgetBtn, true);

                var res = await ajax('vincocrm_wizard_connect_widget');
                setLoading(widgetBtn, false);

                if (res.success) {
                    showMsg(msgEl, res.data?.message || 'Widget connected!', 'success');
                    goToStep(4);
                } else {
                    showMsg(msgEl, res.data?.message || 'Failed to connect widget.');
                }
            });
        }

        // Step 4: WooCommerce
        var wooBtn = $('#vincocrm-connect-woo-btn');
        if (wooBtn) {
            wooBtn.addEventListener('click', async function () {
                var msgEl = $('#vincocrm-woo-message');
                setLoading(wooBtn, true);

                var res = await ajax('vincocrm_wizard_connect_woocommerce');
                setLoading(wooBtn, false);

                if (res.success) {
                    showMsg(msgEl, res.data?.message || 'Connected!', 'success');
                    if (res.data?.authUrl) {
                        window.open(res.data.authUrl, '_blank');
                    }
                    goToStep('complete');
                } else {
                    showMsg(msgEl, res.data?.message || 'Failed to connect WooCommerce.');
                }
            });
        }

        var skipWooBtn = $('#vincocrm-skip-woo-btn');
        if (skipWooBtn) {
            skipWooBtn.addEventListener('click', async function () {
                await ajax('vincocrm_wizard_skip_woocommerce');
                goToStep('complete');
            });
        }

        // Back buttons
        var back1 = $('#vincocrm-back-to-1');
        if (back1) back1.addEventListener('click', function () { goToStep(1); });
        var back2 = $('#vincocrm-back-to-2');
        if (back2) back2.addEventListener('click', function () { goToStep(2); });
        var back3 = $('#vincocrm-back-to-3');
        if (back3) back3.addEventListener('click', function () { goToStep(3); });
    }

    // ── Settings Page ──

    function initSettings() {
        $$('.vincocrm-settings-form').forEach(function (form) {
            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                var tab = form.dataset.tab;
                var btn = $('.vincocrm-save-settings', form);
                var formData = new FormData(form);

                setLoading(btn, true);

                var data = { tab: tab };
                for (var pair of formData.entries()) {
                    data[pair[0]] = pair[1];
                }

                $$('input[type="checkbox"]', form).forEach(function (cb) {
                    if (!cb.checked) {
                        data[cb.name] = '';
                    }
                });

                var res = await ajax('vincocrm_save_settings', data);
                setLoading(btn, false);

                if (res.success) {
                    showNotice(res.data?.message || cfg.strings?.saved || 'Saved!', 'success');
                } else {
                    showNotice(res.data?.message || cfg.strings?.error || 'Error', 'error');
                }
            });
        });

        $$('select[name]').forEach(function (select) {
            select.addEventListener('change', updateConditionals);
        });
        updateConditionals();

        $$('.vincocrm-test-connection').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                setLoading(btn, true);
                var res = await ajax('vincocrm_test_connection');
                setLoading(btn, false);
                showNotice(
                    res.success ? (res.data?.message || 'Connected!') : (res.data?.message || 'Failed'),
                    res.success ? 'success' : 'error'
                );
            });
        });

        $$('.vincocrm-disconnect').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                if (!confirm(cfg.strings?.confirm || 'Are you sure?')) return;
                setLoading(btn, true);
                var res = await ajax('vincocrm_disconnect');
                setLoading(btn, false);
                if (res.success) {
                    location.reload();
                }
            });
        });

        $$('.vincocrm-clear-retry-queue').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                setLoading(btn, true);
                await ajax('vincocrm_clear_retry_queue');
                setLoading(btn, false);
                location.reload();
            });
        });
    }

    function updateConditionals() {
        $$('.vincocrm-conditional').forEach(function (row) {
            var rule = row.dataset.showWhen;
            if (!rule) return;
            var parts = rule.split('=');
            var select = $('select[name="' + parts[0] + '"]');
            row.style.display = select && select.value === parts[1] ? '' : 'none';
        });
    }

    // ── Init ──

    document.addEventListener('DOMContentLoaded', function () {
        if ($('.vincocrm-wizard')) {
            initWizard();
        }
        if ($('.vincocrm-settings-form') || $('.vincocrm-test-connection')) {
            initSettings();
        }
    });
})();
