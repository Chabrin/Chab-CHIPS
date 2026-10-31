<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('staffCreateForm') || document.getElementById('staffEditForm');
    if (!form) return;
    const inputs = Array.from(form.querySelectorAll('input[type="file"]'));
    const states = new Map();
    const uploadLimits = @json(\App\Support\EmployeeUploadLimits::current());
    let revision = 0;
    let pending = false;
    let saving = false;
    let summary;

    const labels = {
        photo: 'Staff photo', 'documents.national_id': 'National ID / Passport copy',
        'documents.kra_pin': 'KRA PIN certificate', 'documents.contract': 'Signed employment contract',
        'documents.cv': 'CV', 'documents.academic': 'Academic certificates'
    };
    const label = input => labels[fieldName(input)] || input.name;
    const limitFor = input => Math.min((input.name === 'photo' ? 2 : 5) * 1024 * 1024, uploadLimits.file || Infinity);
    const megabytes = bytes => Number((bytes / 1024 / 1024).toFixed(2)) + ' MB';

    function showErrors(message, errors = {}, retry = false) {
        if (!summary) {
            summary = document.createElement('div');
            summary.setAttribute('role', 'alert');
            form.insertAdjacentElement('beforebegin', summary);
        }
        summary.className = 'alert alert-danger';
        summary.dataset.source = retry ? 'check' : 'save';
        summary.replaceChildren();
        const heading = document.createElement('div');
        heading.textContent = message;
        summary.appendChild(heading);
        const list = document.createElement('ul');
        Object.values(errors).flat().forEach(message => {
            const item = document.createElement('li'); item.textContent = message; list.appendChild(item);
        });
        summary.appendChild(list);
        if (retry) {
            const button = document.createElement('button');
            button.type = 'button'; button.className = 'btn btn-outline-danger btn-sm mt-2';
            button.textContent = 'Check selected files again'; button.addEventListener('click', check);
            summary.appendChild(button);
        }
    }

    function reveal(input) {
        const pane = input.closest('.tab-pane');
        const tab = pane && document.getElementById(pane.id + '-tab');
        if (tab && window.bootstrap) new bootstrap.Tab(tab).show();
        input.focus();
    }

    function busy(value) {
        ['createButton', 'updateButton', 'updateButtonFooter'].forEach(id => {
            const button = document.getElementById(id);
            if (button) { button.disabled = value; button.style.opacity = value ? '0.6' : '1'; }
        });
        const spinner = document.getElementById('submitSpinner');
        if (spinner) spinner.classList.toggle('d-none', !value);
    }

    function fieldName(input) {
        return input.name.replace(/\[([^\]]+)\]/g, '.$1');
    }
    function feedback(input, message, invalid) {
        let note = input.parentElement.querySelector('[data-duplicate-feedback="' + fieldName(input) + '"]');
        if (!note) {
            note = document.createElement('div');
            note.dataset.duplicateFeedback = fieldName(input);
            note.setAttribute('aria-live', 'polite');
            input.insertAdjacentElement('afterend', note);
        }
        note.textContent = message;
        note.className = 'small mt-1 ' + (invalid ? 'text-danger' : 'text-muted');
        input.setCustomValidity(invalid ? message : '');
        input.classList.toggle('is-invalid', invalid);
    }

    async function check() {
        const current = ++revision;
        const selected = inputs.filter(input => input.files.length);
        pending = selected.length > 0;
        inputs.forEach(input => feedback(input, input.files.length ? 'Checking for duplicate files…' : '', false));
        states.clear();
        if (!selected.length) return;

        try {
            const hashes = {};
            for (const input of selected) {
                const file = input.files[0];
                // Oversized files are rejected on save; avoid reading them into browser memory.
                const limit = limitFor(input);
                if (file.size > limit) {
                    if (current !== revision) return;
                    const message = label(input) + ': This server accepts up to ' + megabytes(limit) + ' per file. Choose a smaller file; your other selections have been kept.';
                    states.set(input, message);
                    continue;
                }
                const digest = await crypto.subtle.digest('SHA-256', await file.arrayBuffer());
                if (current !== revision) return;
                hashes[fieldName(input)] = Array.from(new Uint8Array(digest), byte => byte.toString(16).padStart(2, '0')).join('');
            }

            const seen = new Map();
            selected.forEach(input => {
                const hash = hashes[fieldName(input)];
                if (hash && seen.has(hash)) states.set(input, label(input) + ': This file is already selected for ' + label(seen.get(hash)) + '. Choose a different file.');
                if (hash) seen.set(hash, input);
            });
            if (uploadLimits.post > 0 && selected.reduce((size, input) => size + input.files[0].size, 0) > uploadLimits.post) {
                selected.forEach(input => states.set(input, 'The combined files exceed this server’s ' + megabytes(uploadLimits.post) + ' request limit. Choose smaller files. Your selections have been kept.'));
            }
            // Show same-form duplicates without waiting for the server.
            selected.forEach(input => {
                if (states.has(input)) feedback(input, states.get(input), true);
            });

            if (Object.keys(hashes).length) {
                const response = await fetch(@json(route('hr.uploads.check-duplicates')), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value
                    },
                    body: JSON.stringify({ hashes, staff_id: form.dataset.employeeId || null })
                });
                if (current !== revision) return;
                const data = await response.json();
                if (current !== revision) return;
                if (!response.ok && response.status !== 422) throw new Error('Check unavailable');
                if (response.status === 422) {
                    if (!data.errors) throw new Error('Invalid check response');
                    selected.forEach(input => {
                        const errors = data.errors[fieldName(input)];
                        if (errors) states.set(input, errors[0]);
                    });
                }
            }
            selected.forEach(input => feedback(input, states.get(input) || '', states.has(input)));
            if (!states.size && summary?.dataset.source === 'check') summary.classList.add('d-none');
        } catch (error) {
            if (current !== revision) return;
            selected.forEach(input => {
                if (!states.has(input)) states.set(input, label(input) + ': The duplicate check could not finish. Retry the check before saving; your file is still selected.');
                feedback(input, states.get(input), true);
            });
            showErrors('Files have not been saved. Retry the duplicate check before saving.', {}, true);
        } finally {
            if (current === revision) pending = false;
        }
    }

    inputs.forEach(input => {
        const hint = document.createElement('div');
        hint.className = 'small text-muted mt-1';
        hint.textContent = 'Maximum ' + megabytes(limitFor(input)) + ' per file on this server. Duplicate files are not allowed.';
        input.insertAdjacentElement('afterend', hint);
        input.addEventListener('change', check);
    });
    form.addEventListener('input', event => {
        if (event.target.type === 'file') return;
        event.target.setCustomValidity('');
        event.target.classList.remove('is-invalid');
        const note = event.target.parentElement.querySelector('[data-duplicate-feedback="' + fieldName(event.target) + '"]');
        if (note) note.textContent = '';
    });

    // JSON validation errors keep the current document and File objects alive, unlike a redirect.
    window.EmployeeStaffForm = { async save() {
        if (saving || pending || states.size) { busy(false); return; }
        saving = true; busy(true);
        if (summary) summary.classList.add('d-none');
        try {
            const response = await fetch(form.action, {
                method: 'POST', credentials: 'same-origin', body: new FormData(form),
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = response.headers.get('content-type')?.includes('application/json') ? await response.json() : {};
            if (response.ok && data.redirect_url && !response.redirected) {
                window.location.assign(data.redirect_url); return;
            }
            const errors = data.errors || {};
            let first;
            Array.from(form.elements).forEach(input => {
                const messages = errors[fieldName(input)];
                if (!messages) return;
                const text = messages.join(' ');
                feedback(input, text, true);
                if (input.type === 'file') states.set(input, text);
                first ||= input;
            });
            const message = response.status === 413
                ? 'The request exceeds this server’s upload limit. Choose smaller files. All current selections have been kept.'
                : response.status === 419 || response.status === 401 || response.redirected
                    ? 'Your session may have expired. Sign in in another tab, then retry here. Your form and files have been kept.'
                    : data.message || 'Save could not be confirmed. Your form and files have been kept. Check the Employees list in another tab before retrying.';
            showErrors(message, errors);
            if (first) reveal(first);
        } catch (error) {
            showErrors('Save could not be confirmed because the connection failed. Your form and files have been kept. Check the Employees list in another tab before retrying.');
        } finally { saving = false; busy(false); }
    } };
    // Capture before existing form handlers disable buttons or clear validation styles.
    form.addEventListener('submit', function (event) {
        if (!pending && !states.size && !saving) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        if (saving) return;
        const input = states.keys().next().value || inputs.find(input => input.files.length);
        if (!input) return;
        feedback(input, states.get(input) || 'Please wait for the duplicate check to finish.', true);
        reveal(input);
    }, true);
});
</script>
