// Upload boxes that show the file chosen: its name, its size, a Preview and a Remove (x) button. Remove clears the box
// completely, so the same or another file can be chosen straight away and nothing is saved with the form. Choosing another
// file in the same box replaces the first. Put data-file-slots on a form to get this on every upload box in it; leave one
// out with data-no-file-slot.
(function (root) {
    'use strict';

    // 0 B, 512 B, 1.5 KB, 2.3 MB
    function formatSize(bytes) {
        var size = Number(bytes);
        if (!isFinite(size) || size < 0) return '';
        if (size < 1024) return size + ' B';
        if (size < 1024 * 1024) return Number((size / 1024).toFixed(1)) + ' KB';
        return Number((size / 1024 / 1024).toFixed(2)) + ' MB';
    }

    // What the browser can show in a new tab: images and PDFs. Everything else (Word files) is only named.
    function kind(file) {
        var type = String((file && file.type) || '').toLowerCase();
        var name = String((file && file.name) || '').toLowerCase();
        if (/^image\/(png|jpe?g|gif|webp)$/.test(type) || /\.(png|jpe?g|gif|webp)$/.test(name)) return 'image';
        if (type === 'application/pdf' || /\.pdf$/.test(name)) return 'pdf';
        return 'other';
    }

    var STYLE = '.file-slot{display:flex;align-items:center;gap:.5rem;margin-top:.35rem;padding:.3rem .5rem;background:#f6f8fb;border:1px solid #e3e8ef;border-radius:.4rem;font-size:.8125rem}'
        + '.file-slot-name{flex:1 1 auto;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:600}'
        + '.file-slot-size{flex:0 0 auto;color:#6b7a8c}'
        + '.file-slot .btn{padding:.1rem .5rem;font-size:.75rem;line-height:1.5}';

    function addStyle(doc) {
        if (doc.getElementById('file-slot-style')) return;
        var style = doc.createElement('style');
        style.id = 'file-slot-style';
        style.textContent = STYLE;
        doc.head.appendChild(style);
    }

    function element(doc, tag, className, text) {
        var node = doc.createElement(tag);
        if (className) node.className = className;
        if (text != null) node.textContent = text;
        return node;
    }

    // Adds the panel under one upload box. Returns it, or null for a box that is left out.
    function attach(input, doc) {
        doc = doc || root.document;
        if (!input || input.dataset.fileSlotAttached || input.hasAttribute('data-no-file-slot') || input.multiple || input.name === 'signature_file') return null;
        input.dataset.fileSlotAttached = '1';
        addStyle(doc);

        var panel = element(doc, 'div', 'file-slot d-none');
        panel.setAttribute('data-file-slot', '');
        panel.setAttribute('aria-live', 'polite');
        var name = element(doc, 'span', 'file-slot-name');
        var size = element(doc, 'span', 'file-slot-size');
        var preview = element(doc, 'button', 'btn btn-outline-secondary file-slot-preview', 'Preview');
        preview.type = 'button';
        var remove = element(doc, 'button', 'btn btn-outline-danger file-slot-remove', '×');
        remove.type = 'button';
        remove.title = 'Remove this file';
        remove.setAttribute('aria-label', 'Remove the selected file');
        [name, size, preview, remove].forEach(function (part) { panel.appendChild(part); });
        input.insertAdjacentElement('afterend', panel);

        var url = null;
        function release() {
            if (url && root.URL && root.URL.revokeObjectURL) root.URL.revokeObjectURL(url);
            url = null;
        }

        function show() {
            release();
            var file = input.files && input.files[0];
            if (!file) {
                panel.classList.add('d-none');
                return;
            }
            var fileKind = kind(file);
            name.textContent = file.name;
            name.title = file.name;
            size.textContent = formatSize(file.size);
            preview.classList.toggle('d-none', fileKind === 'other');
            panel.classList.remove('d-none');
        }

        preview.addEventListener('click', function () {
            var file = input.files && input.files[0];
            if (!file) return;
            release();
            url = root.URL.createObjectURL(file);
            root.open(url, '_blank', 'noopener');
        });

        remove.addEventListener('click', function () {
            input.value = '';
            show();
            // Whatever else watches the box (the duplicate check) hears that it is empty now
            input.dispatchEvent(new root.Event('change', { bubbles: true }));
            input.focus();
        });

        input.addEventListener('change', show);
        root.addEventListener('pagehide', release);
        show();

        return panel;
    }

    function attachAll(scope, doc) {
        doc = doc || root.document;
        var forms = scope && scope.matches && scope.matches('[data-file-slots]') ? [scope] : Array.prototype.slice.call((scope || doc).querySelectorAll('[data-file-slots]'));
        var panels = [];
        forms.forEach(function (form) {
            Array.prototype.forEach.call(form.querySelectorAll('input[type="file"]'), function (input) {
                var panel = attach(input, doc);
                if (panel) panels.push(panel);
            });
        });
        return panels;
    }

    root.FileSlot = { formatSize: formatSize, kind: kind, attach: attach, attachAll: attachAll };

    if (root.document) {
        if (root.document.readyState === 'loading') {
            root.document.addEventListener('DOMContentLoaded', function () { attachAll(root.document); });
        } else {
            attachAll(root.document);
        }
    }
})(typeof window !== 'undefined' ? window : this);
