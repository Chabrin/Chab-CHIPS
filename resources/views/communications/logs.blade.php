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
                    <input type="date" class="cm-input" id="date_from" value="{{ now()->subMonthNoOverflow()->format('Y-m-d') }}">
                </div>
                <div class="cm-field">
                    <label class="cm-label">To</label>
                    <input type="date" class="cm-input" id="date_to" value="{{ now()->format('Y-m-d') }}">
                </div>
                <div class="cm-field" style="min-width:210px;flex:1;">
                    <label class="cm-label">Recipient</label>
                    <input type="text" class="cm-input" id="search_term" placeholder="Name, email or phone">
                </div>
                <div class="cm-field" style="min-width:auto;">
                    <button type="button" class="cm-btn cm-btn-primary" id="search_btn" style="height:38px;"><span class="material-symbols-outlined" id="search_icon">search</span>Search</button>
                </div>
            </div>

            <div class="cm-hint mb-2" id="result_info" style="display:none;"><b id="result_count">0</b> records<span id="truncated_note" style="display:none;"> — more than that match, narrow the dates or search to see fewer</span></div>

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
                            <th></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@include('communications._message_modal')
@endsection

@section('scripts')
@include('communications._message_modal_js')
<script>
    $(document).ready(function() {
        var channel = 'email';
        var batchUrl = '{{ url("/communications/batches") }}/';
        var logMessageUrl = '{{ url("/ajax/communications/logs") }}/';

        function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }

        var lastJson = null;

        var table = $('#logs-table').DataTable({
            // one page at a time from the server; the Recipient box above does the searching
            serverSide: true,
            processing: true,
            searching: false,
            ajax: {
                url: '{{ route("ajax.communications.logs") }}',
                type: 'GET',
                data: function(d) {
                    d.channel = channel;
                    d.status = $('#status_filter').val();
                    d.date_from = $('#date_from').val();
                    d.date_to = $('#date_to').val();
                    d.q = $('#search_term').val();
                },
                dataSrc: function(json) {
                    lastJson = json;
                    $('#result_info').show();
                    $('#result_count').text(Number(json.count).toLocaleString() + (json.capped ? '+' : ''));
                    $('#truncated_note').toggle(!!json.capped);
                    return json.data;
                },
                error: function(xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to load logs.';
                    if (window.toastr) toastr.error(msg); else alert(msg);
                }
            },
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
                },
                { data: null, orderable: false, searchable: false, render: function() { return '<button type="button" class="cm-read cm-btn cm-btn-light" title="Read the message" style="padding:4px 8px;"><span class="material-symbols-outlined" style="font-size:18px !important;">visibility</span></button>'; } }
            ],
            columnDefs: [{ orderable: true, targets: 0 }, { orderable: false, targets: '_all' }],
            order: [[0, 'desc']],
            pageLength: 50,
            lengthMenu: [25, 50, 100],
            createdRow: function(row, data) {
                $(row).addClass('cm-click').on('click', function(e) {
                    if ($(e.target).closest('a').length) return; // the batch link keeps its own job
                    cmOpenMessage(logMessageUrl + channel + '/' + data.id + '/message');
                });
            },
            infoCallback: function(settings, start, end, max, total) {
                if (!total) return '';
                return 'Showing ' + Number(start).toLocaleString() + ' to ' + Number(end).toLocaleString() + ' of ' + Number(total).toLocaleString() + (lastJson && lastJson.capped ? '+' : '') + ' entries';
            },
            language: {
                emptyTable: '<div class="cm-empty"><span class="material-symbols-outlined">inbox</span><h4>No messages match</h4><p>Change the filters above and search again.</p></div>',
                zeroRecords: '<div class="cm-empty"><span class="material-symbols-outlined">search_off</span><h4>No matches</h4></div>'
            }
        });

        table.on('processing.dt', function(e, settings, on) {
            $('#search_icon').text(on ? 'progress_activity' : 'search').toggleClass('cm-spin', on);
            $('#search_btn').prop('disabled', on);
            $('#table_wrap').toggleClass('cm-loading', on);
        });

        // back to page 1 with the current filters
        function loadData() {
            table.ajax.reload();
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
    });
</script>
@endsection
