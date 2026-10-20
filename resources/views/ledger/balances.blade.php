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

                <div class="gl-filter d-flex flex-wrap align-items-end gap-3">
                    <div>
                        <label class="d-block" for="as_at">As at</label>
                        <input type="date" id="as_at" class="form-control form-control-sm" style="width:160px;" value="{{ $asAt }}">
                    </div>
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" id="hide_zero" checked>
                        <label class="form-check-label" for="hide_zero" style="text-transform:none;letter-spacing:0;font-weight:400;font-size:.85rem;">Hide accounts with no postings</label>
                    </div>
                    <div>
                        <a id="export_btn" class="btn btn-success btn-sm d-flex align-items-center gap-1" href="#">
                            <span class="material-symbols-outlined" style="font-size:17px;">download</span><span>Excel</span>
                        </a>
                    </div>
                    <div class="ms-auto text-muted" style="font-size:.78rem;max-width:520px;">
                        Balances come from the journals posted to the GL. Landlord events that are not posted yet are not in these figures, so accounts such as 1022 Clients Account will not match the landlord ledger until they are.
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
    const viewUrl = '{{ route("ajax.gl.balances.view") }}';
    const exportUrl = '{{ route("gl.balances.export") }}';
    let request = null;

    function params() {
        return { as_at: $('#as_at').val(), hide_zero: $('#hide_zero').is(':checked') ? 1 : 0 };
    }

    function load() {
        const p = params();
        if (!p.as_at) {
            toastr.warning('Please choose the As at date');
            return;
        }

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
                $('#gl_content').html('<div class="gl-empty"><span class="material-symbols-outlined">error</span><p class="mt-2 mb-0">The balances could not be loaded.</p></div>');
                toastr.error('Error loading the balances');
            },
            complete: function (xhr, status) {
                if (status === 'abort') return;
                $('#gl_content').removeClass('is-loading');
                $('#gl_loading').hide();
            }
        });
    }

    $('#as_at, #hide_zero').on('change', load);
    load();
});
</script>
@endsection
