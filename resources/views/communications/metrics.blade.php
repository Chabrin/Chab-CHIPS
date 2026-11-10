@extends('layouts.main')

@section('styles')
@include('communications._styles')
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid cm-page">
        @include('communications._header', ['active' => 'metrics', 'title' => 'Communication metrics', 'subtitle' => 'Volumes, delivery rates and SMS cost drivers across every message.'])

        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <div class="cm-section-label mb-0">Overview</div>
            <div class="cm-filters" id="range_chips">
                <button type="button" class="cm-fchip" data-days="7">7 days</button>
                <button type="button" class="cm-fchip active" data-days="30">30 days</button>
                <button type="button" class="cm-fchip" data-days="90">90 days</button>
                <button type="button" class="cm-fchip" data-days="365">12 months</button>
            </div>
        </div>

        <div class="cm-stats">
            <div class="cm-stat cm-tone-blue"><div class="cm-stat-ico"><span class="material-symbols-outlined">mail</span></div><div><div class="cm-stat-v" id="m_email_sent">—</div><div class="cm-stat-l">Emails delivered</div><div class="cm-stat-s" id="m_email_sub"></div></div></div>
            <div class="cm-stat cm-tone-green"><div class="cm-stat-ico"><span class="material-symbols-outlined">task_alt</span></div><div><div class="cm-stat-v" id="m_email_rate">—</div><div class="cm-stat-l">Email delivery rate</div><div class="cm-stat-s" id="m_email_skipped"></div></div></div>
            <div class="cm-stat cm-tone-blue" style="--tone:#8b5cf6;--tone-bg:#ede9fe;--tone-ink:#6d28d9;"><div class="cm-stat-ico"><span class="material-symbols-outlined">sms</span></div><div><div class="cm-stat-v" id="m_sms_sent">—</div><div class="cm-stat-l">SMS delivered</div><div class="cm-stat-s" id="m_sms_sub"></div></div></div>
            <div class="cm-stat cm-tone-amber"><div class="cm-stat-ico"><span class="material-symbols-outlined">payments</span></div><div><div class="cm-stat-v" id="m_sms_units">—</div><div class="cm-stat-l">SMS units used</div><div class="cm-stat-s" id="m_sms_skipped"></div></div></div>
        </div>

        <div class="row">
            <div class="col-xl-8">
                <div class="cm-card">
                    <h3 class="cm-card-title"><span class="material-symbols-outlined" style="color:var(--cm-blue);">bar_chart</span>Messages per day</h3>
                    <div class="cm-legend">
                        <span><i style="background:#5b9bd5"></i>Email</span>
                        <span><i style="background:#8b5cf6"></i>SMS</span>
                        <span><i style="background:#ef4444"></i>Failed</span>
                    </div>
                    <div id="chart_wrap">
                        <div class="cm-bars" id="bars"></div>
                        <div class="cm-axis"><span id="ax_from"></span><span id="ax_to"></span></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="cm-card">
                    <h3 class="cm-card-title"><span class="material-symbols-outlined" style="color:var(--cm-blue);">donut_large</span>Overall delivery</h3>
                    <div class="cm-donut" id="donut"><div><b id="donut_pct">—</b><small>delivered</small></div></div>
                    <div class="cm-hint text-center mt-2" id="donut_sub"></div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-6">
                <div class="cm-card">
                    <h3 class="cm-card-title"><span class="material-symbols-outlined" style="color:var(--cm-red);">report</span>Top failure reasons</h3>
                    <div id="errors_list"></div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="cm-card">
                    <h3 class="cm-card-title"><span class="material-symbols-outlined" style="color:var(--cm-blue);">category</span>Emails by type</h3>
                    <div id="types_list"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        var days = 30;

        function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }
        function num(n) { return Number(n || 0).toLocaleString(); }
        function ymd(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
        function pretty(s) { var d = new Date(s + 'T00:00:00'); return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }); }

        function hbars(rows, labelKey, red) {
            if (!rows.length) return '';
            var max = Math.max.apply(null, rows.map(function(r) { return Number(r.n); })) || 1;
            return rows.map(function(r) {
                return '<div class="cm-hbar"><div class="cm-hbar-l' + (red ? ' wide' : '') + '" title="' + esc(r[labelKey]) + '">' + esc(r[labelKey] || '(no reason recorded)') + '</div><div class="cm-hbar-t' + (red ? ' red' : '') + '"><i style="width:' + (Number(r.n) / max * 100) + '%"></i></div><div class="cm-hbar-n">' + num(r.n) + '</div></div>';
            }).join('');
        }

        function loadData() {
            $('#chart_wrap').addClass('cm-loading');
            $.ajax({
                url: '{{ route("ajax.communications.metrics") }}', type: 'GET', data: { days: days },
                success: function(res) {
                    if (!res.success) return;
                    var e = res.totals.email || {}, s = res.totals.sms || {};
                    var eAttempt = (+e.sent || 0) + (+e.failed || 0), sAttempt = (+s.sent || 0) + (+s.failed || 0);

                    $('#m_email_sent').text(num(e.sent));
                    $('#m_email_sub').text(e.failed > 0 ? num(e.failed) + ' failed' : '').css('color', '#b91c1c');
                    $('#m_email_rate').text(eAttempt ? ((+e.sent || 0) * 100 / eAttempt).toFixed(1) + '%' : '—');
                    $('#m_email_skipped').text(e.skipped > 0 ? num(e.skipped) + ' skipped, no email on record' : '').css('color', '#b45309');
                    $('#m_sms_sent').text(num(s.sent));
                    $('#m_sms_sub').text(s.failed > 0 ? num(s.failed) + ' failed' : '').css('color', '#b91c1c');
                    $('#m_sms_units').text(num(s.units));
                    $('#m_sms_skipped').text(s.skipped > 0 ? num(s.skipped) + ' skipped, no mobile on record' : '').css('color', '#b45309');

                    var sent = (+e.sent || 0) + (+s.sent || 0), attempts = eAttempt + sAttempt;
                    var pct = attempts ? sent * 100 / attempts : 0;
                    $('#donut').css('--p', attempts ? pct : 0).css('background', attempts ? '' : '#e2e8f0');
                    $('#donut_pct').text(attempts ? pct.toFixed(0) + '%' : '—');
                    $('#donut_sub').text(attempts ? num(sent) + ' of ' + num(attempts) + ' attempts delivered' : 'Nothing sent in this period.');

                    // per-day bars, one slot for every day so gaps show
                    var map = {};
                    (res.per_day.email || []).forEach(function(r) { map[r.day] = map[r.day] || { e: 0, s: 0, f: 0 }; map[r.day].e = +r.total; map[r.day].f += +r.failed; });
                    (res.per_day.sms || []).forEach(function(r) { map[r.day] = map[r.day] || { e: 0, s: 0, f: 0 }; map[r.day].s = +r.total; map[r.day].f += +r.failed; });
                    var list = [], start = new Date(); start.setDate(start.getDate() - (days - 1));
                    var buckets = days > 90 ? 30 : days;
                    var step = Math.ceil(days / buckets);
                    for (var i = 0; i < days; i += step) {
                        var agg = { e: 0, s: 0, f: 0 }, first = null, last = null;
                        for (var j = 0; j < step && i + j < days; j++) {
                            var d = new Date(start); d.setDate(start.getDate() + i + j);
                            var k = ymd(d); first = first || k; last = k;
                            var m = map[k]; if (m) { agg.e += m.e; agg.s += m.s; agg.f += m.f; }
                        }
                        list.push({ from: first, to: last, e: agg.e, s: agg.s, f: agg.f });
                    }
                    var max = Math.max.apply(null, list.map(function(x) { return x.e + x.s; }).concat([1]));
                    $('#bars').html(list.map(function(x) {
                        var h = function(v) { return (v / max * 100) + '%'; };
                        var label = x.from === x.to ? pretty(x.from) : pretty(x.from) + ' – ' + pretty(x.to);
                        return '<div class="cm-bar"><div class="cm-bar-tip">' + label + '<br>Email ' + num(x.e) + ' · SMS ' + num(x.s) + (x.f ? '<br>Failed ' + num(x.f) : '') + '</div>' +
                            '<span class="e" style="height:' + h(x.e) + '"></span><span class="m" style="height:' + h(x.s) + '"></span>' + (x.f ? '<span class="x" style="height:4px;margin-bottom:1px;"></span>' : '') + '</div>';
                    }).join(''));
                    $('#ax_from').text(pretty(list[0].from));
                    $('#ax_to').text(pretty(list[list.length - 1].to));

                    var errs = [];
                    ['email', 'sms'].forEach(function(ch) { (res.top_errors[ch] || []).forEach(function(r) { errs.push({ error: (ch === 'sms' ? 'SMS: ' : 'Email: ') + (r.error || ''), n: r.n }); }); });
                    errs.sort(function(a, b) { return b.n - a.n; });
                    $('#errors_list').html(hbars(errs.slice(0, 6), 'error', true) || '<div class="cm-empty" style="padding:24px;"><span class="material-symbols-outlined" style="color:#86efac;">verified</span><h4>No failures</h4><p>Everything attempted in this period was delivered.</p></div>');
                    $('#types_list').html(hbars(res.email_by_type || [], 'type', false) || '<div class="cm-empty" style="padding:24px;"><span class="material-symbols-outlined">mail</span><h4>No emails yet</h4></div>');
                },
                error: function(xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to load metrics.';
                    if (window.toastr) toastr.error(msg); else alert(msg);
                },
                complete: function() { $('#chart_wrap').removeClass('cm-loading'); }
            });
        }

        $('#range_chips .cm-fchip').on('click', function() {
            $('#range_chips .cm-fchip').removeClass('active');
            $(this).addClass('active');
            days = +$(this).data('days');
            loadData();
        });
        loadData();
    });
</script>
@endsection
