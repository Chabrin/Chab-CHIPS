@extends('layouts.main')

@section('styles')
@include('communications._styles')
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid cm-page">
        @include('communications._header', ['active' => 'logs', 'title' => 'Message log', 'subtitle' => 'Every individual email and SMS the system has sent, including payslips and other system mail once they are connected.'])

        <div class="cm-card">
            <div class="cm-toolbar">
                <div class="cm-field">
                    <label class="cm-label">Channel</label>
                    <div class="cm-seg" id="channel_seg">
                        <button type="button" class="active" data-ch="email"><span class="material-symbols-outlined">mail</span>Email</button>
                        <button type="button" data-ch="sms"><span class="material-symbols-outlined">sms</span>SMS</button>
                    </div>
                </div>
                <div class="cm-field">
                    <label class="cm-label">Status</label>
                    <select class="cm-select" id="status_filter">
                        <option value="">Any status</option>
                        <option value="sent">Delivered</option>
                        <option value="failed">Failed</option>
                        <option value="skipped">Skipped</option>
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
                <div class="cm-field" style="min-width:210px;flex:1;">
                    <label class="cm-label">Recipient</label>
                    <input type="text" class="cm-input" id="search_term" placeholder="Name, email or phone">
                </div>
                <div class="cm-field" style="min-width:auto;">
                    <button type="button" class="cm-btn cm-btn-primary" id="search_btn" style="height:38px;"><span class="material-symbols-outlined" id="search_icon">search</span>Search</button>
                </div>
            </div>

            <div class="cm-hint mb-2" id="result_info" style="display:none;"><b id="result_count">0</b> records<span id="truncated_note" style="display:none;"> — showing the latest 5,000, narrow the dates to see older ones</span></div>

            <div class="cm-table-wrap" id="table_wrap">
                <table class="cm-table" id="logs-table">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Recipient</th>
                            <th>Message</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Batch</th>
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
        var channel = 'email';
        var batchUrl = '{{ url("/communications/batches") }}/';

        function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }

        var table = $('#logs-table').DataTable({
            data: [],
            columns: [
                {
                    data: 'id',
                    render: function(d, t, r) {
                        if (t !== 'display') return d;
                        var p = String(r.date || '').split(' ');
                        return '<div class="cm-date-main">' + esc(p.slice(0, 3).join(' ')) + '</div><div class="cm-date-sub">' + esc(p[3] || '') + '</div>';
                    }
                },
                {
                    data: 'recipient',
                    render: function(d, t, r) {
                        if (t !== 'display') return d + ' ' + (r.contact || '');
                        return '<div class="cm-cell-main">' + esc(d) + '</div><div class="cm-cell-sub">' + esc(r.contact || '—') + '</div>';
                    }
                },
                { data: 'subject', render: function(d, t) { return t === 'display' ? '<div class="cm-cell-main" style="font-weight:500;max-width:360px;">' + esc(d) + '</div>' : d; } },
                { data: 'type', render: function(d, t) { return t === 'display' ? '<span class="cm-type">' + esc(d) + '</span>' : d; } },
                {
                    data: 'status',
                    render: function(d, t, r) {
                        if (t !== 'display') return d;
                        var label = { sent: 'Delivered', failed: 'Failed', skipped: 'Skipped' }[d] || d;
                        return '<span class="cm-pill ' + d + '" title="' + esc(r.error || '') + '">' + label + '</span>' + (d === 'failed' && r.error ? '<div class="cm-err mt-1">' + esc(r.error) + '</div>' : '');
                    }
                },
                {
                    data: 'batch_id',
                    render: function(d, t) {
                        if (t !== 'display') return d || 0;
                        return d ? '<a href="' + batchUrl + d + '" style="font-weight:600;">#' + d + '</a>' : '<span class="cm-hint">system</span>';
                    }
                }
            ],
            order: [[0, 'desc']],
            pageLength: 25,
            language: {
                search: '', searchPlaceholder: 'Filter these results…',
                emptyTable: '<div class="cm-empty"><span class="material-symbols-outlined">inbox</span><h4>No messages match</h4><p>Change the filters above and search again.</p></div>',
                zeroRecords: '<div class="cm-empty"><span class="material-symbols-outlined">search_off</span><h4>No matches</h4></div>'
            }
        });

        function loadData() {
            $('#search_icon').text('progress_activity').addClass('cm-spin');
            $('#search_btn').prop('disabled', true);
            $('#table_wrap').addClass('cm-loading');

            $.ajax({
                url: '{{ route("ajax.communications.logs") }}',
                type: 'GET',
                data: { channel: channel, status: $('#status_filter').val(), date_from: $('#date_from').val(), date_to: $('#date_to').val(), search: $('#search_term').val() },
                success: function(res) {
                    if (!res.success) return;
                    $('#result_info').show();
                    $('#result_count').text(Number(res.count).toLocaleString());
                    $('#truncated_note').toggle(!!res.truncated);
                    table.clear().rows.add(res.data).draw();
                },
                error: function(xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to load logs.';
                    if (window.toastr) toastr.error(msg); else alert(msg);
                },
                complete: function() {
                    $('#search_icon').text('search').removeClass('cm-spin');
                    $('#search_btn').prop('disabled', false);
                    $('#table_wrap').removeClass('cm-loading');
                }
            });
        }

        $('#channel_seg button').on('click', function() {
            channel = $(this).data('ch');
            $('#channel_seg button').removeClass('active');
            $(this).addClass('active');
            loadData();
        });
        $('#status_filter, #date_from, #date_to').on('change', loadData);
        $('#search_btn').on('click', loadData);
        $('#search_term').on('keypress', function(e) { if (e.which === 13) loadData(); });
        loadData();
    });
</script>
@endsection
