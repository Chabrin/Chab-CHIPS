{{-- Generic approval queue table, selection, dialogs and actions. Reads the page's window.ApprovalQueue config. --}}
<script>
$(function () {
    const Q = window.ApprovalQueue;
    const H = window.QH;
    const csrf = $('meta[name="csrf-token"]').attr('content');
    const selected = new Set();
    let modalId = null;
    let modalBlock = null;
    let modalLabel = '';

    /* ---------- filters ---------- */
    function filters() {
        const f = { status: $('#f-status').val() };
        Object.keys(Q.filterFields).forEach(function (key) { f[key] = $(Q.filterFields[key]).val(); });
        return f;
    }

    /* ---------- table ---------- */
    const loading = $('<div class="sla-loading" role="status" aria-live="polite" hidden></div>');
    $('#sla-table').before(loading);
    function showLoading(on) {
        const label = $('#sla-tabs .sla-tab.active').clone().children().remove().end().text().trim();
        loading.text('Loading ' + (label ? label.toLowerCase() + ' ' : '') + Q.plural + '\u2026').prop('hidden', !on);
        $('#sla-table').attr('aria-busy', String(on)).toggleClass('sla-table-loading', on);
        $('#sla-table_wrapper').toggleClass('sla-queue-loading', on);
    }
    $('#sla-table').on('preXhr.dt', function () { showLoading(true); });
    const mid = Q.columns.length;
    const columns = [{ data: 'id', orderable: false, render: id => '<input type="checkbox" class="form-check-input row-check" value="' + id + '"' + (selected.has(id) ? ' checked' : '') + '>' }]
        .concat(Q.columns)
        .concat([
            { data: 'status', orderable: false, render: (v, t, r) => H.statusPill(r.status_id, v) },
            { data: 'id', orderable: false, className: 'text-end', render: (id, t, r) => {
                const open = '<button class="icon-btn act-view" data-id="' + id + '" title="Review">' + H.icon('arrow_outward') + '</button>';
                if (r.status_id !== 1) { return '<div class="row-actions">' + open + '</div>'; }
                const blocked = Q.blockReason ? Q.blockReason(r) : null;
                const approveBtn = blocked
                    ? '<button class="icon-btn blocked act-blocked" data-id="' + id + '" title="' + H.esc(blocked) + '">' + H.icon('lock') + '</button>'
                    : '<button class="icon-btn ok act-approve" data-id="' + id + '" title="Approve">' + H.icon('check') + '</button>';
                return '<div class="row-actions">' + approveBtn +
                    '<button class="icon-btn bad act-reject" data-id="' + id + '" title="Reject">' + H.icon('close') + '</button>' + open + '</div>';
            } },
        ]);

    const table = $('#sla-table').DataTable({
        processing: true,
        serverSide: true,
        searching: false,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [Q.defaultOrder],
        layout: { topStart: 'pageLength', topEnd: 'info', bottomStart: null, bottomEnd: 'paging' },
        language: {
            emptyTable: '<div style="font-size:15px;font-weight:700;color:#334155;">Nothing here</div><div>No ' + Q.plural + ' match these filters.</div>',
            processing: 'Loading…',
            lengthMenu: '_MENU_ per page'
        },
        ajax: {
            url: Q.urls.list,
            type: 'GET',
            data: function (d) {
                Object.assign(d, filters());
                const o = d.order && d.order[0];
                d.order_key = o ? (Q.orderKeys[o.column] || Q.orderKeys.default) : Q.orderKeys.default;
                d.order_dir = o ? o.dir : 'desc';
            },
            dataSrc: function (json) {
                if (json.counts) {
                    $('#count-pending').text(Number(json.counts.pending).toLocaleString());
                    $('#count-approved').text(Number(json.counts.approved).toLocaleString());
                    $('#count-rejected').text(Number(json.counts.rejected).toLocaleString());
                }
                return json.data;
            },
            error: function (xhr) {
                showLoading(false);
                toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Could not load ' + Q.plural + '.');
            }
        },
        columns: columns,
        columnDefs: [{ targets: [0, mid + 1, mid + 2], orderable: false }],
        drawCallback: function () { showLoading(false); syncSelectAll(); }
    });

    /* ---------- tabs + filters ---------- */
    let searchTimer;
    function syncTabs() {
        const s = String($('#f-status').val());
        $('#sla-tabs .sla-tab').each(function () { $(this).toggleClass('active', String($(this).data('status')) === s); });
    }
    function reload() { selected.clear(); updateBulk(); table.ajax.reload(null, true); }
    $('#sla-tabs').on('click', '.sla-tab', function () { $('#f-status').val($(this).data('status')); syncTabs(); reload(); });
    Object.keys(Q.filterFields).forEach(function (key) {
        const $el = $(Q.filterFields[key]);
        if (key === 'search') { $el.on('input', function () { clearTimeout(searchTimer); searchTimer = setTimeout(reload, 350); }); }
        else { $el.on('change', reload); }
    });
    $('#f-reset').on('click', function () {
        Object.keys(Q.filterFields).forEach(function (key) { $(Q.filterFields[key]).val(''); });
        $('#f-status').val('1'); syncTabs(); reload();
    });
    syncTabs();

    /* ---------- selection ---------- */
    function updateBulk() {
        $('#sel-count').text(selected.size);
        $('#sla-bulk').toggleClass('show', selected.size > 0);
        syncSelectAll();
    }
    function syncSelectAll() {
        const boxes = $('#sla-table .row-check');
        $('#select-all').prop('checked', boxes.length > 0 && boxes.filter(':checked').length === boxes.length);
    }
    $('#select-all').on('change', function () {
        const on = this.checked;
        $('#sla-table .row-check').each(function () { const id = Number(this.value); this.checked = on; on ? selected.add(id) : selected.delete(id); });
        updateBulk();
    });
    $('#sla-table').on('click', '.row-check', function (e) {
        e.stopPropagation();
        const id = Number(this.value);
        this.checked ? selected.add(id) : selected.delete(id);
        updateBulk();
    });
    $('#bulk-clear').on('click', function () { selected.clear(); $('#sla-table .row-check').prop('checked', false); updateBulk(); });

    /* ---------- confirm / reason dialogs ---------- */
    function confirmAction(title, body, extras) {
        return new Promise(resolve => {
            $('#confirmActionModalTitle').text(title);
            $('#confirmActionModalBody').text(body);
            $('#confirmActionExtras').html(extras || '');
            const el = document.getElementById('confirmActionModal');
            const modal = bootstrap.Modal.getOrCreateInstance(el);
            let settled = false;
            $('#confirmActionModalConfirmBtn').off('click').on('click', function () {
                if (extras && Q.validateExtras && !Q.validateExtras()) { return; }
                settled = true; modal.hide(); resolve(true);
            });
            $(el).off('hidden.bs.modal').on('hidden.bs.modal', function () { if (!settled) resolve(false); });
            modal.show();
        });
    }
    function promptReason() {
        return new Promise(resolve => {
            $('#rejectReasonInput').val('').removeClass('is-invalid');
            const el = document.getElementById('rejectReasonModal');
            const modal = bootstrap.Modal.getOrCreateInstance(el);
            let settled = false;
            $('#rejectReasonConfirmBtn').off('click').on('click', function () {
                const reason = $('#rejectReasonInput').val().trim();
                if (!reason) { $('#rejectReasonInput').addClass('is-invalid'); return; }
                settled = true; modal.hide(); resolve(reason);
            });
            $(el).off('hidden.bs.modal').on('hidden.bs.modal', function () { if (!settled) resolve(null); });
            modal.show();
        });
    }
    function busy($btn, on) {
        $btn.prop('disabled', on);
        $btn.find('.spinner-border').remove();
        if (on) { $btn.append('<span class="spinner-border spinner-border-sm ms-2" aria-hidden="true"></span>'); }
    }

    /* ---------- notice dialog: why something was not approved ---------- */
    function showNotice(title, items, lead) {
        const groups = {};
        items.forEach(function (it) { (groups[it.reason] = groups[it.reason] || []).push(it.label); });
        $('#noticeModalTitle').text(title);
        $('#noticeModalLead').text(lead || '');
        $('#noticeModalList').html(Object.keys(groups).map(function (reason) {
            const labels = groups[reason];
            const shown = labels.slice(0, 6).map(H.esc).join(', ') + (labels.length > 6 ? ' and ' + (labels.length - 6) + ' more' : '');
            return '<div class="notice-group">' + H.esc(reason) + '<div class="who-list">' + labels.length + ' ' + (labels.length === 1 ? Q.noun : Q.plural) + ': ' + shown + '</div></div>';
        }).join(''));
        bootstrap.Modal.getOrCreateInstance(document.getElementById('noticeModal')).show();
    }
    Q.showNotice = showNotice;

    /* ---------- approve / reject ---------- */
    function send(url, payload, $btn, done) {
        busy($btn, true);
        $.post(url, Object.assign({ _token: csrf }, payload))
            .done(function (res) {
                const items = res.skipped_items || Object.keys(res.skipped || {}).map(id => ({ label: '#' + id, reason: res.skipped[id] }));
                if (res.success) { toastr.success(res.message || 'Done'); } else if (!items.length) { toastr.warning(res.error || res.message || 'Nothing changed'); }
                done(res);
                if (items.length) {
                    const lead = res.success ? 'The rest went through. These were left as they are:' : 'Nothing was changed:';
                    setTimeout(function () { showNotice(res.success ? 'Some were not approved' : 'Not approved', items, lead); }, 450);
                }
            })
            .fail(function (xhr) {
                const j = xhr.responseJSON || {};
                const firstErr = j.errors ? Object.values(j.errors)[0][0] : null;
                toastr.error(firstErr || j.error || 'Request failed');
            })
            .always(function () { busy($btn, false); });
    }
    async function approveIds(ids, $btn) {
        const many = ids.length > 1;
        // A page can add its own questions to the approve dialog when exactly one item is approved.
        const row = many ? null : table.rows().data().toArray().find(r => Number(r.id) === Number(ids[0]));
        const extras = row && Q.approveExtras ? Q.approveExtras(row) : null;
        const ok = await confirmAction(many ? 'Approve ' + ids.length + ' ' + Q.plural + '?' : 'Approve this ' + Q.noun + '?', Q.approveNote(many), extras);
        if (!ok) return;
        const payload = Object.assign({ ids: ids }, extras && Q.collectExtras ? Q.collectExtras() : {});
        send(Q.urls.approve, payload, $btn, function () { afterAction(ids); });
    }
    async function rejectIds(ids, $btn) {
        const reason = await promptReason();
        if (!reason) return;
        send(Q.urls.reject, { ids: ids, rejection_reason: reason }, $btn, function () { afterAction(ids); });
    }
    function afterAction(ids) {
        ids.forEach(id => selected.delete(id));
        updateBulk();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('slaModal')).hide();
        table.ajax.reload(null, false);
    }
    $('#sla-table').on('click', '.act-approve', function (e) { e.stopPropagation(); approveIds([Number($(this).data('id'))], $(this)); });
    $('#sla-table').on('click', '.act-blocked', function (e) {
        e.stopPropagation();
        const row = table.row($(this).closest('tr')).data();
        showNotice('Cannot approve yet', [{ label: row.reference, reason: row.block_reason }], 'This one has to wait:');
    });
    $('#sla-table').on('click', '.act-reject', function (e) { e.stopPropagation(); rejectIds([Number($(this).data('id'))], $(this)); });
    $('#sla-table').on('click', '.act-view', function (e) { e.stopPropagation(); openModal(Number($(this).data('id'))); });
    $('#sla-table tbody').on('click', 'tr', function (e) {
        if ($(e.target).closest('input, button, a').length) return;
        const row = table.row(this).data();
        if (row) openModal(row.id);
    });
    $('#bulk-approve').on('click', function () { approveIds(Array.from(selected), $(this)); });
    $('#bulk-reject').on('click', function () { rejectIds(Array.from(selected), $(this)); });
    $('#modal-approve').on('click', function () {
        if (!modalId) return;
        if (modalBlock) { showNotice('Cannot approve yet', [{ label: modalLabel, reason: modalBlock }], 'This one has to wait:'); return; }
        approveIds([modalId], $(this));
    });
    $('#modal-reject').on('click', function () { if (modalId) rejectIds([modalId], $(this)); });

    /* ---------- review modal ---------- */
    $('#mTabs').html(Q.tabs.map((t, i) => '<button type="button" class="m-tab' + (i === 0 ? ' active' : '') + '" data-pane="' + t.key + '">' + H.esc(t.label) + '</button>').join(''));
    $('#mTabs').on('click', '.m-tab', function () { showPane($(this).data('pane')); });
    function showPane(name) {
        $('#mTabs .m-tab').each(function () { $(this).toggleClass('active', $(this).data('pane') === name); });
        $('#slaModalBody .m-pane').each(function () { $(this).toggleClass('active', $(this).data('pane') === name); });
    }
    function renderModal(d) {
        const out = Q.renderModal(d);
        $('#slaHeroInner').html(out.hero);
        $('#slaModalBody').html(Q.tabs.map((t, i) => '<div class="m-pane' + (i === 0 ? ' active' : '') + '" data-pane="' + t.key + '">' + (out.panes[t.key] || '') + '</div>').join(''));
        showPane(Q.tabs[0].key);
        const pending = d.status_id === 1;
        const blocked = pending && d.can_approve === false;
        modalBlock = blocked ? d.block_reason : null;
        modalLabel = Q.itemLabel ? Q.itemLabel(d) : '';
        $('#modal-approve').toggleClass('d-none', !pending).toggleClass('is-blocked', blocked);
        $('#modal-reject').toggleClass('d-none', !pending);
        $('#foot-note').text(blocked ? d.block_reason : (pending ? Q.footNote : '')).css('color', blocked ? '#b7791f' : '');
    }
    function openModal(id) {
        modalId = id;
        modalBlock = null;
        $('#modal-approve').removeClass('is-blocked');
        $('#slaHeroInner').html('<div class="hero-row"><div class="avatar lg" style="background:#eef2f7;"></div><div><div class="hero-name">Loading…</div></div></div><div style="height:22px;"></div>');
        $('#slaModalBody').html('<div class="sla-empty">Loading…</div>');
        $('#modal-approve, #modal-reject').addClass('d-none');
        $('#foot-note').text('');
        showPane(Q.tabs[0].key);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('slaModal')).show();
        $.get(Q.urls.show + '/' + id)
            .done(function (res) { if (res.success) renderModal(res.data); else $('#slaModalBody').html('<div class="sla-empty">' + H.esc(res.error || 'Not found') + '</div>'); })
            .fail(function (xhr) { $('#slaModalBody').html('<div class="sla-empty">' + H.esc((xhr.responseJSON && xhr.responseJSON.error) || 'Could not load details.') + '</div>'); });
    }
});
</script>
