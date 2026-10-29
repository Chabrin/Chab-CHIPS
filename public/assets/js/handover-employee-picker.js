(function (root) {
    'use strict';

    const help = 'Type at least 2 letters, then choose an employee from the results.';
    const normalize = text => String(text).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase().trim();

    function init(picker) {
        const select = picker.querySelector('select');
        const input = picker.querySelector('[data-handover-input]');
        const clearButton = picker.querySelector('[data-handover-clear]');
        const results = picker.querySelector('[data-handover-results]');
        const hint = picker.querySelector('[data-handover-hint]');
        let active = -1;
        let choices = [];
        let updating = false;

        function close() {
            results.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
            active = -1;
        }

        function setValue(value) {
            updating = true;
            try {
                select.value = value;
                select.dispatchEvent(new root.Event('change', { bubbles: true }));
            } finally {
                updating = false;
            }
        }

        function sync() {
            if (updating) return;
            const selected = Array.from(select.options).find(option => option.value && option.value === select.value && !option.disabled);
            if (!selected) select.value = '';
            input.value = selected ? selected.textContent : '';
            input.setCustomValidity('');
            clearButton.hidden = !selected;
            hint.textContent = selected ? 'Selected: ' + selected.textContent + '. Use Clear to remove or search for someone else.' : help;
            close();
        }

        function choose(option) {
            // Recheck eligibility: HR can change the applicant while this list is open.
            if (option.disabled || !option.value) return;
            setValue(option.value);
            sync();
            input.focus();
            close();
        }

        function render() {
            results.replaceChildren();
            active = -1;
            input.removeAttribute('aria-activedescendant');
            const term = normalize(input.value);
            if (term.length < 2 || select.value) {
                close();
                if (!select.value) hint.textContent = help;
                return;
            }
            const words = term.split(/\s+/);
            const matches = Array.from(select.options).filter(option => option.value && !option.disabled && words.every(word => normalize(option.textContent).includes(word)));
            choices = matches.slice(0, 10);
            choices.forEach((option, index) => {
                const result = root.document.createElement('button');
                result.type = 'button';
                result.tabIndex = -1;
                result.id = results.id + '_' + index;
                result.className = 'handover-picker__option';
                result.setAttribute('role', 'option');
                result.setAttribute('aria-selected', 'false');
                result.textContent = option.textContent;
                result.addEventListener('mousedown', event => event.preventDefault());
                result.addEventListener('click', () => choose(option));
                results.appendChild(result);
            });
            hint.textContent = !matches.length ? 'No matching employees. Try another name.'
                : matches.length > 10 ? 'Showing 10 of ' + matches.length + ' matches. Keep typing to narrow the results.'
                    : matches.length + (matches.length === 1 ? ' employee found. Choose their name.' : ' employees found. Choose a name.');
            results.hidden = !choices.length;
            input.setAttribute('aria-expanded', String(choices.length > 0));
        }

        input.addEventListener('input', function () {
            // Searching is never a selection; discard the previous employee immediately.
            setValue('');
            clearButton.hidden = !input.value;
            input.setCustomValidity(input.value.trim() ? 'Choose an employee from the matching names, or clear the search.' : '');
            render();
        });
        input.addEventListener('focus', render);
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' || event.key === 'Tab') {
                const wasOpen = !results.hidden;
                close();
                if (event.key === 'Escape' && wasOpen) event.stopPropagation();
                return;
            }
            if (event.key === 'Enter') {
                // Enter only confirms an explicitly highlighted result; never pick the first name by default.
                event.preventDefault();
                if (!results.hidden && active >= 0) choose(choices[active]);
                return;
            }
            if (!['ArrowDown', 'ArrowUp'].includes(event.key)) return;
            event.preventDefault();
            if (results.hidden) render();
            if (results.hidden) return;
            active = event.key === 'ArrowDown' ? Math.min(active + 1, choices.length - 1)
                : active < 0 ? choices.length - 1 : Math.max(active - 1, 0);
            Array.from(results.children).forEach((result, index) => {
                result.classList.toggle('is-active', index === active);
                result.setAttribute('aria-selected', String(index === active));
            });
            const result = results.children[active];
            input.setAttribute('aria-activedescendant', result.id);
            result.scrollIntoView({ block: 'nearest' });
        });
        clearButton.addEventListener('click', function () {
            setValue('');
            sync();
            input.focus();
        });
        root.document.addEventListener('pointerdown', event => { if (!picker.contains(event.target)) close(); });
        root.document.addEventListener('focusin', event => { if (!picker.contains(event.target)) close(); });
        // Listen through jQuery as the HR edit form also changes the canonical select programmatically.
        root.jQuery(select).on('change.handoverPicker', sync);
        root.jQuery(select.form).on('reset.handoverPicker', () => root.setTimeout(sync, 0));
        root.jQuery(picker.closest('.modal')).on('hidden.bs.modal.handoverPicker', close);

        // Respect server-rendered defaults, rather than a browser-restored first employee.
        select.value = (Array.from(select.options).find(option => option.defaultSelected) || { value: '' }).value;
        sync();
        select.hidden = true;
        picker.querySelector('label').htmlFor = input.id;
        picker.querySelector('[data-handover-search]').hidden = false;
    }

    root.document.addEventListener('DOMContentLoaded', function () {
        root.document.querySelectorAll('[data-handover-picker]').forEach(init);
    });
})(window);
