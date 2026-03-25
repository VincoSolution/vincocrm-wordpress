/**
 * VincoCRM Frontend Form Handler
 *
 * Vanilla JS — no jQuery. Handles AJAX form submission with:
 * - Client-side validation
 * - Honeypot + timestamp spam checks
 * - Loading state management
 * - Success/error feedback
 *
 * @package VincoCRM
 */

(function () {
    'use strict';

    const cfg = window.vincoCRMForm || {};

    function initForms() {
        document.querySelectorAll('.vincocrm-contact-form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                handleSubmit(form);
            });
        });
    }

    function handleSubmit(form) {
        var msgEl = form.querySelector('.vincocrm-form-message');
        var btn = form.querySelector('.vincocrm-form-submit');

        // Clear previous messages.
        if (msgEl) {
            msgEl.textContent = '';
            msgEl.className = 'vincocrm-form-message';
        }

        // Client-side validation.
        var fields = form.querySelectorAll('input[required], textarea[required], select[required]');
        for (var i = 0; i < fields.length; i++) {
            if (!fields[i].value.trim()) {
                var label = form.querySelector('label[for="' + fields[i].id + '"]');
                var name = label ? label.textContent.replace(' *', '').trim() : 'Field';
                showMessage(msgEl, name + ' is required.', 'error');
                fields[i].focus();
                return;
            }
        }

        // Email validation.
        var emailFields = form.querySelectorAll('input[type="email"]');
        for (var j = 0; j < emailFields.length; j++) {
            if (emailFields[j].value && !isValidEmail(emailFields[j].value)) {
                showMessage(msgEl, 'Please enter a valid email address.', 'error');
                emailFields[j].focus();
                return;
            }
        }

        // Guard against missing config.
        if (!cfg.ajaxUrl) {
            showMessage(msgEl, 'Form configuration error. Please reload the page.', 'error');
            return;
        }

        // Set loading state.
        var origBtnText = btn ? btn.textContent : '';
        form.classList.add('vincocrm-form--loading');
        if (btn) {
            btn.disabled = true;
            btn.textContent = cfg.strings?.sending || 'Sending...';
        }

        // Build form data.
        var formData = new FormData(form);
        formData.append('action', 'vincocrm_submit_form');
        formData.append('form_id', form.dataset.formId || '');
        formData.append('nonce', form.dataset.nonce || '');

        // Send via fetch.
        fetch(cfg.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData,
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                form.classList.remove('vincocrm-form--loading');
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = origBtnText || 'Send Message';
                }

                if (res.success) {
                    showMessage(msgEl, res.data?.message || cfg.strings?.sent || 'Sent!', 'success');
                    form.reset();
                    // Refresh timestamp.
                    var tsInput = form.querySelector('input[name="vincocrm_ts"]');
                    if (tsInput) tsInput.value = Math.floor(Date.now() / 1000);
                } else {
                    showMessage(msgEl, res.data?.message || cfg.strings?.error || 'Error', 'error');
                }
            })
            .catch(function () {
                form.classList.remove('vincocrm-form--loading');
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = origBtnText || 'Send Message';
                }
                showMessage(msgEl, cfg.strings?.error || 'Something went wrong.', 'error');
            });
    }

    function showMessage(el, text, type) {
        if (!el) return;
        el.textContent = text;
        el.className = 'vincocrm-form-message vincocrm-form-message--' + type;
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    // Init on DOM ready.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initForms);
    } else {
        initForms();
    }
})();
