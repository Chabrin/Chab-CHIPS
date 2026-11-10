@extends('layouts.main')

@section('styles')
@include('communications._styles')
@endsection

@section('content')
@php
    $isSms = $batch->channel === 'sms';
    $heroTitle = ($isSms ? 'SMS' : 'Email') . ' to ' . $batch->audience_label;
    $batchState = ['status' => $batch->status, 'recipients' => $batch->recipient_count, 'sent' => $batch->sent_count, 'failed' => $batch->failed_count, 'skipped' => $batch->skipped_count];
    $heroSub = 'Batch #' . $batch->id . ' · created ' . optional($batch->created_at)->format('d M Y H:i') . ' by ' . optional($batch->sender)->name;
@endphp
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid cm-page">
        @include('communications._header', ['active' => 'batches', 'title' => $heroTitle, 'subtitle' => $heroSub])

        <div class="cm-stats">
            <div class="cm-stat cm-tone-blue"><div class="cm-stat-ico"><span class="material-symbols-outlined">groups</span></div><div><div class="cm-stat-v" id="st_recipients">{{ number_format($batch->recipient_count) }}</div><div class="cm-stat-l">In the audience</div></div></div>
            <div class="cm-stat cm-tone-green"><div class="cm-stat-ico"><span class="material-symbols-outlined">check_circle</span></div><div><div class="cm-stat-v" id="st_sent">{{ number_format($batch->sent_count) }}</div><div class="cm-stat-l">Delivered</div></div></div>
            <div class="cm-stat cm-tone-red"><div class="cm-stat-ico"><span class="material-symbols-outlined">error</span></div><div><div class="cm-stat-v" id="st_failed">{{ number_format($batch->failed_count) }}</div><div class="cm-stat-l">Failed</div></div></div>
            <div class="cm-stat cm-tone-amber"><div class="cm-stat-ico"><span class="material-symbols-outlined">person_off</span></div><div><div class="cm-stat-v" id="st_skipped">{{ number_format($batch->skipped_count) }}</div><div class="cm-stat-l">Skipped, no contact</div></div></div>
        </div>

        <div class="row">
            <div class="col-xl-8">
                <div class="cm-card">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                        <h3 class="cm-card-title mb-0">Delivery <span class="cm-pill {{ $batch->status }}" id="status_pill">{{ ['done' => 'Delivered', 'failed' => 'Failed', 'sending' => 'Sending', 'queued' => 'Queued'][$batch->status] ?? $batch->status }}</span></h3>
                        <button type="button" class="cm-btn cm-btn-warn" id="resend_btn" style="{{ $batch->failed_count > 0 ? '' : 'display:none;' }}">
                            <span class="material-symbols-outlined" id="resend_icon">replay</span>Resend <span id="resend_n">{{ $batch->failed_count }}</span> failed
                        </button>
                    </div>
                    <div class="cm-deliv cm-deliv-big">
                        <div class="cm-deliv-bar"><i class="s" id="bar_s"></i><i class="f" id="bar_f"></i><i class="k" id="bar_k"></i></div>
                        <div class="cm-deliv-txt" id="bar_txt"></div>
                    </div>
                </div>

                <div class="cm-card">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                        <h3 class="cm-card-title mb-0">Recipients</h3>
                        <div class="cm-filters" id="status_filters">
                            <button type="button" class="cm-fchip active" data-status="">All<em id="fc_all">{{ number_format($batch->recipient_count) }}</em></button>
                            <button type="button" class="cm-fchip" data-status="sent">Delivered<em id="fc_sent">{{ number_format($batch->sent_count) }}</em></button>
                            <button type="button" class="cm-fchip" data-status="failed">Failed<em id="fc_failed">{{ number_format($batch->failed_count) }}</em></button>
                            <button type="button" class="cm-fchip" data-status="skipped">Skipped<em id="fc_skipped">{{ number_format($batch->skipped_count) }}</em></button>
                        </div>
                    </div>
                    <div class="cm-table-wrap" id="table_wrap">
                        <table class="cm-table" id="logs-table">
                            <thead>
                                <tr>
                                    <th>Recipient</th>
                                    <th>{{ $isSms ? 'Phone' : 'Email' }}</th>
                                    @if($isSms)<th class="text-end">Units</th>@endif
                                    <th>Status</th>
                                    <th>Detail</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="cm-card">
                    <h3 class="cm-card-title"><span class="material-symbols-outlined" style="color:var(--cm-blue);">{{ $isSms ? 'sms' : 'mail' }}</span>The message</h3>
                    <dl class="cm-kv">
                        <dt>Channel</dt><dd><span class="cm-chan {{ $batch->channel }}"><span class="material-symbols-outlined">{{ $isSms ? 'sms' : 'mail' }}</span>{{ $isSms ? 'SMS' : 'Email' }}</span></dd>
                        <dt>Audience</dt><dd>{{ $batch->audience_label }}</dd>
                        @if($batch->attachment_name)<dt>Attachment</dt><dd>{{ $batch->attachment_name }}</dd>@endif
                    </dl>
                    <div class="cm-msg-card">
                        @unless($isSms)<div class="cm-msg-subject">{{ $batch->subject }}</div>@endunless
                        <div class="cm-msg-body">{{ $batch->body }}</div>
                    </div>
                    <div class="cm-hint mt-2">Placeholders like {name} are filled in separately for each recipient.</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        var isSms = @json($isSms);
        var statusUrl = '{{ route("ajax.communications.batch.status", $batch->id) }}';
        var logsUrl = '{{ route("ajax.communications.batch.logs", $batch->id) }}';
        var statusFilter = '';
        var current = @json($batchState);
        var labels = { done: 'Delivered', failed: 'Failed', sending: 'Sending', queued: 'Queued' };

        function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }
        function num(n) { return Number(n || 0).toLocaleString(); }
        function toast(type, msg) { if (window.toastr) toastr[type](msg); else alert(msg); }

        function paint(c) {
            current = c;
            var total = Math.max(1, c.recipients);
            $('#bar_s').css('width', (c.sent / total * 100) + '%');
            $('#bar_f').css('width', (c.failed / total * 100) + '%');
            $('#bar_k').css('width', (c.skipped / total * 100) + '%');
            var done = c.sent + c.failed + c.skipped;
            $('#bar_txt').text(num(done) + ' of ' + num(c.recipients) + ' processed · ' + num(c.sent) + ' delivered' + (c.failed ? ' · ' + num(c.failed) + ' failed' : '') + (c.skipped ? ' · ' + num(c.skipped) + ' skipped' : ''));
            $('#st_recipients').text(num(c.recipients)); $('#st_sent').text(num(c.sent)); $('#st_failed').text(num(c.failed)); $('#st_skipped').text(num(c.skipped));
            $('#fc_all').text(num(c.recipients)); $('#fc_sent').text(num(c.sent)); $('#fc_failed').text(num(c.failed)); $('#fc_skipped').text(num(c.skipped));
            $('#status_pill').attr('class', 'cm-pill ' + c.status).text(labels[c.status] || c.status);
            $('#resend_n').text(num(c.failed));
            $('#resend_btn').toggle(c.failed > 0 && (c.status === 'done' || c.status === 'failed'));
        }

        var columns = [
            { data: 'recipient', render: function(d, t) { return t === 'display' ? '<div class="cm-cell-main">' + esc(d) + '</div>' : d; } },
            { data: 'contact', render: function(d, t) { return t === 'display' ? '<span style="font-size:12px;">' + esc(d || '—') + '</span>' : d; } }
        ];
        if (isSms) columns.push({ data: 'units', className: 'text-end' });
        columns.push(
            { data: 'status', render: function(d, t) { return t === 'display' ? '<span class="cm-pill ' + d + '">' + ({ sent: 'Delivered', failed: 'Failed', skipped: 'Skipped' }[d] || d) + '</span>' : d; } },
            { data: 'error', render: function(d, t) { return t === 'display' ? (d ? '<div class="cm-err">' + esc(d) + '</div>' : '') : d; } },
            { data: 'date', render: function(d, t) { return t === 'display' ? '<span style="font-size:12px;color:#64748b;">' + esc(d) + '</span>' : d; } }
        );

        var table = $('#logs-table').DataTable({
            data: [], columns: columns, pageLength: 25, order: [],
            language: { search: '', searchPlaceholder: 'Find a recipient…', zeroRecords: '<div class="cm-empty"><span class="material-symbols-outlined">person_search</span><h4>No recipients here</h4></div>', emptyTable: '<div class="cm-empty"><span class="material-symbols-outlined">hourglass_empty</span><h4>No recipients logged yet</h4><p>They appear here as the batch is processed.</p></div>' }
        });

        function loadLogs() {
            $('#table_wrap').addClass('cm-loading');
            $.ajax({
                url: logsUrl, type: 'GET', data: { status: statusFilter },
                success: function(res) { if (res.success) { table.clear().rows.add(res.data).draw(false); } },
                error: function(xhr) { toast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to load recipients.'); },
                complete: function() { $('#table_wrap').removeClass('cm-loading'); }
            });
        }

        $('#status_filters .cm-fchip').on('click', function() {
            $('#status_filters .cm-fchip').removeClass('active');
            $(this).addClass('active');
            statusFilter = $(this).data('status');
            loadLogs();
        });

        paint(current);
        loadLogs();

        // While the batch is going out, keep the numbers and the recipient list live
        var poller = null;
        function poll() {
            $.get(statusUrl).done(function(res) {
                if (!res.success) return;
                paint({ status: res.status, recipients: res.recipients, sent: res.sent, failed: res.failed, skipped: res.skipped });
                loadLogs();
                if (res.status === 'done' || res.status === 'failed') { clearInterval(poller); poller = null; }
            });
        }
        function startPolling() { if (!poller) poller = setInterval(poll, 4000); }
        if (current.status === 'queued' || current.status === 'sending') startPolling();

        $('#resend_btn').on('click', function() {
            if (!confirm('Send again to every recipient that failed?')) return;
            var $b = $(this).prop('disabled', true);
            $('#resend_icon').text('progress_activity').addClass('cm-spin');

            $.post('{{ route("ajax.communications.batch.resend", $batch->id) }}', { _token: '{{ csrf_token() }}' })
                .done(function(res) { toast('success', res.message || 'Re-queued.'); paint($.extend({}, current, { status: 'queued', failed: 0 })); startPolling(); poll(); })
                .fail(function(xhr) { toast('error', (xhr.responseJSON && xhr.responseJSON.message) || 'Resend failed.'); })
                .always(function() { $b.prop('disabled', false); $('#resend_icon').text('replay').removeClass('cm-spin'); });
        });
    });
</script>
@endsection
