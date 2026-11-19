(function () {
    'use strict';

    function fieldName(key) {
        const parts = key.split('.');
        return parts[0] + parts.slice(1).map(part => '[' + part + ']').join('');
    }

    if (typeof document === 'undefined') return;
    window.jQuery(function () {
        const form = document.getElementById('unitTenantForm');
        if (!form) return;
        const summary = document.getElementById('tenantFormError');
        const status = document.getElementById('tenantSaveStatus');
        const save = document.getElementById('saveButton');
        const spinner = document.getElementById('submitSpinner');
        const previous = document.getElementById('tenantPreviousSection');
        const next = document.getElementById('tenantNextSection');
        const position = document.getElementById('tenantSectionPosition');
        const tabs = Array.from(document.querySelectorAll('#unitTenantTabs [role="tab"]'));
        let saving = false;
        let completed = false;
        let messageId = 0;

        function target(key) {
            return form.elements.namedItem(fieldName(key));
        }

        function reveal(field) {
            if (!field || field.type === 'hidden') {
                summary.focus();
                summary.scrollIntoView({ block: 'center' });
                return;
            }
            const pane = field.closest('.tab-pane');
            const tab = pane && document.getElementById(pane.getAttribute('aria-labelledby'));
            const accordion = field.closest('.accordion-collapse');
            function focus() {
                field.focus({ preventScroll: true });
                field.scrollIntoView({ block: 'center' });
            }
            function openAccordion() {
                if (accordion && !accordion.classList.contains('show')) {
                    accordion.addEventListener('shown.bs.collapse', focus, { once: true });
                    window.bootstrap.Collapse.getOrCreateInstance(accordion, { toggle: false }).show();
                } else {
                    focus();
                }
            }
            if (pane && !pane.classList.contains('active')) {
                tab.addEventListener('shown.bs.tab', openAccordion, { once: true });
                window.bootstrap.Tab.getOrCreateInstance(tab).show();
            } else {
                openAccordion();
            }
        }

        function fieldError(field, message) {
            if (!field || !field.classList) return;
            field.classList.add('is-invalid');
            field.setAttribute('aria-invalid', 'true');
            let hint = field.parentElement.querySelector('.invalid-feedback');
            if (!hint) {
                hint = document.createElement('div');
                hint.className = 'invalid-feedback';
                field.insertAdjacentElement('afterend', hint);
            }
            if (!hint.id) hint.id = 'tenant-field-error-' + (++messageId);
            hint.textContent = message;
            field.setAttribute('aria-errormessage', hint.id);
        }

        function showErrors(items) {
            summary.replaceChildren();
            summary.hidden = items.length === 0;
            if (!items.length) return;
            const title = document.createElement('strong');
            title.textContent = 'Tenant could not be saved';
            const list = document.createElement('ul');
            items.forEach(function (item) {
                const row = document.createElement('li');
                const button = document.createElement('button');
                button.type = 'button';
                button.dataset.errorTarget = item.key;
                button.textContent = item.message;
                row.append(button);
                list.append(row);
                fieldError(target(item.key), item.message);
            });
            summary.append(title, list);
            reveal(target(items[0].key));
        }

        function updateNavigation() {
            const index = tabs.findIndex(tab => tab.classList.contains('active'));
            previous.disabled = index === 0 || saving;
            next.hidden = index === tabs.length - 1;
            next.disabled = saving;
            next.textContent = tabs[index + 1] ? 'Next: ' + tabs[index + 1].textContent.trim() : 'Next section';
            position.textContent = 'Section ' + (index + 1) + ' of ' + tabs.length + ' · ' + tabs[index].textContent.trim();
        }

        function changeSection(direction) {
            const index = tabs.findIndex(tab => tab.classList.contains('active'));
            const tab = tabs[index + direction];
            if (tab) window.bootstrap.Tab.getOrCreateInstance(tab).show();
        }

        previous.addEventListener('click', () => changeSection(-1));
        next.addEventListener('click', () => changeSection(1));
        tabs.forEach(tab => tab.addEventListener('shown.bs.tab', updateNavigation));
        summary.addEventListener('click', function (event) {
            const button = event.target.closest('[data-error-target]');
            if (button) reveal(target(button.dataset.errorTarget));
        });

        function validationErrors() {
            const items = [];
            Array.from(form.elements).forEach(function (field) {
                if (!field.willValidate || field.validity.valid) return;
                const label = field.labels?.[0]?.textContent.trim().replace(/\s*\*$/, '') || field.name;
                items.push({ key: field.name, message: label + ': ' + field.validationMessage });
            });
            if (form.elements.tenant_type.value === '1'
                && !form.elements.national_id.value.trim() && !form.elements.passport_number.value.trim()) {
                items.push({ key: 'national_id', message: 'Enter a National ID or Passport Number in Tenant Details.' });
            }
            const identifiers = form.elements.tenant_type.value === '3'
                ? ['company_registration_number'] : ['national_id', 'passport_number'];
            identifiers.forEach(function (key) {
                const field = form.elements.namedItem(key);
                const value = field.value.trim();
                const stored = window.jQuery(field);
                if (value && stored.data('duplicate-flagged') === value && stored.data('duplicate-confirmed') !== value) {
                    items.push({ key: key, message: 'Resolve the duplicate identifier warning before saving.' });
                }
            });
            return items;
        }

        form.addEventListener('input', function (event) {
            if (event.target.validity?.valid) {
                event.target.classList.remove('is-invalid');
                event.target.removeAttribute('aria-invalid');
                event.target.removeAttribute('aria-errormessage');
            }
        });

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (saving || completed) return;
            const items = validationErrors();
            showErrors(items);
            if (items.length) return;
            const body = new FormData(form);
            saving = true;
            save.disabled = true;
            spinner.classList.remove('d-none');
            status.hidden = false;
            status.textContent = 'Saving tenant and deposit. Please keep this page open…';
            updateNavigation();
            const abort = new AbortController();
            const timeout = window.setTimeout(() => abort.abort(), 45000);
            try {
                const response = await window.fetch(form.action, {
                    method: 'POST', body: body, credentials: 'same-origin', signal: abort.signal,
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value }
                });
                const data = (response.headers.get('content-type') || '').includes('application/json')
                    ? await response.json() : {};
                if (response.status === 422 && data.errors) {
                    const errors = Object.entries(data.errors).flatMap(([key, messages]) =>
                        messages.map(message => ({ key: key, message: message })));
                    showErrors(errors);
                    status.hidden = true;
                    return;
                }
                if (!response.ok || data.success !== true || !data.redirect) {
                    let message = data.message || 'The save could not be confirmed. Check New Tenant Approvals before trying again.';
                    if (response.status === 401 || response.status === 419) message = 'Your session expired. Keep this form open and sign in again in another tab.';
                    if (response.status === 403) message = 'Your account does not have permission to save this tenant.';
                    showErrors([{ key: '', message: message }]);
                    status.hidden = true;
                    return;
                }
                const destination = new URL(data.redirect, window.location.href);
                if (destination.origin !== window.location.origin) throw new Error('Unexpected redirect');
                completed = true;
                status.textContent = 'Tenant saved and signed.';
                const link = document.createElement('a');
                link.href = destination.href;
                link.textContent = 'Open the approval review';
                status.append(' ', link);
                window.location.assign(destination.href);
            } catch (error) {
                if (!completed) {
                    showErrors([{ key: '', message: 'The save could not be confirmed. Keep this form open and check New Tenant Approvals before trying again.' }]);
                    const link = document.createElement('a');
                    link.href = form.dataset.reviewsUrl;
                    link.target = '_blank';
                    link.rel = 'noopener';
                    link.textContent = 'Check New Tenant Approvals';
                    summary.append(link);
                    status.hidden = true;
                }
            } finally {
                window.clearTimeout(timeout);
                saving = false;
                save.disabled = completed;
                spinner.classList.add('d-none');
                updateNavigation();
            }
        });

        const serverErrors = Array.from(summary.querySelectorAll('[data-error-target]')).map(button =>
            ({ key: button.dataset.errorTarget, message: button.textContent }));
        if (serverErrors.length) showErrors(serverErrors);
        updateNavigation();
    });
})();
