/**
 * VincoCRM Form Builder — Classic & Modern drag-and-drop engines.
 *
 * All server-sourced data rendered via textContent or escaped (XSS-safe).
 * All AJAX calls have error handling.
 *
 * @package VincoCRM
 */

(function () {
    'use strict';

    var cfg = window.vincoCRMForms || {};
    var adminCfg = window.vincoCRM || {};

    var currentFormId = null;
    var currentFields = [];
    var dragIndex = null;
    var previewDebounce = null;

    // ── Helpers ──

    function ajax(action, data) {
        data = data || {};
        var body = new URLSearchParams();
        body.append('action', action);
        body.append('nonce', cfg.nonce || adminCfg.nonce);
        for (var k in data) {
            if (Object.prototype.hasOwnProperty.call(data, k)) {
                body.append(k, typeof data[k] === 'object' ? JSON.stringify(data[k]) : data[k]);
            }
        }
        return fetch(cfg.ajaxUrl || adminCfg.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: body,
        }).then(function (r) {
            return r.json();
        }).catch(function () {
            return { success: false, data: { message: 'Network error. Please try again.' } };
        });
    }

    function $(sel, ctx) {
        return (ctx || document).querySelector(sel);
    }

    function $$(sel, ctx) {
        return Array.from((ctx || document).querySelectorAll(sel));
    }

    function showNotice(msg, type) {
        type = type || 'success';
        var existing = $('.vincocrm-notice');
        if (existing) existing.remove();
        var n = document.createElement('div');
        n.className = 'notice notice-' + type + ' is-dismissible vincocrm-notice';
        n.setAttribute('role', 'alert');
        n.setAttribute('aria-live', 'polite');
        var p = document.createElement('p');
        p.textContent = msg; // Safe: textContent, not innerHTML.
        n.appendChild(p);
        var wrap = $('.vincocrm-wrap');
        if (wrap) {
            wrap.insertBefore(n, wrap.children[1]);
            setTimeout(function () { n.remove(); }, 4000);
        }
    }

    // ── Escape helpers ──

    function escAttr(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function escHtml(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // ── Form List ──

    async function loadFormList() {
        var res = await ajax('vincocrm_list_forms');
        var container = $('#vincocrm-form-list');
        if (!container) return;

        var forms = res.data?.forms || [];
        container.textContent = '';

        if (forms.length === 0) {
            var p = document.createElement('p');
            p.className = 'description';
            p.textContent = cfg.strings?.dragHere || 'No forms yet.';
            container.appendChild(p);
            return;
        }

        forms.forEach(function (form) {
            var item = document.createElement('div');
            item.className = 'vincocrm-form-list-item' + (form.id === currentFormId ? ' active' : '');
            item.dataset.id = form.id;

            var titleSpan = document.createElement('span');
            titleSpan.textContent = form.title || cfg.strings?.untitled || 'Untitled';
            var countSmall = document.createElement('small');
            countSmall.textContent = (form.fields?.length || 0) + ' fields';

            item.appendChild(titleSpan);
            item.appendChild(countSmall);
            item.addEventListener('click', function () { loadForm(form.id); });
            container.appendChild(item);
        });
    }

    // ── Load / New Form ──

    async function loadForm(formId) {
        var res = await ajax('vincocrm_get_form', { form_id: formId });
        if (!res.success) return;

        var form = res.data.form;
        currentFormId = form.id;
        currentFields = form.fields || [];

        $('#vincocrm-form-title').value = form.title || '';
        $('#vincocrm-form-empty').style.display = 'none';
        $('#vincocrm-form-editor').style.display = '';

        var sc = $('#vincocrm-form-shortcode');
        sc.textContent = '[vincocrm_form id="' + form.id + '"]';
        sc.style.display = '';

        renderCanvas();
        renderPreview();
        loadFormList();
    }

    function newForm() {
        currentFormId = null;
        currentFields = getDefaultFields();

        $('#vincocrm-form-title').value = '';
        $('#vincocrm-form-empty').style.display = 'none';
        $('#vincocrm-form-editor').style.display = '';
        $('#vincocrm-form-shortcode').style.display = 'none';

        renderCanvas();
        renderPreview();
    }

    function getDefaultFields() {
        return [
            { type: 'text', label: 'Name', placeholder: 'Your name', required: true, crm_field: 'name', options: [], width: 'full' },
            { type: 'email', label: 'Email', placeholder: 'your@email.com', required: true, crm_field: 'email', options: [], width: 'full' },
            { type: 'text', label: 'Subject', placeholder: 'How can we help?', required: false, crm_field: 'subject', options: [], width: 'full' },
            { type: 'textarea', label: 'Message', placeholder: 'Tell us more...', required: true, crm_field: 'message', options: [], width: 'full' },
        ];
    }

    // ── Canvas Rendering ──

    function renderCanvas() {
        var canvas = $('#vincocrm-builder-canvas');
        if (!canvas) return;
        canvas.innerHTML = '';

        currentFields.forEach(function (field, index) {
            var el = createCanvasField(field, index);
            canvas.appendChild(el);
        });
    }

    function createCanvasField(field, index) {
        var el = document.createElement('div');
        el.className = 'vincocrm-canvas-field';
        el.draggable = true;
        el.dataset.index = index;
        el.setAttribute('role', 'listitem');

        var mappings = cfg.crmMappings || {};

        // Build using innerHTML with escaped values.
        el.innerHTML =
            '<span class="vincocrm-canvas-field__grip" aria-label="Drag to reorder">&#x2807;</span>' +
            '<div class="vincocrm-canvas-field__body">' +
                '<div style="display:flex; gap:8px; margin-bottom:6px;">' +
                    '<input type="text" value="' + escAttr(field.label) + '" data-prop="label" placeholder="Label" style="flex:1;">' +
                    '<select data-prop="type" style="width:100px;">' +
                        Object.keys(cfg.fieldTypes || {}).map(function (k) {
                            var v = cfg.fieldTypes[k];
                            return '<option value="' + escAttr(k) + '"' + (k === field.type ? ' selected' : '') + '>' + escHtml(v.label) + '</option>';
                        }).join('') +
                    '</select>' +
                '</div>' +
                '<div style="display:flex; gap:8px; align-items:center;">' +
                    '<input type="text" value="' + escAttr(field.placeholder) + '" data-prop="placeholder" placeholder="Placeholder" style="flex:1;">' +
                    '<select data-prop="crm_field" style="width:130px;" title="CRM mapping">' +
                        Object.keys(mappings).map(function (k) {
                            return '<option value="' + escAttr(k) + '"' + (k === field.crm_field ? ' selected' : '') + '>' + escHtml(mappings[k]) + '</option>';
                        }).join('') +
                    '</select>' +
                    '<label style="white-space:nowrap; font-size:12px;">' +
                        '<input type="checkbox" data-prop="required"' + (field.required ? ' checked' : '') + '> Req' +
                    '</label>' +
                '</div>' +
                (field.type === 'select' ?
                    '<div style="margin-top:6px;">' +
                        '<input type="text" value="' + escAttr((field.options || []).join(', ')) + '" data-prop="options" placeholder="Options (comma-separated)" style="width:100%;">' +
                    '</div>'
                : '') +
            '</div>' +
            '<div class="vincocrm-canvas-field__actions">' +
                '<button type="button" data-action="remove" title="Remove" aria-label="Remove field">&#x2715;</button>' +
            '</div>';

        // Field change listeners with debounced preview.
        $$('input, select', el).forEach(function (input) {
            var handler = function () {
                var prop = input.dataset.prop;
                if (!prop) return;
                if (prop === 'required') {
                    currentFields[index].required = input.checked;
                } else if (prop === 'options') {
                    currentFields[index].options = input.value.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
                } else {
                    currentFields[index][prop] = input.value;
                }
                if (prop === 'type') renderCanvas();
                // Debounce preview re-render on input events.
                clearTimeout(previewDebounce);
                previewDebounce = setTimeout(renderPreview, 150);
            };
            input.addEventListener('change', handler);
            input.addEventListener('input', handler);
        });

        // Remove.
        var removeBtn = $('[data-action="remove"]', el);
        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                currentFields.splice(index, 1);
                renderCanvas();
                renderPreview();
            });
        }

        // Drag and drop.
        el.addEventListener('dragstart', function (e) {
            dragIndex = index;
            el.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        el.addEventListener('dragend', function () {
            el.classList.remove('dragging');
            dragIndex = null;
        });
        el.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        });
        el.addEventListener('drop', function (e) {
            e.preventDefault();
            if (dragIndex === null || dragIndex === index) return;
            var moved = currentFields.splice(dragIndex, 1)[0];
            currentFields.splice(index, 0, moved);
            renderCanvas();
            renderPreview();
        });

        return el;
    }

    // ── Preview Rendering ──

    function renderPreview() {
        var preview = $('#vincocrm-form-preview');
        if (!preview) return;
        preview.innerHTML = '';

        currentFields.forEach(function (field) {
            var div = document.createElement('div');
            div.className = 'vincocrm-form-field';

            var label = field.label + (field.required ? ' *' : '');

            switch (field.type) {
                case 'textarea':
                    div.innerHTML = '<label>' + escHtml(label) + '</label><textarea placeholder="' + escAttr(field.placeholder) + '" rows="3" disabled></textarea>';
                    break;
                case 'select':
                    div.innerHTML = '<label>' + escHtml(label) + '</label><select disabled><option>— Select —</option>' + (field.options || []).map(function (o) { return '<option>' + escHtml(o) + '</option>'; }).join('') + '</select>';
                    break;
                case 'checkbox':
                    div.innerHTML = '<label><input type="checkbox" disabled> ' + escHtml(field.placeholder || field.label) + '</label>';
                    break;
                default:
                    var inputType = field.type === 'email' ? 'email' : field.type === 'phone' ? 'tel' : 'text';
                    div.innerHTML = '<label>' + escHtml(label) + '</label><input type="' + inputType + '" placeholder="' + escAttr(field.placeholder) + '" disabled>';
            }

            preview.appendChild(div);
        });

        var btn = document.createElement('div');
        btn.innerHTML = '<button type="button" disabled style="margin-top:12px; padding:8px 24px; background:#2271b1; color:#fff; border:none; border-radius:4px; cursor:default;">Send Message</button>';
        preview.appendChild(btn);
    }

    // ── Field Palette ──

    function renderPalette() {
        var palette = $('#vincocrm-field-palette');
        if (!palette) return;

        Object.keys(cfg.fieldTypes || {}).forEach(function (type) {
            var info = cfg.fieldTypes[type];
            var el = document.createElement('div');
            el.className = 'vincocrm-palette-item';
            el.draggable = true;
            el.dataset.type = type;

            var iconSpan = document.createElement('span');
            iconSpan.textContent = info.icon;
            el.appendChild(iconSpan);
            el.appendChild(document.createTextNode(' ' + info.label));

            el.addEventListener('dragstart', function (e) {
                e.dataTransfer.setData('text/plain', type);
                e.dataTransfer.effectAllowed = 'copy';
            });

            el.addEventListener('click', function () {
                currentFields.push({
                    type: type,
                    label: info.label,
                    placeholder: '',
                    required: false,
                    crm_field: '',
                    options: [],
                    width: 'full',
                });
                renderCanvas();
                renderPreview();
            });

            palette.appendChild(el);
        });
    }

    // ── Canvas drop zone ──

    function initCanvasDrop() {
        var canvas = $('#vincocrm-builder-canvas');
        if (!canvas) return;
        canvas.setAttribute('role', 'list');

        canvas.addEventListener('dragover', function (e) {
            e.preventDefault();
            canvas.classList.add('drag-over');
        });
        canvas.addEventListener('dragleave', function () { canvas.classList.remove('drag-over'); });
        canvas.addEventListener('drop', function (e) {
            e.preventDefault();
            canvas.classList.remove('drag-over');
            var type = e.dataTransfer.getData('text/plain');
            if (type && cfg.fieldTypes && cfg.fieldTypes[type] && dragIndex === null) {
                currentFields.push({
                    type: type,
                    label: cfg.fieldTypes[type].label,
                    placeholder: '',
                    required: false,
                    crm_field: '',
                    options: [],
                    width: 'full',
                });
                renderCanvas();
                renderPreview();
            }
        });
    }

    // ── Save / Delete / Duplicate ──

    async function saveForm() {
        var title = ($('#vincocrm-form-title') || {}).value || cfg.strings?.untitled || 'Untitled Form';
        var btn = $('#vincocrm-save-form');
        if (btn) btn.disabled = true;

        var res = await ajax('vincocrm_save_form', {
            form_id: currentFormId || '',
            title: title,
            fields: JSON.stringify(currentFields),
        });

        if (btn) btn.disabled = false;

        if (res.success) {
            currentFormId = res.data.form.id;
            var sc = $('#vincocrm-form-shortcode');
            if (sc) {
                sc.textContent = res.data.shortcode;
                sc.style.display = '';
            }
            showNotice(cfg.strings?.formSaved || 'Saved!');
            loadFormList();
        } else {
            showNotice(res.data?.message || 'Error', 'error');
        }
    }

    async function deleteForm() {
        if (!currentFormId) return;
        if (!confirm(cfg.strings?.confirmDelete || 'Delete this form?')) return;

        var res = await ajax('vincocrm_delete_form', { form_id: currentFormId });
        if (res.success) {
            currentFormId = null;
            currentFields = [];
            $('#vincocrm-form-editor').style.display = 'none';
            $('#vincocrm-form-empty').style.display = '';
            showNotice(cfg.strings?.formDeleted || 'Deleted.');
            loadFormList();
        }
    }

    async function duplicateForm() {
        if (!currentFormId) return;
        var res = await ajax('vincocrm_duplicate_form', { form_id: currentFormId });
        if (res.success) {
            await loadForm(res.data.form.id);
            showNotice('Form duplicated!');
        }
    }

    // ── Init ──

    document.addEventListener('DOMContentLoaded', function () {
        if (!$('#vincocrm-form-list')) return;

        renderPalette();
        initCanvasDrop();
        loadFormList();

        var newBtn = $('#vincocrm-new-form');
        if (newBtn) newBtn.addEventListener('click', newForm);
        var saveBtn = $('#vincocrm-save-form');
        if (saveBtn) saveBtn.addEventListener('click', saveForm);
        var deleteBtn = $('#vincocrm-delete-form');
        if (deleteBtn) deleteBtn.addEventListener('click', deleteForm);
        var dupBtn = $('#vincocrm-duplicate-form');
        if (dupBtn) dupBtn.addEventListener('click', duplicateForm);
    });
})();
