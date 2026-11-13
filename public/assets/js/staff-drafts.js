// Add Staff: Save as Draft, the list of drafts, and a warning before leaving with typing that was never saved. A draft is kept on
// the server (not in this browser), so it can be reopened from any computer; the form's files are sent with it. Passwords are
// never sent. The server is App\Http\Controllers\StaffDraftsController.
(function (root) {
    'use strict';

    // Quotes are escaped too, because titles are also written into attributes
    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
        });
    }

    // The fields that are never put in a draft
    function isSecret(name) {
        return /^password(_confirmation)?$/.test(String(name));
    }

    function filesText(count) {
        return count > 0 ? ' · ' + count + (count === 1 ? ' file' : ' files') : '';
    }

    // The rows of the Drafts window. currentId is the draft open now, if any.
    function listHtml(drafts, currentId) {
        if (!drafts || !drafts.length) {
            return '<p class="text-muted mb-0">You have no saved drafts. Use <strong>Save as Draft</strong> on any tab to keep what you have filled in and come back to it later.</p>';
        }
        return '<div class="list-group">' + drafts.map(function (draft) {
            var title = escapeHtml(draft.title);
            var open = currentId && String(currentId) === String(draft.id) ? ' <span class="badge bg-info">open now</span>' : '';
            return '<div class="list-group-item d-flex align-items-center flex-wrap gap-2" data-draft-id="' + escapeHtml(draft.id) + '">'
                + '<div class="me-auto"><div class="fw-semibold">' + title + open + '</div>'
                + '<div class="small text-muted">' + (draft.staff_number ? 'Staff No. ' + escapeHtml(draft.staff_number) + ' · ' : '') + 'Last saved ' + escapeHtml(draft.updated_label) + filesText(draft.files) + '</div></div>'
                + '<a class="btn btn-sm btn-primary" href="' + escapeHtml(draft.resume_url) + '">Resume</a>'
                + '<button type="button" class="btn btn-sm btn-outline-danger js-discard-draft" data-draft-id="' + escapeHtml(draft.id) + '" aria-label="Discard the draft for ' + title + '">Discard</button>'
                + '</div>';
        }).join('') + '</div>';
    }

    // The form as it goes to the server: everything, files included, except the passwords
    function draftFormData(form) {
        var data = new root.FormData(form);
        Array.from(data.keys()).forEach(function (name) {
            if (isSecret(name)) data.delete(name);
        });
        return data;
    }

    // The first message from a failed save: its own message, or the first field error
    function failureMessage(body) {
        if (body && body.errors) {
            var first = Object.keys(body.errors)[0];
            if (first) return [].concat(body.errors[first])[0];
        }
        return (body && body.message) || 'The draft could not be saved.';
    }

    function init(doc, win) {
        win = win || root;
        var form = doc.getElementById('staffCreateForm');
        if (!form || !form.dataset.draftSaveUrl) return null;

        var idInput = doc.getElementById('draft_id');
        var buttons = Array.prototype.slice.call(form.querySelectorAll('.js-save-draft'));
        var notes = Array.prototype.slice.call(form.querySelectorAll('.js-draft-note'));
        var list = doc.getElementById('draftsList');
        var modal = doc.getElementById('draftsModal');
        var count = doc.getElementById('draftsCount');
        var csrf = form.querySelector('input[name="_token"]');
        var dirty = false;
        var saving = false;

        function setNote(text, bad) {
            notes.forEach(function (note) {
                note.textContent = text;
                note.classList.toggle('text-danger', !!bad);
                note.classList.toggle('text-success', !bad && !!text);
            });
        }

        function busy(flag) {
            saving = flag;
            buttons.forEach(function (button) { button.disabled = flag; });
        }

        function say(kind, text) {
            if (win.toastr && win.toastr[kind]) win.toastr[kind](text);
        }

        function activeTab() {
            var pane = form.querySelector('.tab-pane.active');
            return pane ? pane.id : '';
        }

        // After the first save the address names the draft, so reloading the page reopens it instead of losing it
        function nameTheAddress(id) {
            try {
                var url = new win.URL(win.location.href);
                url.searchParams.set('draft', id);
                win.history.replaceState(null, '', url.toString());
            } catch (error) { /* the address stays as it was */ }
        }

        // The staff number the draft holds. Saving can give the draft one, and discarding another draft can move it, so what is
        // shown follows what the server says.
        function showStaffNumber(number) {
            if (!number) return;
            var input = doc.getElementById('staff_number');
            if (input) input.value = number;
            var note = doc.getElementById('staffNumberNote');
            if (note) note.classList.remove('d-none');
        }

        // The state of each stage (tab) as the form sees it now, to be kept with the draft
        function stagesNow() {
            var steps = win.StaffFormSteps && win.StaffFormSteps.instance;
            return steps && steps.statuses ? JSON.stringify(steps.statuses()) : '';
        }

        // Answers true when the draft was saved. options.lastTab names the tab to reopen on (Save & Next names the one it is moving to).
        async function save(retried, options) {
            if (saving) return false;
            busy(true);
            setNote('Saving draft...');
            var data = draftFormData(form);
            data.set('last_tab', (options && options.lastTab) || activeTab());
            var stages = stagesNow();
            if (stages) data.set('stages', stages);
            var saved = false;
            try {
                var response = await win.fetch(form.dataset.draftSaveUrl, {
                    method: 'POST', body: data, credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                var body = {};
                try { body = await response.json(); } catch (error) { body = {}; }

                if (response.status === 404 && idInput && idInput.value && !retried) {
                    // The draft was discarded somewhere else: keep this one as a new draft
                    idInput.value = '';
                    busy(false);
                    return save(true, options);
                }
                if (response.ok && body.success) {
                    saved = true;
                    if (idInput) idInput.value = body.draft_id;
                    showStaffNumber(body.staff_number);
                    dirty = false;
                    setNote('Draft saved at ' + body.saved_at_label);
                    say('success', body.message);
                    nameTheAddress(body.draft_id);
                    loadList();
                } else {
                    var message = response.status === 419 || response.status === 401
                        ? 'Your session may have expired. Sign in in another tab, then save the draft again. Your form is still here.'
                        : failureMessage(body);
                    setNote(message, true);
                    say('error', message);
                }
            } catch (error) {
                setNote('The draft could not be saved because the connection failed. Your form is still here.', true);
            } finally {
                busy(false);
            }
            return saved;
        }

        function loadList() {
            if (!form.dataset.draftsUrl) return Promise.resolve();
            return win.fetch(form.dataset.draftsUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (response) { return response.json(); })
                .then(function (body) {
                    var drafts = body.drafts || [];
                    // Another draft being discarded can close a gap and move this one's number
                    var current = idInput && idInput.value ? drafts.filter(function (draft) { return String(draft.id) === String(idInput.value); })[0] : null;
                    if (current) showStaffNumber(current.staff_number);
                    if (list) list.innerHTML = listHtml(drafts, idInput && idInput.value);
                    if (count) {
                        count.textContent = drafts.length;
                        count.classList.toggle('d-none', !drafts.length);
                    }
                })
                .catch(function () {
                    if (list) list.innerHTML = '<p class="text-danger mb-0">The drafts could not be loaded.</p>';
                });
        }

        function discard(id) {
            if (!win.confirm('Discard this draft? What was saved in it, including its files, is deleted.')) return Promise.resolve(false);
            return win.fetch(form.dataset.draftsUrl.replace(/\/?$/, '/') + id, {
                method: 'DELETE', credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf ? csrf.value : '' }
            }).then(function (response) {
                if (!response.ok) throw new Error('not discarded');
                say('success', 'The draft was discarded.');
                return true;
            }).catch(function () {
                say('error', 'The draft could not be discarded.');
                return false;
            });
        }

        buttons.forEach(function (button) { button.addEventListener('click', function () { save(false); }); });

        if (list) {
            list.addEventListener('click', function (event) {
                var button = event.target.closest('.js-discard-draft');
                if (!button) return;
                var id = button.getAttribute('data-draft-id');
                discard(id).then(function (done) {
                    if (!done) return;
                    // The draft open on this page is gone, so the next save starts a new one
                    if (idInput && String(idInput.value) === String(id)) idInput.value = '';
                    loadList();
                });
            });
        }
        if (modal) modal.addEventListener('show.bs.modal', loadList);

        var banner = doc.getElementById('discardThisDraft');
        if (banner) {
            banner.addEventListener('click', function () {
                var id = idInput && idInput.value;
                if (!id) return;
                discard(id).then(function (done) {
                    if (!done) return;
                    dirty = false;
                    // Back to a blank form; a listener can take over by cancelling the event
                    var discarded = new win.CustomEvent('staff-draft:discarded', { cancelable: true });
                    if (form.dispatchEvent(discarded)) win.location.assign(win.location.pathname);
                });
            });
        }

        // Typing that was not saved is not lost quietly. Events the page makes itself (the suggested email) do not count.
        function markDirty(event) {
            if (event && event.isTrusted === false) return;
            dirty = true;
        }
        form.addEventListener('input', markDirty);
        form.addEventListener('change', markDirty);
        win.addEventListener('beforeunload', function (event) {
            if (dirty && !form.dataset.saved) {
                event.preventDefault();
                event.returnValue = '';
            }
        });

        // A draft reopens on the tab it was left on
        if (form.dataset.lastTab && win.bootstrap && win.bootstrap.Tab) {
            var tab = doc.getElementById(form.dataset.lastTab + '-tab');
            var item = tab && tab.closest('li');
            if (tab && !tab.classList.contains('active') && !(item && item.style.display === 'none')) {
                win.bootstrap.Tab.getOrCreateInstance(tab).show();
            }
        }

        loadList();

        // Save & Next: keep the form as a draft that reopens on the tab being moved to
        function saveStep(nextPaneId) { return save(false, { lastTab: nextPaneId }); }

        return { save: function () { return save(false); }, saveStep: saveStep, loadList: loadList, markDirty: function () { dirty = true; }, isDirty: function () { return dirty; } };
    }

    var api = { escapeHtml: escapeHtml, isSecret: isSecret, filesText: filesText, listHtml: listHtml, draftFormData: draftFormData, failureMessage: failureMessage, init: init };
    root.StaffDrafts = api;

    if (root.document) {
        var start = function () { root.StaffDrafts.instance = init(root.document, root); };
        if (root.document.readyState === 'loading') {
            root.document.addEventListener('DOMContentLoaded', start);
        } else {
            start();
        }
    }
})(typeof window !== 'undefined' ? window : this);
