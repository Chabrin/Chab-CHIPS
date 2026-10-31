<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('staffCreateForm') || document.getElementById('staffEditForm');
    if (!form) return;
    const inputs = Array.from(form.querySelectorAll('input[type="file"]'));
    const states = new Map();
    let revision = 0;
    let pending = false;

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
                const limit = input.name === 'photo' ? 2 : 5;
                if (file.size > limit * 1024 * 1024) {
                    if (current !== revision) return;
                    const message = 'Select a file smaller than ' + limit + ' MB.';
                    states.set(input, message);
                    continue;
                }
                const digest = await crypto.subtle.digest('SHA-256', await file.arrayBuffer());
                if (current !== revision) return;
                hashes[fieldName(input)] = Array.from(new Uint8Array(digest), byte => byte.toString(16).padStart(2, '0')).join('');
            }

            const seen = new Set();
            selected.forEach(input => {
                const hash = hashes[fieldName(input)];
                if (hash && seen.has(hash)) states.set(input, 'This file is selected in another upload field. Please select a different file.');
                if (hash) seen.add(hash);
            });
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
                    body: JSON.stringify({ hashes })
                });
                if (current !== revision) return;
                const data = await response.json();
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
        } catch (error) {
            if (current !== revision) return;
            selected.forEach(input => feedback(input, states.get(input) || 'Live check unavailable. Files will be checked when you save.', states.has(input)));
        } finally {
            if (current === revision) pending = false;
        }
    }

    inputs.forEach(input => input.addEventListener('change', check));
    // Capture before existing form handlers disable buttons or clear validation styles.
    form.addEventListener('submit', function (event) {
        if (!pending && !states.size) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        const input = states.keys().next().value || inputs.find(input => input.files.length);
        if (!input) return;
        const pane = input.closest('.tab-pane');
        const tab = pane && document.getElementById(pane.id + '-tab');
        if (tab && window.bootstrap) new bootstrap.Tab(tab).show();
        feedback(input, states.get(input) || 'Please wait for the duplicate check to finish.', true);
        input.focus();
    }, true);
});
</script>
