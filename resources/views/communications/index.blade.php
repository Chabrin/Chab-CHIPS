@extends('layouts.main')

@section('styles')
@include('communications._styles')
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid cm-page">
        @include('communications._header', ['active' => 'batches', 'title' => 'Sent messages', 'subtitle' => 'Every email and SMS blast, who it went to and how it was delivered.'])

        <div class="cm-stats" id="stats_row">
            <div class="cm-stat cm-tone-blue"><div class="cm-stat-ico"><span class="material-symbols-outlined">outbox</span></div><div><div class="cm-stat-v" id="st_batches">—</div><div class="cm-stat-l">Messages sent out</div></div></div>
            <div class="cm-stat cm-tone-green"><div class="cm-stat-ico"><span class="material-symbols-outlined">mark_email_read</span></div><div><div class="cm-stat-v" id="st_sent">—</div><div class="cm-stat-l">Delivered to recipients</div></div></div>
            <div class="cm-stat cm-tone-red"><div class="cm-stat-ico"><span class="material-symbols-outlined">error</span></div><div><div class="cm-stat-v" id="st_failed">—</div><div class="cm-stat-l">Failed</div></div></div>
            <div class="cm-stat cm-tone-amber"><div class="cm-stat-ico"><span class="material-symbols-outlined">person_off</span></div><div><div class="cm-stat-v" id="st_skipped">—</div><div class="cm-stat-l">Skipped, no contact on record</div></div></div>
        </div>

        <div class="cm-card">
            <div class="cm-toolbar">
                <div class="cm-field">
                    <label class="cm-label">Channel</label>
                    <select class="cm-select" id="channel_filter">
                        <option value="">All channels</option>
                        <option value="email">Email</option>
                        <option value="sms">SMS</option>
                    </select>
                </div>
                <div class="cm-field">
                    <label class="cm-label">From</label>
                    <input type="date" class="cm-input" id="date_from">
                </div>
                <div class="cm-field">
                    <label class="cm-label">To</label>
                    <input type="date" class="cm-input" id="date_to">
                </div>
                <div class="cm-field" style="min-width:auto;">
                    <button type="button" class="cm-btn cm-btn-primary" id="search_btn" style="height:38px;"><span class="material-symbols-outlined" id="search_icon">search</span>Search</button>
                </div>
            </div>

            <div class="cm-table-wrap" id="table_wrap">
                <table class="cm-table" id="batches-table">
                    <thead>
                        <tr>
                            <th>Sent</th>
                            <th>Channel</th>
                            <th>Message</th>
                            <th>Delivery</th>
                            <th>Status</th>
                            <th>By</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        var showUrl = '{{ url("/communications/batches") }}/';
        var composeUrl = '{{ route("communications.compose") }}';

        function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }
        function num(n) { return Number(n || 0).toLocaleString(); }

        function chan(c) {
            var email = String(c).toLowerCase() === 'email';
            return '<span class="cm-chan ' + (email ? 'email' : 'sms') + '"><span class="material-symbols-outlined">' + (email ? 'mail' : 'sms') + '</span>' + (email ? 'Email' : 'SMS') + '</span>';
        }

        function delivery(r) {
            var total = Math.max(1, r.recipients);
            var s = r.sent / total * 100, f = r.failed / total * 100, k = r.skipped / total * 100;
            var parts = [num(r.sent) + ' of ' + num(r.recipients) + ' delivered'];
            if (r.failed) parts.push(num(r.failed) + ' failed');
            if (r.skipped) parts.push(num(r.skipped) + ' skipped');
            return '<div class="cm-deliv"><div class="cm-deliv-bar"><i class="s" style="width:' + s + '%"></i><i class="f" style="width:' + f + '%"></i><i class="k" style="width:' + k + '%"></i></div><div class="cm-deliv-txt">' + parts.join(' · ') + '</div></div>';
        }

        var labels = { done: 'Delivered', failed: 'Failed', sending: 'Sending', queued: 'Queued' };

        var table = $('#batches-table').DataTable({
            data: [],
            columns: [
                {
                    data: 'sort_key',
                    render: function(d, t, r) {
                        if (t !== 'display') return d;
                        var parts = String(r.date || '').split(' ');
                        return '<div class="cm-date-main">' + esc(parts.slice(0, 3).join(' ')) + '</div><div class="cm-date-sub">' + esc(parts[3] || '') + '</div>';
                    }
                },
                { data: 'channel', render: function(d, t) { return t === 'display' ? chan(d) : d; } },
                {
                    data: 'subject',
                    render: function(d, t, r) {
                        if (t !== 'display') return d + ' ' + r.audience;
                        return '<div class="cm-cell-main" title="' + esc(d) + '">' + esc(d) + '</div><div class="cm-cell-sub"><span class="material-symbols-outlined" style="font-size:13px !important;">group</span> ' + esc(r.audience) + '</div>';
                    }
                },
                { data: 'sent', orderable: false, render: function(d, t, r) { return t === 'display' ? delivery(r) : d; } },
                { data: 'status', render: function(d, t) { return t === 'display' ? '<span class="cm-pill ' + d + '">' + (labels[d] || d) + '</span>' : d; } },
                { data: 'sender', render: function(d, t) { return t === 'display' ? '<span style="font-size:12px;">' + esc(d) + '</span>' : d; } },
                { data: null, orderable: false, searchable: false, render: function() { return '<span class="material-symbols-outlined" style="color:#9ec3e3;">chevron_right</span>'; } }
            ],
            order: [[0, 'desc']],
            pageLength: 25,
            createdRow: function(row, data) { $(row).addClass('cm-click').on('click', function() { window.location = showUrl + data.id; }); },
            language: {
                search: '', searchPlaceholder: 'Filter these results…',
                emptyTable: '<div class="cm-empty"><span class="material-symbols-outlined">forward_to_inbox</span><h4>Nothing sent yet</h4><p>Messages you send will be listed here with their delivery results.</p><a href="' + composeUrl + '" class="cm-btn cm-btn-primary">Write the first one</a></div>',
                zeroRecords: '<div class="cm-empty"><span class="material-symbols-outlined">search_off</span><h4>No matches</h4><p>Try a different date range or channel.</p></div>'
            }
        });

        function loadData() {
            $('#search_icon').text('progress_activity').addClass('cm-spin');
            $('#search_btn').prop('disabled', true);
            $('#table_wrap').addClass('cm-loading');

            $.ajax({
                url: '{{ route("ajax.communications.batches") }}',
                type: 'GET',
                data: { channel: $('#channel_filter').val(), date_from: $('#date_from').val(), date_to: $('#date_to').val() },
                success: function(res) {
                    if (!res.success) return;
                    var rows = res.data.map(function(r) { r.sort_key = r.id; return r; });
                    table.clear().rows.add(rows).draw();

                    var sent = 0, failed = 0, skipped = 0;
                    rows.forEach(function(r) { sent += r.sent; failed += r.failed; skipped += r.skipped; });
                    $('#st_batches').text(num(rows.length));
                    $('#st_sent').text(num(sent));
                    $('#st_failed').text(num(failed));
                    $('#st_skipped').text(num(skipped));
                },
                error: function(xhr) {
                    var msg = 'Failed to load data.';
                    if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    if (window.toastr) toastr.error(msg); else alert(msg);
                },
                complete: function() {
                    $('#search_icon').text('search').removeClass('cm-spin');
                    $('#search_btn').prop('disabled', false);
                    $('#table_wrap').removeClass('cm-loading');
                }
            });
        }

        $('#search_btn').on('click', loadData);
        $('#channel_filter, #date_from, #date_to').on('change', loadData);
        loadData();
    });
</script>
@endsection
