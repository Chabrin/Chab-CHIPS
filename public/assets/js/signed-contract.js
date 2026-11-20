// The signed contract box on the contract forms (see staff/contracts/partials/signed-contract.blade.php). While a file is on record the
// box shows its name, when it was uploaded and by whom, with View and Remove, and the upload button is hidden. Remove asks first, the
// server keeps the file in the removal history, and then the upload button appears.
(function (root) {
    'use strict';

    // "Uploaded 12 Nov 2026 by Hannah", with whatever part is known
    function describe(doc) {
        if (!doc || (!doc.uploaded && !doc.by)) return '';
        return 'Uploaded' + (doc.uploaded ? ' ' + doc.uploaded : '') + (doc.by ? ' by ' + doc.by : '');
    }

    function viewUrl(base, id) {
        return String(base).replace(/\/+$/, '') + '/' + encodeURIComponent(id) + '/view';
    }

    function removeUrl(base, id) {
        return String(base).replace(/\/+$/, '') + '/' + encodeURIComponent(id);
    }

    // Wire the box. Returns { show(doc), showUpload(), current() }. ajax is $.ajax (or a stand-in); confirmFn is window.confirm.
    function attach(doc, options) {
        options = options || {};
        var card = doc.getElementById('signed_contract_card');
        var input = doc.getElementById('signed_contract');
        var upload = doc.getElementById('signed_contract_upload');
        if (!card || !input || !upload) return null;
        var nameEl = doc.getElementById('signed_contract_name');
        var metaEl = doc.getElementById('signed_contract_meta');
        var noteEl = doc.getElementById('signed_contract_note');
        var viewEl = doc.getElementById('signed_contract_view');
        var removeEl = doc.getElementById('signed_contract_remove');
        var base = card.getAttribute('data-documents-url') || '/documents';
        var current = null;
        var busy = false;

        // Show a file on record: its card, and no upload button
        function show(file) {
            current = file;
            nameEl.textContent = file.name || '';
            metaEl.textContent = describe(file);
            if (noteEl) noteEl.classList.toggle('d-none', !file.attachedOnSave);
            // Someone who may not open staff documents has no View button
            if (viewEl) viewEl.setAttribute('href', viewUrl(base, file.id));
            if (removeEl) removeEl.setAttribute('data-document-id', file.id);
            card.classList.remove('d-none');
            // A hidden, emptied, disabled box sends nothing, so the contract takes the file already on record
            input.value = '';
            input.disabled = true;
            upload.classList.add('d-none');
        }

        // Nothing on record (or it was removed): the upload button
        function showUpload() {
            current = null;
            card.classList.add('d-none');
            input.disabled = false;
            upload.classList.remove('d-none');
        }

        if (removeEl) {
            removeEl.addEventListener('click', function () {
                var id = current ? current.id : removeEl.getAttribute('data-document-id');
                if (!id || busy) return;
                var ask = options.confirm || root.confirm;
                if (!ask('Remove this contract? It is taken off the contract, and a copy is kept in the history.')) return;
                busy = true;
                removeEl.disabled = true;
                (options.ajax || root.jQuery.ajax)({
                    url: removeUrl(base, id),
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': options.token || '', 'Accept': 'application/json' },
                    success: function () {
                        var removed = current;
                        showUpload();
                        if (options.onRemoved) options.onRemoved(removed);
                        if (options.notify) options.notify('success', 'Contract removed. A copy is kept in the history.');
                    },
                    error: function (xhr) {
                        var message = xhr && xhr.status === 403 ? 'You do not have permission to remove this contract.' : 'The contract could not be removed. Please try again.';
                        if (options.notify) options.notify('error', message);
                    },
                    complete: function () {
                        busy = false;
                        removeEl.disabled = false;
                    }
                });
            });
        }

        // A card that came with the page (the Edit form) already has its file: remember it so Remove knows which
        var startId = removeEl ? removeEl.getAttribute('data-document-id') : '';
        if (!card.classList.contains('d-none') && startId) {
            current = { id: startId, name: nameEl.textContent, attachedOnSave: noteEl ? !noteEl.classList.contains('d-none') : false };
        }

        return { show: show, showUpload: showUpload, current: function () { return current; } };
    }

    root.SignedContract = { describe: describe, viewUrl: viewUrl, removeUrl: removeUrl, attach: attach };
})(typeof window !== 'undefined' ? window : this);
