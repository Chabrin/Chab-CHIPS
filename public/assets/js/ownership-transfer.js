document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('ownership-transfer-form');
    if (!form) return;
    const property = document.getElementById('property_id');
    const landlord = document.getElementById('to_client_id');
    const bank = document.getElementById('bank_detail_id');
    const help = document.getElementById('bank-help');
    const retry = document.getElementById('retry-banks');
    const documents = document.getElementById('handover_documents');
    const fileList = document.getElementById('selected-documents');
    const $ = window.jQuery;
    let generation = 0;

    function updateProperty() {
        const option = property.selectedOptions[0];
        document.getElementById('property-context').textContent = property.value
            ? 'Current landlord: ' + (option.dataset.owner || 'Not recorded') + ' · Property reference: ' + (option.dataset.reference || 'Not recorded')
            : 'Select a property to see its current landlord.';
    }

    async function loadBanks(selectedBank = '') {
        const current = ++generation;
        retry.hidden = true;
        bank.disabled = true;
        bank.replaceChildren(new Option(landlord.value ? 'Loading accounts…' : 'Select a landlord first', ''));
        help.textContent = landlord.value ? 'Loading registered payout accounts…' : "Accounts load from the incoming landlord's bank details.";
        if (!landlord.value) return;
        try {
            const response = await fetch(form.dataset.banksUrl + '/' + encodeURIComponent(landlord.value), {headers: {'Accept': 'application/json'}});
            if (!response.ok) throw new Error('Unable to load accounts');
            const data = await response.json();
            if (current !== generation) return;
            bank.replaceChildren(new Option(data.banks.length ? 'Select a payout account' : 'No registered accounts', ''));
            data.banks.forEach(item => bank.add(new Option(item.label, item.id)));
            bank.disabled = !data.banks.length;
            bank.value = selectedBank;
            help.textContent = data.banks.length ? 'Verify the payout account before submitting.' : "Add a bank account to this landlord's client record, then retry.";
            retry.hidden = !!data.banks.length;
        } catch (error) {
            if (current !== generation) return;
            bank.replaceChildren(new Option('Accounts could not be loaded', ''));
            help.textContent = 'Unable to load payout accounts. Please retry.';
            retry.hidden = false;
        }
    }

    if ($ && $.fn.select2) {
        $('.transfer-search').each(function () {
            const select = $(this);
            select.select2({
                width: '100%', placeholder: this.dataset.placeholder,
                minimumResultsForSearch: 0, dropdownCssClass: 'transfer-select-dropdown'
            });
            select.on('select2:open', function () {
                const search = document.querySelector('.select2-container--open .select2-search__field');
                if (search) {
                    search.placeholder = this.dataset.placeholder;
                    search.setAttribute('aria-label', this.dataset.placeholder);
                    search.focus();
                }
            });
        });
        $(property).on('change', updateProperty);
        $(landlord).on('change', () => loadBanks());
    } else {
        property.addEventListener('change', updateProperty);
        landlord.addEventListener('change', () => loadBanks());
    }
    retry.addEventListener('click', () => loadBanks());
    documents.addEventListener('change', function () {
        fileList.replaceChildren();
        Array.from(this.files).forEach(file => {
            const item = document.createElement('li');
            item.textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)';
            fileList.appendChild(item);
        });
        fileList.hidden = !this.files.length;
    });
    // Keep required searchable selects reachable when native validation fails.
    if ($ && $.fn.select2) {
        [property, landlord].forEach(select => select.addEventListener('invalid', function (event) {
            event.preventDefault();
            if (property.validity.valid || select === property) $(select).select2('open');
        }));
    }
    updateProperty();
    if (landlord.value) loadBanks(form.dataset.oldBank);
});
