(function (global) {
    'use strict';

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
        });
    }

    // Format complete, recognizable numbers only. Never truncate pasted digits.
    function normalizePhone(value) {
        var original = String(value || '').trim();
        var digits = original.replace(/[\s()+-]/g, '');
        if (/^254\d{9}$/.test(digits)) return digits;
        if (/^0\d{9}$/.test(digits)) return '254' + digits.slice(1);
        if (/^\d{9}$/.test(digits)) return '254' + digits;
        return original;
    }

    function errorItems(errors) {
        return Object.entries(errors || {}).flatMap(function (entry) {
            return (Array.isArray(entry[1]) ? entry[1] : [entry[1]]).map(function (message) {
                return { key: entry[0], message: String(message) };
            });
        });
    }

    function saveFailure(status, data) {
        if (status === 422) return 'validation';
        if (status === 401 || status === 419) return 'session';
        if (status === 403) return 'permission';
        return data && data.save_state === 'not_saved' ? 'not_saved' : 'unconfirmed';
    }

    global.ChipsClientCreate = { normalizePhone: normalizePhone, errorItems: errorItems, saveFailure: saveFailure, escapeHtml: escapeHtml };
    if (typeof document === 'undefined') return;

    function initialize() {
        var page = document.getElementById('client-create');
        if (!page) return;
        var form = page.querySelector('#clientCreateForm');
        var summary = page.querySelector('#clientCreateErrors');
        var status = page.querySelector('#clientCreateStatus');
        var tabs = Array.from(page.querySelectorAll('#clientCreateTabs [data-bs-toggle="tab"]'));
        var nextStep = page.querySelector('#clientNextStep');
        var token = form.querySelector('[name="_token"]').value;
        var draft = form.querySelector('#draft_token').value;
        var sections = {};
        var banks = [], branches = [], bankOptionsLoaded = false, banksRequest = null;
        var dirty = false, saving = false, completed = false, savedRedirect = null, mutations = 0;
        var lockedControls = [];
        var identifierChecks = {};
        var duplicateFields = {};
        var esc = escapeHtml;

        function symbol(name) { return '<span class="material-symbols-outlined" aria-hidden="true">' + name + '</span>'; }
        function action(label, name, id, css) {
            return '<button type="button" class="btn btn-sm ' + (css || 'btn-outline-primary') + '" data-entry-action="' + name + '" data-entry-id="' + esc(id) + '">' + label + '</button>';
        }
        function queryUrl(base, parameters) {
            var url = new URL(base, global.location.href);
            Object.entries(parameters).forEach(function (entry) { url.searchParams.set(entry[0], entry[1]); });
            return url.href;
        }
        async function request(url, options) {
            options = options || {};
            var abort = new AbortController();
            var timer = global.setTimeout(function () { abort.abort(); }, 45000);
            var headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token };
            var body = options.body;
            if (options.data) { headers['Content-Type'] = 'application/json'; body = JSON.stringify(options.data); }
            try {
                var response = await global.fetch(url, { method: options.method || 'GET', body: body, credentials: 'same-origin', headers: headers, signal: abort.signal });
                var data = null;
                if ((response.headers.get('content-type') || '').includes('application/json')) data = await response.json();
                if (!response.ok || !data || data.success === false) {
                    var error = new Error('Request could not be completed.');
                    error.status = response.status;
                    error.data = data || {};
                    throw error;
                }
                return data;
            } finally { global.clearTimeout(timer); }
        }
        function syncSaveButtons() {
            var hasNext = Boolean(followingTab());
            page.querySelectorAll('.client-submit-button').forEach(function (button) {
                button.hidden = hasNext;
                button.disabled = hasNext || saving || completed || mutations > 0;
                button.querySelector('.spinner-border').classList.toggle('d-none', !saving);
                button.querySelector('.client-submit-label').textContent = completed ? 'Client Saved' : saving ? 'Saving client…' : 'Save Client';
            });
            nextStep.hidden = !hasNext;
            nextStep.disabled = saving || completed || mutations > 0;
        }
        function followingTab() {
            return tabs[tabs.findIndex(function (tab) { return tab.classList.contains('active'); }) + 1];
        }
        function syncNextStep() {
            var next = followingTab();
            if (next) {
                nextStep.querySelector('.client-next-label').textContent = 'Next: ' + next.dataset.stepLabel;
                nextStep.setAttribute('aria-controls', next.getAttribute('aria-controls'));
            }
            syncSaveButtons();
        }
        function lockForSave(value) {
            saving = value;
            form.setAttribute('aria-busy', String(value));
            if (value) {
                lockedControls = Array.from(form.elements).map(function (control) {
                    var saved = [control, control.disabled];
                    control.disabled = true;
                    return saved;
                });
            } else {
                lockedControls.forEach(function (saved) { saved[0].disabled = saved[1]; });
                lockedControls = [];
            }
            syncSaveButtons();
        }
        function saveStatus(message, warning, link, label) {
            status.hidden = !message;
            status.classList.toggle('is-warning', Boolean(warning));
            status.textContent = message;
            if (link) {
                var anchor = document.createElement('a');
                anchor.href = link; anchor.target = '_blank'; anchor.rel = 'noopener'; anchor.textContent = label || 'Open Clients list';
                status.appendChild(anchor);
            }
        }
        function reveal(element) {
            if (!element) return;
            function focus() { element.focus(); element.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
            var pane = element.closest('.tab-pane');
            if (pane && !pane.classList.contains('active')) {
                var tab = document.getElementById(pane.id + '-tab');
                tab.addEventListener('shown.bs.tab', focus, { once: true });
                global.bootstrap.Tab.getOrCreateInstance(tab).show();
            } else focus();
        }
        function issueTarget(key) {
            var kind = { banks: 'bank', next_of_kin: 'nextofkin', partners: 'partner' }[key];
            if (kind) {
                var requiredSection = sections[kind];
                if (!requiredSection.loaded) return requiredSection.status.querySelector('[data-entry-action="reload"]') || requiredSection.addButton;
                return requiredSection.editor.hidden ? requiredSection.addButton : requiredSection.editor.querySelector('input, select');
            }
            if (key.startsWith('entry_')) {
                var section = sections[key.slice(6)];
                return section && section.editor.querySelector('input, select');
            }
            return form.elements.namedItem(key);
        }
        function showSummary(items, focusFirst) {
            summary.hidden = items.length === 0;
            summary.innerHTML = '<strong>Client not saved. Please correct the following:</strong><ul>' + items.map(function (item) {
                return '<li><button type="button" class="client-error-link" data-error-target="' + esc(item.key) + '">' + esc(item.message) + '</button></li>';
            }).join('') + '</ul>';
            if (items.length && focusFirst) reveal(issueTarget(items[0].key) || summary);
        }
        function clearPersonalErrors() {
            form.querySelectorAll('[name].is-invalid').forEach(function (field) { field.classList.remove('is-invalid'); field.removeAttribute('aria-invalid'); });
            form.querySelectorAll('[data-main-field-error]').forEach(function (element) { element.remove(); });
        }
        function personalError(field, message) {
            if (!field) return;
            field.classList.add('is-invalid'); field.setAttribute('aria-invalid', 'true');
            var existing = field.parentElement.querySelector('[data-main-field-error]');
            if (!existing) {
                existing = document.createElement('div'); existing.className = 'client-field-error'; existing.dataset.mainFieldError = ''; existing.id = field.id + '-client-error';
                field.insertAdjacentElement('afterend', existing);
            }
            existing.textContent = message;
            var describedBy = (field.getAttribute('aria-describedby') || '').split(' ').filter(Boolean);
            if (!describedBy.includes(existing.id)) describedBy.push(existing.id);
            field.setAttribute('aria-describedby', describedBy.join(' '));
        }
        function sectionStatus(section, message, type, retry) {
            section.status.className = 'client-entry-status' + (type ? ' is-' + type : '');
            section.status.textContent = message || '';
            if (retry) section.status.insertAdjacentHTML('beforeend', action('Try again', retry, ''));
        }
        function recordMarkup(section, row) {
            var kind = section.kind;
            var name = kind === 'bank' ? row.bank_name : kind === 'nextofkin' ? [row.first_name, row.second_name, row.last_name].filter(Boolean).join(' ') : kind === 'contact' ? row.names : row.name;
            var subtitle = kind === 'bank' ? 'Account ending ' + String(row.account_number || '').slice(-4) : kind === 'nextofkin' ? row.relation || 'Relationship not provided' : kind === 'partner' ? 'Partner' : 'Additional contact';
            var details = kind === 'bank' ? [['Account Name', row.account_name], ['Branch', row.branch_name]] : kind === 'partner' ? [['Phone', row.phone], ['National ID', row.national_id], ['KRA PIN', row.kra_pin]] : [['Mobile Number', row.mobile], ['Email Address', row.email]];
            if (kind === 'nextofkin') details.push(['National ID', row.national_id]);
            var controls = section.removing === row.encrypted_id ? '<div class="client-remove-confirm" role="alert">Remove this ' + esc(section.label.toLowerCase()) + ' from the form?<div class="client-record-actions">' + action('Keep Entry', 'keep', row.encrypted_id) + action('Remove', 'confirm-remove', row.encrypted_id, 'client-remove-button') + '</div></div>' : '<div class="client-record-actions">' + action('Edit', 'edit', row.encrypted_id) + action('Remove', 'remove', row.encrypted_id, 'client-remove-button') + '</div>';
            return '<article class="client-entry-record"><div class="client-record-heading">' + symbol(section.icon) + '<div><div class="client-record-name">' + esc(name || 'Not provided') + '</div><div class="client-record-subtitle">' + esc(subtitle) + '</div></div></div><div class="client-record-body"><dl>' + details.map(function (detail) { return '<div><dt>' + detail[0] + '</dt><dd>' + esc(detail[1] || 'Not provided') + '</dd></div>'; }).join('') + '</dl>' + controls + '</div></article>';
        }
        function renderRows(section) {
            section.addButton.hidden = !section.loaded || !section.rows.length || !section.editor.hidden;
            section.list.hidden = !section.editor.hidden && !section.rows.length;
            if (!section.loaded) {
                section.list.innerHTML = '<p class="client-loading">' + (section.loadFailed ? 'Entries could not be loaded.' : 'Loading entries…') + '</p>';
                return;
            }
            section.list.innerHTML = section.rows.length ? '<p class="client-entry-count">' + section.rows.length + ' ' + esc(section.rows.length === 1 ? section.label.toLowerCase() : section.kind === 'nextofkin' ? 'next of kin' : section.label.toLowerCase() + 's') + ' added to this form</p>' + section.rows.map(function (row) { return recordMarkup(section, row); }).join('') : '<div class="client-entry-empty">' + symbol(section.icon) + '<strong>' + esc(section.element.dataset.empty) + '</strong><p>' + (section.kind === 'bank' ? 'Add the account the client will use to receive payments.' : section.kind === 'nextofkin' ? 'Add a person the team can contact when needed.' : 'You can add an entry now or later.') + '</p>' + action('Add ' + esc(section.label), 'add', '', 'btn-primary') + '</div>';
        }
        async function loadRows(section) {
            var sequence = ++section.sequence;
            section.loadFailed = false;
            try {
                var response = await request(queryUrl(page.dataset.repeatersUrl, { draft_token: draft, kind: section.kind }));
                if (!Array.isArray(response.data)) throw new Error('Invalid entry list');
                if (sequence !== section.sequence) return false;
                section.rows = response.data; section.loaded = true;
                if (section.rows.length) dirty = true;
                renderRows(section);
                return true;
            } catch (error) {
                if (sequence !== section.sequence) return false;
                section.loaded = false; section.loadFailed = true;
                sectionStatus(section, 'Could not load these entries. Your form details are still here.', 'error', 'reload');
                renderRows(section);
                return false;
            }
        }
        function populateBranches(section, selected) {
            var bank = section.fields.bank_id.value;
            var branch = section.fields.branch_id;
            branch.innerHTML = '<option value="">Select branch (optional)</option>' + branches.filter(function (item) { return String(item.bank_id) === String(bank); }).map(function (item) { return '<option value="' + esc(item.id) + '">' + esc(item.branch_name) + '</option>'; }).join('');
            branch.value = selected || '';
            branch.disabled = !bank;
        }
        async function loadBanks() {
            try {
                var data = await request(page.dataset.banksUrl);
                if (!Array.isArray(data.banks) || !Array.isArray(data.branches)) throw new Error('Invalid bank choices');
                banks = data.banks; branches = data.branches; bankOptionsLoaded = true;
                var field = sections.bank.fields.bank_id;
                var selected = field.value;
                field.innerHTML = '<option value="">Select bank</option>' + banks.map(function (bank) { return '<option value="' + esc(bank.id) + '">' + esc(bank.bank_name) + '</option>'; }).join('');
                field.value = selected;
                return true;
            } catch (error) {
                bankOptionsLoaded = false;
                sectionStatus(sections.bank, 'Bank choices could not be loaded. Try again to add or edit an account.', 'error', 'reload-banks');
                return false;
            }
        }
        function clearEntryErrors(section) {
            section.errors.hidden = true; section.errors.textContent = '';
            Object.values(section.fields).forEach(function (field) { field.classList.remove('is-invalid'); field.removeAttribute('aria-invalid'); });
            section.element.querySelectorAll('[data-error-for]').forEach(function (element) { element.hidden = true; element.textContent = ''; });
        }
        function showEntryErrors(section, errors, general) {
            clearEntryErrors(section);
            var items = errorItems(errors);
            section.errors.hidden = false;
            section.errors.innerHTML = '<strong>' + esc(general || 'Entry not added. Please correct the highlighted fields.') + '</strong>' + (items.length ? '<ul>' + items.map(function (item) { return '<li>' + esc(item.message) + '</li>'; }).join('') + '</ul>' : '');
            items.forEach(function (item) {
                var field = section.fields[item.key];
                if (!field) return;
                field.classList.add('is-invalid'); field.setAttribute('aria-invalid', 'true');
                var message = section.element.querySelector('[data-error-for="' + item.key + '"]');
                message.textContent = item.message; message.hidden = false;
            });
            var target = items.length && section.fields[items[0].key];
            global.requestAnimationFrame(function () { (target || section.editor.querySelector('input, select')).focus(); });
        }
        async function openEditor(section, row) {
            if (section.busy || saving || completed) return;
            if (!section.loaded && !(await loadRows(section))) return;
            if (section.kind === 'bank' && !bankOptionsLoaded) {
                sectionStatus(section, 'Loading bank choices…');
                banksRequest = banksRequest || loadBanks();
                if (!(await banksRequest)) { banksRequest = null; return; }
            }
            if (!section.editor.hidden && section.editId !== (row && row.encrypted_id) && !global.confirm('Discard changes to this entry?')) return;
            section.editId = row ? row.encrypted_id : null;
            section.removing = null;
            section.editor.hidden = false; section.editor.disabled = false;
            Object.entries(section.fields).forEach(function (entry) { entry[1].value = row && row[entry[0]] != null ? row[entry[0]] : ''; });
            if (section.kind === 'bank') populateBranches(section, row && row.branch_id);
            section.initialValues = Object.fromEntries(Object.entries(section.fields).map(function (entry) { return [entry[0], entry[1].value]; }));
            section.element.querySelector('[data-editor-title]').textContent = (row ? 'Edit ' : 'Add ') + section.label;
            section.element.querySelector('[data-entry-save-label]').textContent = (row ? 'Update ' : 'Add ') + section.label;
            clearEntryErrors(section); sectionStatus(section, ''); renderRows(section);
            reveal(section.editor.querySelector('input, select'));
        }
        function closeEditor(section) {
            section.editor.hidden = true; section.editor.disabled = true; section.editId = null;
            clearEntryErrors(section); renderRows(section);
        }
        function collectEntry(section) {
            var values = {}, errors = {};
            Object.entries(section.fields).forEach(function (entry) {
                var field = entry[1]; var value = field.value.trim();
                if (field.type === 'text') value = value.toUpperCase();
                field.value = value; values[entry[0]] = value;
                if (field.required && !value) errors[entry[0]] = [field.dataset.fieldLabel + ' is required.'];
                else if (!field.disabled && !field.checkValidity()) errors[entry[0]] = [field.type === 'email' ? 'Enter a valid email address, such as name@example.com.' : 'Check ' + field.dataset.fieldLabel.toLowerCase() + '.'];
            });
            return { values: values, errors: errors };
        }
        function busyEntry(section, value) {
            section.busy = value; mutations += value ? 1 : -1;
            section.editor.disabled = value || section.editor.hidden;
            section.element.setAttribute('aria-busy', String(value));
            section.element.querySelectorAll('[data-entry-action]').forEach(function (button) { button.disabled = value; });
            section.element.querySelector('[data-entry-action="save"] .spinner-border').classList.toggle('d-none', !value);
            syncSaveButtons();
        }
        async function saveEntry(section) {
            if (section.busy || saving || completed || section.editor.hidden) return;
            var result = collectEntry(section);
            if (Object.keys(result.errors).length) { showEntryErrors(section, result.errors); return; }
            var editId = section.editId;
            // Encrypted URLs change on every response; the numeric row id is stable.
            var editRowId = section.editId && section.rows.find(function (row) { return row.encrypted_id === section.editId; });
            editRowId = editRowId && editRowId.id;
            var beforeIds = new Set(section.rows.map(function (row) { return String(row.id); }));
            var data = Object.assign({ draft_token: draft, kind: section.kind }, result.values);
            if (section.kind === 'nextofkin') { data.client_email = form.elements.email_address.value; data.client_mobile = normalizePhone(form.elements.mobile_number.value); }
            clearEntryErrors(section); busyEntry(section, true);
            try {
                await request(page.dataset.repeatersUrl + (editId ? '/' + encodeURIComponent(editId) : ''), { method: editId ? 'PUT' : 'POST', data: data });
                dirty = true; closeEditor(section);
                var loaded = await loadRows(section);
                if (loaded) sectionStatus(section, section.label + (editId ? ' updated.' : ' added to this form.') + ' The client has not been saved yet.');
            } catch (error) {
                if (error.status === 422) showEntryErrors(section, error.data.errors);
                else if (error.status === 401 || error.status === 419 || error.status === 403) showEntryErrors(section, {}, error.status === 403 ? 'You do not have permission to change this entry.' : 'Your session expired. Keep this page open and sign in again in another tab.');
                else {
                    // A lost response can follow a successful write. Reload before offering another attempt.
                    var recovered = await loadRows(section);
                    var matches = recovered && section.rows.filter(function (row) {
                        return (editId ? String(row.id) === String(editRowId) : !beforeIds.has(String(row.id))) && Object.entries(result.values).every(function (entry) { return String(row[entry[0]] || '') === entry[1]; });
                    });
                    if (matches && matches.length === 1) { dirty = true; closeEditor(section); sectionStatus(section, section.label + ' is present in this form. The client has not been saved yet.'); }
                    else showEntryErrors(section, {}, 'Could not confirm this change. Check the entries below before trying again. Your entered details are still here.');
                }
            } finally { busyEntry(section, false); }
        }
        async function removeEntry(section, id) {
            if (section.busy || saving || completed) return;
            var removedRow = section.rows.find(function (row) { return row.encrypted_id === id; });
            busyEntry(section, true);
            try {
                await request(page.dataset.repeatersUrl + '/' + encodeURIComponent(id), { method: 'DELETE' });
                dirty = true; section.removing = null;
                if (section.editId === id) closeEditor(section);
                if (await loadRows(section)) sectionStatus(section, section.label + ' removed from this form.');
            } catch (error) {
                var loaded = await loadRows(section);
                if (loaded && removedRow && !section.rows.some(function (row) { return String(row.id) === String(removedRow.id); })) { dirty = true; section.removing = null; renderRows(section); sectionStatus(section, 'The entry is no longer in this form.'); }
                else sectionStatus(section, 'Could not confirm the removal. Check the entries before trying again.', 'warning');
            } finally { busyEntry(section, false); }
        }
        function clientType() {
            var value = form.elements.client_type_id.value;
            var individual = value === '1', company = value === '3', entity = !individual;
            page.dataset.clientType = value;
            page.querySelectorAll('.individual-only, .company-only, .partnership-only').forEach(function (group) {
                var shared = group.classList.contains('company-only') && group.classList.contains('partnership-only');
                var visible = shared ? entity : group.classList.contains('company-only') ? company : group.classList.contains('partnership-only') ? value === '4' : individual;
                group.hidden = !visible;
                group.querySelectorAll('input, select, textarea').forEach(function (field) {
                    var editor = field.closest('[data-entry-editor]');
                    field.disabled = !visible || Boolean(editor && editor.hidden);
                });
            });
            ['first_name', 'last_name', 'gender'].forEach(function (name) { form.elements[name].required = individual; });
            form.elements.company_name.required = entity;
            page.querySelector('#entityNameLabel').innerHTML = (value === '4' ? 'Partnership/Trust Name' : 'Company Name') + ' <span class="text-danger">*</span>';
            form.elements.company_name.placeholder = value === '4' ? 'Enter partnership/trust name' : 'Enter company name';
            page.querySelectorAll('.kin-required-star').forEach(function (star) { star.hidden = !individual; });
            page.querySelector('[data-kin-requirement]').textContent = individual ? 'Required' : 'Optional';
            page.querySelector('[data-kin-help]').textContent = individual ? 'Add at least one next of kin for an individual client.' : 'You can add next of kin if relevant to this client. This section is optional.';
            page.querySelector('#clientRequirementsHelp').textContent = individual ? 'A bank account and next of kin are required for an individual client. Other contacts are optional.' : 'A bank account is required. Other contacts and next of kin are optional.';
        }
        async function checkIdentifier(field) {
            var type = form.elements.client_type_id.value;
            if ((field.name === 'national_id' && type !== '1') || (field.name === 'company_registration_number' && type !== '3')) return;
            var value = field.value.trim(), sequence = (identifierChecks[field.name] || 0) + 1;
            identifierChecks[field.name] = sequence;
            if (!value) return;
            try {
                var result = await request(queryUrl(page.dataset.identifierUrl, { client_type_id: type, value: value }));
                if (identifierChecks[field.name] !== sequence || field.value.trim() !== value || form.elements.client_type_id.value !== type) return;
                if (result.exists) {
                    var message = 'A client with this ' + (type === '3' ? 'registration number' : 'National ID') + ' already exists' + (result.reference_number ? ': ' + result.reference_number : '') + '.';
                    duplicateFields[field.name] = { value: value, message: message };
                    personalError(field, message);
                }
            } catch (error) { /* The server repeats this check on Save Client. */ }
        }
        function mainIssues() {
            var issues = [];
            clearPersonalErrors();
            form.elements.mobile_number.value = normalizePhone(form.elements.mobile_number.value);
            Array.from(form.elements).forEach(function (field) {
                if (!field.name || field.type === 'hidden' || field.disabled || !field.checkValidity) return;
                var labelElement = field.labels && field.labels[0];
                var label = labelElement ? labelElement.textContent.replace(/\*|\(optional\)/g, '').trim() : field.name;
                var message = field.required && !field.value.trim() ? label + ' is required.' : !field.checkValidity() ? field.name === 'mobile_number' ? 'Enter a valid mobile number, such as 0712 345 678.' : field.type === 'email' ? 'Enter a valid email address.' : 'Check ' + label.toLowerCase() + '.' : '';
                if (duplicateFields[field.name] && duplicateFields[field.name].value === field.value.trim()) message = duplicateFields[field.name].message;
                if (message) { personalError(field, message); issues.push({ key: field.name, message: message }); }
            });
            if (form.elements.client_type_id.value === '1' && !form.elements.national_id.value.trim() && !form.elements.passport_number.value.trim()) {
                var identityMessage = 'Enter either the National ID or Passport Number.';
                personalError(form.elements.national_id, identityMessage); personalError(form.elements.passport_number, identityMessage);
                issues.push({ key: 'national_id', message: identityMessage });
            }
            Object.values(sections).forEach(function (section) {
                if (section.element.closest('[hidden]')) return;
                if (!section.editor.hidden) issues.push({ key: 'entry_' + section.kind, message: 'Finish adding or updating the ' + section.label.toLowerCase() + ', or cancel that entry before saving the client.' });
            });
            var required = ['bank'].concat(form.elements.client_type_id.value === '1' ? ['nextofkin'] : []);
            required.forEach(function (kind) {
                var section = sections[kind], key = kind === 'bank' ? 'banks' : 'next_of_kin';
                if (!section.loaded) issues.push({ key: key, message: section.loadFailed ? 'Could not check ' + section.label.toLowerCase() + ' entries. Use Try again in that section.' : 'Please wait for ' + section.label.toLowerCase() + ' entries to finish loading.' });
                else if (!section.rows.length && section.editor.hidden) issues.push({ key: key, message: 'Add at least one ' + section.label.toLowerCase() + ' before saving the client.' });
            });
            return issues;
        }
        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (saving || completed || mutations) return;
            if (followingTab()) { nextStep.click(); return; }
            var issues = mainIssues();
            showSummary(issues, true);
            if (issues.length) return;
            var payload = new FormData(form);
            lockForSave(true); saveStatus('Saving client. Please keep this page open…');
            try {
                var response = await request(form.action, { method: 'POST', body: payload });
                if (response.success !== true || !response.redirect || new URL(response.redirect, global.location.href).origin !== global.location.origin) throw new Error('Unconfirmed save');
                completed = true; dirty = false; savedRedirect = response.redirect;
                saveStatus('Client saved successfully. Opening the client page…'); syncSaveButtons();
                global.location.assign(savedRedirect);
            } catch (error) {
                if (completed) {
                    saving = false; form.setAttribute('aria-busy', 'false'); syncSaveButtons();
                    saveStatus('Client saved successfully. Open its page to continue.', false, savedRedirect, 'Open Client');
                    return;
                }
                lockForSave(false);
                var failure = saveFailure(error.status, error.data);
                if (failure === 'validation') {
                    var items = errorItems(error.data.errors);
                    items.forEach(function (item) { personalError(form.elements.namedItem(item.key), item.message); });
                    saveStatus(''); showSummary(items.length ? items : [{ key: '', message: 'Check the required client details and try again.' }], true);
                } else if (failure === 'session') saveStatus('Your session expired. Your entered details are still here. Keep this page open, sign in again in another tab, then try saving.', true);
                else if (failure === 'permission') saveStatus('You do not have permission to create this client. Your entered details are still here.', true);
                else if (failure === 'not_saved') saveStatus('The client could not be saved. Your entered details and added entries are still here. Please try again.', true);
                else saveStatus('We could not confirm whether the client was saved. Check the Clients list before trying again. Your entered details are still here.', true, page.dataset.clientsUrl);
                status.scrollIntoView({ block: 'center', behavior: 'smooth' });
            }
        });
        tabs.forEach(function (tab) { tab.addEventListener('shown.bs.tab', syncNextStep); });
        nextStep.addEventListener('click', function () {
            if (saving || completed || mutations) return;
            var next = followingTab();
            if (!next) return;
            next.addEventListener('shown.bs.tab', function () {
                next.focus({ preventScroll: true });
                page.querySelector(next.dataset.bsTarget).scrollIntoView({ block: 'start', behavior: 'smooth' });
            }, { once: true });
            global.bootstrap.Tab.getOrCreateInstance(next).show();
        });
        page.addEventListener('click', function (event) {
            var errorLink = event.target.closest('[data-error-target]');
            if (errorLink) { reveal(issueTarget(errorLink.dataset.errorTarget) || summary); return; }
            var button = event.target.closest('[data-entry-action]');
            if (!button || saving || completed) return;
            var element = button.closest('[data-client-repeater]');
            var section = sections[element.dataset.clientRepeater];
            if (section.busy) return;
            var id = button.dataset.entryId, name = button.dataset.entryAction;
            if (name === 'add') openEditor(section);
            else if (name === 'edit') openEditor(section, section.rows.find(function (row) { return row.encrypted_id === id; }));
            else if (name === 'cancel') {
                var changed = Object.entries(section.fields).some(function (entry) { return entry[1].value !== section.initialValues[entry[0]]; });
                if (!changed || global.confirm('Discard the unsaved changes to this entry?')) { closeEditor(section); reveal(section.addButton.hidden ? section.list.querySelector('[data-entry-action="add"]') : section.addButton); }
            } else if (name === 'save') saveEntry(section);
            else if (name === 'remove') { section.removing = id; renderRows(section); }
            else if (name === 'keep') { section.removing = null; renderRows(section); }
            else if (name === 'confirm-remove') removeEntry(section, id);
            else if (name === 'reload') { sectionStatus(section, ''); loadRows(section); }
            else if (name === 'reload-banks') { sectionStatus(section, 'Loading bank choices…'); banksRequest = loadBanks().then(function (ok) { if (ok) sectionStatus(section, 'Bank choices are ready.'); return ok; }); }
        });
        form.addEventListener('input', function (event) {
            dirty = true;
            var field = event.target;
            if (field.name) {
                delete duplicateFields[field.name];
                field.classList.remove('is-invalid'); field.removeAttribute('aria-invalid');
                var error = field.parentElement.querySelector('[data-main-field-error]'); if (error) error.remove();
            }
        });
        form.addEventListener('change', function (event) {
            dirty = true;
            if (event.target.name === 'client_type_id') clientType();
            if (event.target.dataset.entryField === 'bank_id') populateBranches(sections.bank);
        });
        form.addEventListener('focusout', function (event) {
            var field = event.target;
            if (field.name === 'mobile_number') field.value = normalizePhone(field.value);
            if (field.name === 'national_id' || field.name === 'company_registration_number') checkIdentifier(field);
            if (field.dataset.entryField && field.type === 'text') field.value = field.value.toUpperCase();
        });
        form.addEventListener('keydown', function (event) {
            var editor = event.target.closest('[data-entry-editor]');
            if (editor && event.key === 'Enter' && event.target.tagName === 'INPUT') { event.preventDefault(); saveEntry(sections[editor.closest('[data-client-repeater]').dataset.clientRepeater]); }
        });
        page.querySelectorAll('[data-client-repeater]').forEach(function (element) {
            var section = { element: element, kind: element.dataset.clientRepeater, label: element.dataset.label, icon: element.dataset.icon, rows: [], loaded: false, loadFailed: false, busy: false, sequence: 0, fields: {}, editId: null, removing: null };
            section.editor = element.querySelector('[data-entry-editor]'); section.errors = element.querySelector('[data-entry-errors]'); section.list = element.querySelector('[data-entry-list]'); section.status = element.querySelector('[data-entry-status]'); section.addButton = element.querySelector('[data-entry-action="add"]');
            element.querySelectorAll('[data-entry-field]').forEach(function (field) { section.fields[field.dataset.entryField] = field; });
            sections[section.kind] = section;
        });
        var nationality = form.elements.nationality_id;
        if (nationality.dataset.initialValue) nationality.value = nationality.dataset.initialValue;
        clientType();
        syncNextStep();
        Object.values(sections).forEach(loadRows);
        banksRequest = loadBanks();
        global.addEventListener('beforeunload', function (event) { if ((dirty || saving || mutations) && !completed) { event.preventDefault(); event.returnValue = ''; } });
        global.addEventListener('pageshow', function (event) {
            if (!event.persisted) return;
            if (completed) { saving = false; syncSaveButtons(); saveStatus('This client was already saved. Open its page to continue.', false, savedRedirect, 'Open Client'); }
            else if (saving) { lockForSave(false); saveStatus('Check the Clients list to confirm whether the previous save completed before trying again.', true, page.dataset.clientsUrl); }
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize); else initialize();
})(typeof window !== 'undefined' ? window : globalThis);
