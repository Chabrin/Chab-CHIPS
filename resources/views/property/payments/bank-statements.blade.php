@extends('layouts.main')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/bank-statements.css') }}?v={{ filemtime(public_path('assets/css/bank-statements.css')) }}">
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid bs-page">

        <div class="bs-hero">
            <div class="bs-hero-top">
                <div>
                    <h1>Bank Statements</h1>
                    <p>Upload a bank's statement. Someone else in a confirming role confirms it, then every line is matched to a tenant and paid in the background.</p>
                </div>
                <div class="bs-hero-actions">
                    <button type="button" class="bs-btn bs-btn-ghost" data-bs-toggle="modal" data-bs-target="#confirmers-modal">
                        <span class="material-symbols-outlined">how_to_reg</span>Who confirms: <b id="confirmers-chip">...</b>
                    </button>
                </div>
            </div>

            <div class="bs-flow">
                <b><span class="bs-n">1</span>Capture</b><span class="material-symbols-outlined">chevron_right</span>
                <b><span class="bs-n">2</span>Someone else confirms</b><span class="material-symbols-outlined">chevron_right</span>
                <b><span class="bs-n">3</span>Match by unit code</b><span class="material-symbols-outlined">chevron_right</span>
                <b><span class="bs-n">4</span>Pay arrears, bills, then rent</b><span class="material-symbols-outlined">chevron_right</span>
                <b><span class="bs-n">5</span>Receipt emailed</b>
            </div>

            <div class="bs-tabs" role="tablist">
                @foreach($banks as $key => $bank)
                <button type="button" class="bs-tab {{ $loop->first ? 'active' : '' }}" data-bank="{{ $key }}" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                    <span class="material-symbols-outlined">account_balance</span>{{ $bank['label'] }}<span class="bs-tab-badge d-none" data-badge="{{ $key }}"></span>
                </button>
                @endforeach
            </div>
        </div>

        @foreach($banks as $key => $bank)
        <section class="bs-pane {{ $loop->first ? '' : 'd-none' }}" data-bank="{{ $key }}">

            <div class="bs-card">
                <div class="bs-upload">
                    <label class="bs-drop">
                        <input type="file" accept=".xlsx,.xls,.csv,.txt">
                        <span class="material-symbols-outlined bs-drop-icon">upload_file</span>
                        <span>
                            <b class="bs-drop-title">Upload a statement file</b>
                            <span class="bs-drop-sub">Drop the {{ $bank['label'] }} file here or click to choose. xlsx, xls or csv, up to 5 MB.</span>
                        </span>
                    </label>
                    <div>
                        <div class="bs-cols-label">The file needs these columns</div>
                        <div class="bs-cols">
                            @foreach($bank['columns'] as $column)<span>{{ $column }}</span>@endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="bs-card">
                <div class="bs-card-head">
                    <h5 class="bs-card-title"><span class="material-symbols-outlined">description</span>{{ $bank['label'] }} statements</h5>
                    <span class="bs-hint">Click a statement to see its lines underneath.</span>
                </div>
                <div class="bs-table-wrap">
                    <table class="bs-table">
                        <thead>
                            <tr>
                                <th style="width:32px;"></th>
                                <th>Statement</th>
                                <th class="text-end">Lines / amount</th>
                                <th>Result</th>
                                <th>Status</th>
                                <th>Captured by</th>
                                <th>Confirmed by</th>
                            </tr>
                        </thead>
                        <tbody class="bs-uploads">
                            <tr><td colspan="7" class="text-center text-muted">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bs-card bs-lines d-none">
                <div class="bs-card-head">
                    <div>
                        <h5 class="bs-lines-title"></h5>
                        <div class="bs-lines-sub"></div>
                    </div>
                    <button type="button" class="bs-btn bs-btn-light bs-btn-sm bs-lines-close"><span class="material-symbols-outlined">close</span>Close</button>
                </div>
                <div class="bs-filters"></div>
                <div class="bs-scroll">
                    <table class="bs-table">
                        <thead>
                            <tr>
                                <th>Row</th>
                                <th>Date</th>
                                <th>Reference</th>
                                <th class="text-end">Amount</th>
                                <th>Details in the file</th>
                                <th>Unit code</th>
                                <th>Tenant</th>
                                <th>Receipt</th>
                                <th>Result</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody class="bs-lines-body"></tbody>
                    </table>
                </div>
                <div class="bs-pager">
                    <span class="bs-lines-page"></span>
                    <div>
                        <button type="button" class="bs-btn bs-btn-light bs-btn-sm bs-prev">Previous</button>
                        <button type="button" class="bs-btn bs-btn-light bs-btn-sm bs-next">Next</button>
                    </div>
                </div>
            </div>

        </section>
        @endforeach

    </div>
</div>

<div class="modal fade bs-modal" id="confirmers-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Who confirms bank statements</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Currently: <strong id="confirmers-current">...</strong></p>
                <div id="confirmers-form" class="d-none">
                    <div class="bs-cols-label">Roles that confirm</div>
                    <div id="confirmers-roles" class="bs-roles"></div>
                    <div class="bs-hint mt-2">Saving replaces the current confirmers. Anyone with this page can upload; only these roles can confirm.</div>
                </div>
                <div id="confirmers-note" class="bs-hint d-none">Only a user with the update bank statements permission can change who confirms.</div>
                <div id="confirmers-message" class="small mt-2 d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="bs-btn bs-btn-light" data-bs-dismiss="modal">Close</button>
                <button type="button" class="bs-btn bs-btn-primary d-none" id="confirmers-save">Save</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function() {
    var csrf = '{{ csrf_token() }}';
    var urls = {
        data: '{{ route('ajax.tenant.bank-statements.data') }}',
        upload: '{{ route('ajax.tenant.bank-statements.upload') }}',
        confirmers: '{{ route('ajax.tenant.bank-statements.confirmers') }}',
        base: '{{ url('/ajax/tenant/bank-statements') }}',
        matchBase: '{{ url('/tenant/bank-statements') }}'
    };
    var banks = @json(array_keys($banks));
    var MAX_BYTES = 5 * 1024 * 1024;
    var busyStatuses = ['Confirmed', 'Matching', 'Matched', 'Paying'];
    var uploadText = { Uploaded: 'Awaiting confirmation', Confirmed: 'Queued', Matching: 'Matching', Matched: 'Matched', Paying: 'Paying', Completed: 'Completed', Failed: 'Failed' };
    var lineOrder = ['Paid', 'Matched', 'Unmatched', 'Ambiguous', 'Duplicate', 'Invalid', 'Skipped', 'Pending'];
    var lineText = { Paid: 'Paid', Matched: 'Matched, unpaid', Unmatched: 'Unmatched', Ambiguous: 'Ambiguous', Duplicate: 'Duplicate', Invalid: 'Invalid', Skipped: 'Skipped', Pending: 'Pending' };
    var receiptText = { Queued: 'Email queued', Sent: 'Emailed', Failed: 'Email failed', NoEmail: 'No email address' };
    var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    var pollTimer = null;
    var openParam = parseInt(new URLSearchParams(window.location.search).get('open'), 10) || null;

    // One state per bank: the statement whose lines are open, the lines page and filter, and the last rows drawn
    var state = {};
    banks.forEach(function(bank) {
        state[bank] = { id: null, page: 1, pages: 1, status: '', rows: {}, sig: null, selSig: null, req: 0, openAfterLoad: null };
    });

    function esc(value) {
        return $('<div>').text(value === null || value === undefined ? '' : value).html();
    }

    function errorText(xhr, fallback) {
        return (xhr.responseJSON && xhr.responseJSON.message) || fallback;
    }

    function pane(bank) {
        return $('.bs-pane[data-bank="' + bank + '"]');
    }

    function bankOf(element) {
        return $(element).closest('.bs-pane').attr('data-bank');
    }

    // 2026-11-13 12:45:00 -> 13 Nov 2026, 12:45. With shortYear the year is left out when it is this year.
    function when(value, withTime, shortYear) {
        var m = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}:\d{2}))?/.exec(value || '');
        if (!m) { return value || ''; }
        var year = shortYear && m[1] === String(new Date().getFullYear()) ? '' : ' ' + m[1];
        return parseInt(m[3], 10) + ' ' + months[parseInt(m[2], 10) - 1] + year + (withTime && m[4] ? ', ' + m[4] : '');
    }

    function total(row) {
        var sum = 0;
        lineOrder.forEach(function(name) { sum += row.lines[name] || 0; });
        return sum;
    }

    // How far a statement's run has got, from its line counts: matching counts the lines no longer Pending (Invalid and Skipped
    // were set when the file was read), paying counts the matched lines already Paid. Null when nothing is running.
    function progressOf(row) {
        var c = row.lines || {};
        var n = function(name) { return c[name] || 0; };

        if (row.status === 'Confirmed') { return { text: 'Waiting for the queue to start', pct: 0 }; }
        if (row.status === 'Matching') {
            var judged = n('Matched') + n('Unmatched') + n('Ambiguous') + n('Duplicate') + n('Paid');
            var all = judged + n('Pending');
            return { text: 'Matching ' + judged + ' of ' + all + ' lines', pct: all ? judged * 100 / all : 0 };
        }
        if (row.status === 'Matched') { return { text: 'Matching done, starting the payments', pct: 100 }; }
        if (row.status === 'Paying') {
            var toPay = n('Paid') + n('Matched');
            return { text: 'Paying ' + n('Paid') + ' of ' + toPay + ' matched lines', pct: toPay ? n('Paid') * 100 / toPay : 0 };
        }

        return null;
    }

    // ── Bank tabs ─────────────────────────────────────────────
    function showBank(bank) {
        if (banks.indexOf(bank) === -1) { bank = banks[0]; }
        $('.bs-tab').removeClass('active').attr('aria-selected', 'false').filter('[data-bank="' + bank + '"]').addClass('active').attr('aria-selected', 'true');
        $('.bs-pane').addClass('d-none').filter('[data-bank="' + bank + '"]').removeClass('d-none');
        if (window.history && history.replaceState) { history.replaceState(null, '', '#' + bank); }
    }
    $(document).on('click', '.bs-tab', function() { showBank($(this).attr('data-bank')); });

    // ── The statements of each bank ───────────────────────────
    function resultCell(row) {
        var sum = total(row);
        if (!sum) { return '<span class="bs-hint">No lines</span>'; }

        var bar = '', text = '';
        lineOrder.forEach(function(name) {
            var n = row.lines[name] || 0;
            if (!n) { return; }
            bar += '<span class="bs-c-' + name + '" style="width:' + (n * 100 / sum).toFixed(2) + '%" title="' + esc(lineText[name]) + ': ' + n + '"></span>';
            text += '<span><span class="bs-dot bs-c-' + name + '"></span>' + esc(lineText[name]) + ' ' + n + '</span>';
        });

        return '<div class="bs-result"><div class="bs-bar">' + bar + '</div><div class="bs-txt">' + text + '</div>' +
            (row.paid_count ? '<div class="bs-txt"><span>Paid in: ' + esc(row.paid_amount) + '</span></div>' : '') + '</div>';
    }

    function uploadRow(row, selected) {
        var progress = progressOf(row);
        var actions = '';
        if (row.can_confirm) { actions += '<button type="button" class="bs-btn bs-btn-ok bs-btn-sm btn-confirm" data-id="' + row.id + '"><span class="material-symbols-outlined">check_circle</span>Confirm</button>'; }
        if (row.needs_other_confirmer) { actions += '<span class="bs-wait" title="You captured this statement, so someone else has to confirm it."><span class="material-symbols-outlined">lock</span>Another person confirms</span>'; }
        if (row.can_retry) { actions += '<button type="button" class="bs-btn bs-btn-warn bs-btn-sm btn-retry" data-id="' + row.id + '"><span class="material-symbols-outlined">replay</span>Run again</button>'; }
        actions += '<a class="bs-btn bs-btn-sm" href="' + urls.matchBase + '/' + row.id + '/download" title="Download the file as it was uploaded" aria-label="Download the statement file"><span class="material-symbols-outlined">download</span>Download</a>';
        if (row.can_delete) { actions += '<button type="button" class="bs-btn bs-btn-danger bs-btn-sm btn-delete" data-id="' + row.id + '" title="Delete this statement" aria-label="Delete this statement"><span class="material-symbols-outlined">delete</span></button>'; }

        return '<tr class="bs-click' + (selected ? ' bs-selected' : '') + '" data-id="' + row.id + '" tabindex="0">' +
            '<td><span class="material-symbols-outlined bs-chev">chevron_right</span></td>' +
            '<td><div class="bs-cell-main" title="' + esc(row.file_name) + '">' + esc(row.file_name) + '</div>' +
                '<div class="bs-cell-sub">#' + row.id + '</div></td>' +
            '<td class="text-end bs-num"><div class="bs-cell-main">' + row.line_count + '</div><div class="bs-cell-sub">' + esc(row.total_amount) + '</div></td>' +
            '<td>' + resultCell(row) + '</td>' +
            '<td><span class="bs-pill ' + esc(row.status) + '">' + esc(uploadText[row.status] || row.status) + '</span>' + (row.error ? '<div class="bs-err">' + esc(row.error) + '</div>' : '') +
                (progress ? '<div class="bs-prog" title="' + esc(progress.text) + '"><div class="bs-prog-fill" style="width:' + progress.pct.toFixed(1) + '%"></div></div><div class="bs-txt"><span>' + esc(progress.text) + ' (' + Math.floor(progress.pct) + '%)</span></div>' : '') +
                (actions ? '<div class="bs-actions">' + actions + '</div>' : '') + '</td>' +
            '<td><div class="bs-cell-main">' + esc(row.uploader || 'unknown') + '</div><div class="bs-cell-sub">' + esc(when(row.uploaded_at, true, true)) + '</div></td>' +
            '<td>' + (row.confirmed_at ? '<div class="bs-cell-main">' + esc(row.confirmer || 'unknown') + '</div><div class="bs-cell-sub">' + esc(when(row.confirmed_at, true, true)) + '</div>' : '<span class="bs-hint">Not yet</span>') + '</td>' +
            '</tr>';
    }

    function drawUploads(bank, rows) {
        var body = pane(bank).find('.bs-uploads').empty();

        if (!rows.length) {
            body.html('<tr><td colspan="7"><div class="bs-empty"><span class="material-symbols-outlined">inbox</span><h4>No statements yet</h4>Upload the first statement in the box above.</div></td></tr>');
            return;
        }
        rows.forEach(function(row) { body.append(uploadRow(row, row.id === state[bank].id)); });
    }

    function renderUploads(bank, rows) {
        var s = state[bank];
        var sig = JSON.stringify(rows);
        var changed = sig !== s.sig;

        s.sig = sig;
        s.rows = {};
        rows.forEach(function(row) { s.rows[row.id] = row; });
        if (changed) { drawUploads(bank, rows); }

        // Back from matching a line by hand: the statement that was being worked on opens again
        if (openParam && s.rows[openParam]) {
            var backId = openParam;
            openParam = null;
            showBank(bank);
            openLines(bank, backId);
            if (window.history && history.replaceState) { history.replaceState(null, '', window.location.pathname + '#' + bank); }
            return;
        }

        // A statement just uploaded opens its lines at once, so they can be checked before it is confirmed
        if (s.openAfterLoad && s.rows[s.openAfterLoad]) {
            var openId = s.openAfterLoad;
            s.openAfterLoad = null;
            openLines(bank, openId);
            return;
        }

        if (s.id) {
            var row = s.rows[s.id];
            if (!row) { closeLines(bank); return; }

            // The open statement changed (matching or paying moved on): refresh its chips and lines without blanking them
            var selSig = JSON.stringify(row);
            if (selSig !== s.selSig) {
                s.selSig = selSig;
                renderLinesHead(bank);
                loadLines(bank, true);
            }
        }
    }

    // The table of statements. While any statement is being matched or paid the page asks again every three seconds.
    function loadUploads() {
        $.getJSON(urls.data, function(res) {
            var busy = false;

            banks.forEach(function(bank) {
                var rows = res.data.filter(function(row) { return row.bank_key === bank; });
                renderUploads(bank, rows);

                if (rows.some(function(row) { return busyStatuses.indexOf(row.status) !== -1; })) { busy = true; }

                var waiting = rows.filter(function(row) { return row.status === 'Uploaded'; }).length;
                $('[data-badge="' + bank + '"]').toggleClass('d-none', !waiting).text(waiting + ' to confirm');
            });

            clearTimeout(pollTimer);
            if (busy) { pollTimer = setTimeout(loadUploads, 3000); }
        }).fail(function(xhr) {
            $('.bs-uploads').html('<tr><td colspan="7" class="text-center text-danger">' + esc(errorText(xhr, 'Could not load the statements.')) + '</td></tr>');
        });
    }

    // ── The lines of the clicked statement, shown under its table ──
    function renderLinesHead(bank) {
        var s = state[bank];
        var row = s.rows[s.id];
        var panel = pane(bank).find('.bs-lines');
        if (!row) { return; }

        // A filter whose lines are all gone (a retry paid them) goes back to all lines
        if (s.status && !row.lines[s.status]) { s.status = ''; s.page = 1; }

        panel.find('.bs-lines-title').text(row.file_name);
        panel.find('.bs-lines-sub').text('#' + row.id + ' · ' + row.bank + ' · captured by ' + (row.uploader || 'unknown') + ', ' + when(row.uploaded_at, true) +
            (row.confirmed_at ? ' · confirmed by ' + (row.confirmer || 'unknown') + ', ' + when(row.confirmed_at, true) : ''));

        var html = chip('', 'All lines', total(row), s.status === '');
        lineOrder.forEach(function(name) {
            if (row.lines[name]) { html += chip(name, lineText[name], row.lines[name], s.status === name); }
        });
        panel.find('.bs-filters').html(html);
    }

    function chip(value, label, count, active) {
        return '<button type="button" class="bs-fchip' + (active ? ' active' : '') + '" data-status="' + esc(value) + '">' + esc(label) + '<em>' + count + '</em></button>';
    }

    function lineRow(bank, line) {
        var action = '';
        if (line.can_match) {
            action = '<a class="bs-btn bs-btn-primary bs-btn-sm" href="' + esc(urls.matchBase + '/' + bank + '/lines/' + line.id + '/match') + '"><span class="material-symbols-outlined">link</span>Match</a>';
        } else if (line.match_hint) {
            action = '<span class="bs-wait" title="' + esc(line.match_hint) + '"><span class="material-symbols-outlined">lock</span></span>';
        }
        var byHand = line.matched_by ? '<div class="bs-note">Matched by hand: ' + esc(line.matched_by) + (line.matched_at ? ', ' + esc(when(line.matched_at, true, true)) : '') + '</div>' : '';
        var receipt = esc(line.receipt_number) + (line.receipt_status ? '<div class="bs-note">' + esc(receiptText[line.receipt_status] || line.receipt_status) + '</div>' : '');

        return '<tr>' +
            '<td class="bs-num">' + line.row_no + '</td>' +
            '<td class="bs-num">' + esc(when(line.date, false)) + '</td>' +
            '<td class="bs-num">' + esc(line.reference) + '</td>' +
            '<td class="text-end bs-num">' + esc(line.amount) + '</td>' +
            '<td><div class="bs-details">' + esc(line.description) + '</div></td>' +
            '<td class="bs-num">' + esc(line.unit_code) + '</td>' +
            '<td>' + esc(line.tenant) + '</td>' +
            '<td class="bs-num">' + receipt + '</td>' +
            '<td><span class="bs-pill ' + esc(line.status) + '">' + esc(lineText[line.status] || line.status) + '</span>' + (line.note ? '<div class="bs-note">' + esc(line.note) + '</div>' : '') + byHand + '</td>' +
            '<td class="text-end">' + action + '</td>' +
            '</tr>';
    }

    // quiet = a refresh while the statement is being worked on: keep the lines on screen until the new ones arrive
    function loadLines(bank, quiet) {
        var s = state[bank];
        var id = s.id;
        var panel = pane(bank).find('.bs-lines');
        var mine = ++s.req;

        if (!quiet) { panel.find('.bs-lines-body').html('<tr><td colspan="10" class="text-center text-muted">Loading...</td></tr>'); }

        $.getJSON(urls.base + '/' + id + '/lines', { status: s.status, page: s.page }, function(res) {
            // Ignore an answer to a question that is no longer the one on screen
            if (mine !== s.req || s.id !== id) { return; }

            var body = panel.find('.bs-lines-body').empty();
            s.pages = Math.max(res.pages, 1);

            if (!res.data.length) { body.append('<tr><td colspan="10" class="text-center text-muted">No lines.</td></tr>'); }
            res.data.forEach(function(line) { body.append(lineRow(bank, line)); });

            panel.find('.bs-lines-page').text(res.total + ' line(s) · page ' + s.page + ' of ' + s.pages);
            panel.find('.bs-prev').prop('disabled', s.page <= 1);
            panel.find('.bs-next').prop('disabled', s.page >= s.pages);
        }).fail(function(xhr) {
            if (mine !== s.req) { return; }
            panel.find('.bs-lines-body').html('<tr><td colspan="10" class="text-center text-danger">' + esc(errorText(xhr, 'Could not load the lines.')) + '</td></tr>');
        });
    }

    function openLines(bank, id) {
        var s = state[bank];
        var panel = pane(bank).find('.bs-lines');

        s.id = id;
        s.page = 1;
        s.status = '';
        s.selSig = JSON.stringify(s.rows[id]);

        pane(bank).find('.bs-uploads tr').removeClass('bs-selected').filter('[data-id="' + id + '"]').addClass('bs-selected');
        renderLinesHead(bank);
        panel.removeClass('d-none');
        loadLines(bank, false);

        if (panel[0].scrollIntoView) { panel[0].scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    }

    function closeLines(bank) {
        var s = state[bank];

        s.id = null;
        s.selSig = null;
        s.req++;
        pane(bank).find('.bs-uploads tr').removeClass('bs-selected');
        pane(bank).find('.bs-lines').addClass('d-none');
    }

    // Click a statement to open its lines; click it again to close them
    $(document).on('click', '.bs-uploads tr.bs-click', function(e) {
        if ($(e.target).closest('button, a').length) { return; }

        var bank = bankOf(this);
        var id = parseInt($(this).attr('data-id'), 10);
        if (state[bank].id === id) { closeLines(bank); } else { openLines(bank, id); }
    });
    $(document).on('keydown', '.bs-uploads tr.bs-click', function(e) {
        if ((e.key === 'Enter' || e.key === ' ') && e.target === this) { e.preventDefault(); $(this).trigger('click'); }
    });
    $(document).on('click', '.bs-lines-close', function() { closeLines(bankOf(this)); });

    $(document).on('click', '.bs-fchip', function() {
        var bank = bankOf(this);
        state[bank].status = $(this).attr('data-status');
        state[bank].page = 1;
        renderLinesHead(bank);
        loadLines(bank, false);
    });
    $(document).on('click', '.bs-prev', function() {
        var s = state[bankOf(this)];
        if (s.page > 1) { s.page--; loadLines(bankOf(this), false); }
    });
    $(document).on('click', '.bs-next', function() {
        var s = state[bankOf(this)];
        if (s.page < s.pages) { s.page++; loadLines(bankOf(this), false); }
    });

    // ── Upload: the bank is the tab the box is in ─────────────
    function uploadFile(bank, file) {
        var zone = pane(bank).find('.bs-drop');
        var title = zone.find('.bs-drop-title');
        var icon = zone.find('.bs-drop-icon');
        var original = title.text();

        if (file.size > MAX_BYTES) { toastr.error('The file is larger than 5 MB.'); return; }
        if (!/\.(xlsx|xls|csv|txt)$/i.test(file.name)) { toastr.error('Upload the statement as an xlsx, xls or csv file.'); return; }

        zone.addClass('busy');
        title.text('Uploading ' + file.name + '...');
        icon.text('progress_activity').addClass('bs-spin');

        var form = new FormData();
        form.append('bank', bank);
        form.append('statement', file);
        form.append('_token', csrf);

        $.ajax({ url: urls.upload, method: 'POST', data: form, processData: false, contentType: false, dataType: 'json' })
            .done(function(res) {
                toastr.success(res.message);
                state[bank].openAfterLoad = res.id;
                loadUploads();
            })
            .fail(function(xhr) { toastr.error(errorText(xhr, 'Failed to upload the statement.')); })
            .always(function() {
                zone.removeClass('busy');
                title.text(original);
                icon.text('upload_file').removeClass('bs-spin');
            });
    }

    $(document).on('change', '.bs-drop input', function() {
        var file = this.files && this.files[0];
        var bank = bankOf(this);
        this.value = '';
        if (file) { uploadFile(bank, file); }
    });
    $(document).on('dragover dragenter', '.bs-drop', function(e) { e.preventDefault(); $(this).addClass('drag'); });
    $(document).on('dragleave dragend', '.bs-drop', function() { $(this).removeClass('drag'); });
    $(document).on('drop', '.bs-drop', function(e) {
        e.preventDefault();
        $(this).removeClass('drag');
        var files = e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files;
        if (files && files.length) { uploadFile(bankOf(this), files[0]); }
    });

    // ── Confirm, run again, delete ────────────────────────────
    function post(url, method, done) {
        $.ajax({ url: url, method: method, data: { _token: csrf }, dataType: 'json' })
            .done(function(res) { toastr.success(res.message); done(); })
            .fail(function(xhr) { toastr.error(errorText(xhr, 'The action failed.')); });
    }

    $(document).on('click', '.btn-confirm', function() {
        var id = $(this).data('id');
        var row = state[bankOf(this)].rows[id] || {};
        Swal.fire({
            title: 'Confirm this statement?',
            text: (row.file_name || 'This statement') + ' has ' + (row.line_count || 0) + ' lines. Matching and payment start in the background.',
            icon: 'question', showCancelButton: true, confirmButtonText: 'Confirm'
        }).then(function(result) { if (result.isConfirmed) { post(urls.base + '/' + id + '/confirm', 'POST', loadUploads); } });
    });
    $(document).on('click', '.btn-retry', function() {
        post(urls.base + '/' + $(this).data('id') + '/retry', 'POST', loadUploads);
    });
    $(document).on('click', '.btn-delete', function() {
        var id = $(this).data('id');
        Swal.fire({ title: 'Delete this statement?', text: 'Its lines and the file are removed.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete' })
            .then(function(result) { if (result.isConfirmed) { post(urls.base + '/' + id, 'DELETE', loadUploads); } });
    });

    // ── "Who confirms": show the confirming roles, and let a user with update_bank_statements change them ──
    $.getJSON(urls.confirmers, function(setting) { $('#confirmers-chip').text(setting.label); })
        .fail(function() { $('#confirmers-chip').text('unknown'); });

    $('#confirmers-modal').on('show.bs.modal', function() {
        $('#confirmers-message').addClass('d-none');
        $.getJSON(urls.confirmers, function(setting) {
            $('#confirmers-current').text(setting.label);
            $('#confirmers-chip').text(setting.label);
            if (setting.can_change) {
                var list = $('#confirmers-roles').empty();
                setting.roles.forEach(function(role) {
                    var checked = setting.role_ids.indexOf(role.id) !== -1 ? ' checked' : '';
                    list.append('<label class="bs-role"><input class="confirmer-role" type="checkbox" value="' + role.id + '"' + checked + '>' + esc(role.name) + '</label>');
                });
                $('#confirmers-form, #confirmers-save').removeClass('d-none');
                $('#confirmers-note').addClass('d-none');
            } else {
                $('#confirmers-form, #confirmers-save').addClass('d-none');
                $('#confirmers-note').removeClass('d-none');
            }
        }).fail(function() { $('#confirmers-current').text('could not be loaded'); });
    });
    $('#confirmers-save').on('click', function() {
        var ids = $('.confirmer-role:checked').map(function() { return $(this).val(); }).get();
        var message = $('#confirmers-message');
        if (!ids.length) { message.text('Choose at least one role.').removeClass('d-none text-success').addClass('text-danger'); return; }
        var button = $(this).prop('disabled', true);

        $.ajax({ url: urls.confirmers, method: 'POST', data: { role_ids: ids, _token: csrf }, dataType: 'json' })
            .done(function(res) {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmers-modal')).hide();
                toastr.success(res.message);
                $.getJSON(urls.confirmers, function(setting) { $('#confirmers-chip').text(setting.label); });
                loadUploads();
            })
            .fail(function(xhr) { message.text(errorText(xhr, 'Failed to save.')).removeClass('d-none text-success').addClass('text-danger'); })
            .always(function() { button.prop('disabled', false); });
    });

    showBank((window.location.hash || '').replace('#', ''));
    loadUploads();
});
</script>
@endsection
