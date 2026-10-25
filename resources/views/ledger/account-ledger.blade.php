@extends('layouts.main')

@section('styles')
<style>
@include('ledger.partials.gl-styles')
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">

                <div class="gl-filter">
                    <div class="d-flex align-items-center mb-3 gap-2">
                        <a href="{{ route('gl.balances.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                            <span class="material-symbols-outlined" style="font-size:18px;">arrow_back</span><span class="ms-1">Account Balances</span>
                        </a>
                        <h6 class="mb-0">Account ledger &middot; {{ $account['code'] }} {{ $account['name'] }}</h6>
                    </div>
                    <div class="d-flex flex-wrap align-items-end gap-3">
                        <div>
                            <label class="d-block" for="period_preset">Period</label>
                            <select id="period_preset" class="form-select form-select-sm" style="width:170px;">
                                <option value="3m">Last 3 months</option>
                                <option value="6m">Last 6 months</option>
                                <option value="12m">Last 12 months</option>
                                <option value="custom">Custom range</option>
                            </select>
                        </div>
                        <div>
                            <label class="d-block" for="date_from">From</label>
                            <input type="date" id="date_from" class="form-control form-control-sm" style="width:160px;" value="{{ $from }}">
                        </div>
                        <div>
                            <label class="d-block" for="date_to">To</label>
                            <input type="date" id="date_to" class="form-control form-control-sm" style="width:160px;" value="{{ $to }}">
                        </div>
                        <div>
                            <button type="button" id="view_btn" class="btn btn-primary btn-sm">View</button>
                        </div>
                        <div>
                            <a id="export_btn" class="btn btn-success btn-sm d-flex align-items-center gap-1" href="#">
                                <span class="material-symbols-outlined" style="font-size:17px;">download</span><span>Excel</span>
                            </a>
                        </div>
                        <div class="ms-auto text-muted" style="font-size:.78rem;">A period can cover up to 12 months.</div>
                    </div>
                </div>

                <div class="gl-area">
                    <div class="gl-loading" id="gl_loading">
                        <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                    </div>
                    <div class="gl-content" id="gl_content"></div>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function () {
    const account = @json($encrypted);
    const viewUrl = '{{ route("ajax.gl.account.ledger.view") }}';
    const exportUrl = '{{ route("gl.account.ledger.export") }}';
    const pad = n => String(n).padStart(2, '0');
    const ymd = d => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    let request = null;

    // N months back from the To date (day clamped to the month end), plus one day
    function monthsBack(toStr, n) {
        const [y, m, d] = toStr.split('-').map(Number);
        const t = new Date(y, m - 1 - n, 1);
        t.setDate(Math.min(d, new Date(t.getFullYear(), t.getMonth() + 1, 0).getDate()) + 1);
        return ymd(t);
    }

    function syncPreset() {
        const from = $('#date_from').val(), to = $('#date_to').val();
        const hit = [['3m', 3], ['6m', 6], ['12m', 12]].find(p => to && monthsBack(to, p[1]) === from);
        $('#period_preset').val(hit ? hit[0] : 'custom');
    }

    $('#period_preset').on('change', function () {
        const n = { '3m': 3, '6m': 6, '12m': 12 }[$(this).val()];
        const to = $('#date_to').val();
        if (n && to) $('#date_from').val(monthsBack(to, n));
    });
    $('#date_from, #date_to').on('change', syncPreset);

    function load() {
        const from = $('#date_from').val(), to = $('#date_to').val();
        if (!from || !to) {
            toastr.warning('Please choose both dates');
            return;
        }
        if (from > to) {
            toastr.warning('The From date must not be after the To date');
            return;
        }

        const p = { account: account, from: from, to: to };
        $('#export_btn').attr('href', exportUrl + '?' + $.param(p));
        if (request) request.abort();
        $('#gl_content').addClass('is-loading');
        $('#gl_loading').css('display', 'flex');

        request = $.ajax({
            url: viewUrl,
            method: 'GET',
            dataType: 'html',
            data: p,
            success: function (html) {
                $('#gl_content').html(html);
            },
            error: function (xhr, status) {
                if (status === 'abort') return;
                const msg = xhr.status === 422 ? xhr.responseText : 'The ledger could not be loaded.';
                $('#gl_content').html('<div class="gl-empty"><span class="material-symbols-outlined">error</span><p class="mt-2 mb-0"></p></div>');
                $('#gl_content .gl-empty p').text(msg);
                toastr.error(msg);
            },
            complete: function (xhr, status) {
                if (status === 'abort') return;
                $('#gl_content').removeClass('is-loading');
                $('#gl_loading').hide();
            }
        });
    }

    $('#view_btn').on('click', load);
    syncPreset();
    load();
});
</script>
@endsection
