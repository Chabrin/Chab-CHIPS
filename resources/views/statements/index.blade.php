@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Generate Statements</h4>
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('dashboard') }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back_ios_new</span>
                                <span class="ms-1">Back</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body" style="min-height:380px;">

                        <ul class="nav nav-tabs" id="statTabs" role="tablist">
                            <!-- <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="detailed-tab" data-bs-toggle="tab" data-bs-target="#detailed" type="button" role="tab">Detailed Statement</button>
                            </li> -->
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary" type="button" role="tab">Summary Statement</button>
                            </li>
                        </ul>

                        <div class="tab-content mt-3">
                            <!-- <div class="tab-pane fade show active" id="detailed" role="tabpanel" aria-labelledby="detailed-tab">
                                <form id="detailedForm">
                                    <div class="row g-3">
                                        <div class="col-8">
                                            <label class="form-label">Search member</label>
                                            <div class="input-group">
                                                <input id="memberSearch" type="search" class="form-control" placeholder="Enter member number, email or ID...">
                                                <button id="searchBtn" class="btn btn-primary btn-sm d-flex align-items-center btn-icon" type="button">
                                                    <span id="searchBtnSpinner" class="spinner-border spinner-border-sm d-none me-2" role="status" aria-hidden="true"></span>
                                                    <span class="material-symbols-outlined">search_activity</span>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="col-md-8">
                                            <label class="form-label">Select Member</label>
                                            <select id="searchResultsSelect" class="form-select" disabled>
                                                <option selected disabled value="">--- Select Member ---</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Select Account</label>
                                            <select id="memberAccounts" class="form-select" disabled>
                                                <option selected disabled value="">--- Select Account ---</option>
                                            </select>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">Start date</label>
                                            <input type="date" name="start_date" id="d_start" class="form-control" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">End date</label>
                                            <input type="date" name="end_date" id="d_end" class="form-control" required>
                                        </div>

                                        <div class="col-12 text-end">
                                            <br>
                                            <button id="viewDetailed" type="button" class="btn btn-primary btn-sm d-flex align-items-center" disabled>
                                                <span id="viewDetailedSpinner" class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                                                <span class="material-symbols-outlined">description</span>
                                                <span class="ms-1">View Detailed Statement</span>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div> -->

                            <div class="tab-pane fade show active" id="summary" role="tabpanel" aria-labelledby="summary-tab">
                                <form id="summaryForm">
                                    <div class="row g-3">
                                        <div class="col-8">
                                            <label class="form-label">Search member</label>
                                            <div class="input-group">
                                                <input id="memberSearch2" type="search" class="form-control" placeholder="Enter member number, email or ID...">
                                                <button id="searchBtn2" class="btn btn-primary btn-sm d-flex align-items-center btn-icon" type="button">
                                                    <span id="searchBtnSpinner2" class="spinner-border spinner-border-sm d-none me-2" role="status" aria-hidden="true"></span>
                                                    <span class="material-symbols-outlined">search_activity</span>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="col-md-8">
                                            <label class="form-label">Select Member</label>
                                            <select id="searchResultsSelect2" class="form-select" disabled>
                                                <option selected disabled value="">--- Select Member ---</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Select Account</label>
                                            <select id="memberAccounts2" class="form-select" disabled>
                                                <option selected disabled value="">--- Select Account ---</option>
                                            </select>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">End date</label>
                                            <input type="date" name="end_date" id="s_end" class="form-control" required>
                                        </div>

                                        <div class="col-12 text-end">
                                            <br>
                                            <div class="d-inline-flex align-items-center" style="gap:10px;">
                                                <button id="viewSummary" type="button" class="btn btn-primary btn-sm d-flex align-items-center" disabled style="min-width:220px;">
                                                    <span id="viewSummarySpinner" class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                                                    <span class="material-symbols-outlined">summarize</span>
                                                    <span class="ms-1">View Summary Statement</span>
                                                </button>
                                                <button id="emailSummaryBtn" type="button" class="btn btn-primary btn-sm d-flex align-items-center" disabled style="min-width:180px;">
                                                    <span id="emailSummarySpinner" class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                                                    <span class="material-symbols-outlined">email</span>
                                                    <span class="ms-1">Email Summary Statement</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                                <!-- Email will be sent to the member's registered email (no popup) -->
                                <!-- Hidden form to POST and open PDF in new tab -->
                                <form id="summaryPdfForm" action="{{ url('/get/statements/summary/pdf') }}" method="POST" target="_blank" style="display:none;">
                                    @csrf
                                    <input type="hidden" name="end_date" id="pdf_end_date">
                                    <input type="hidden" name="member_no" id="pdf_member_no">
                                    <input type="hidden" name="account_no" id="pdf_account_no">
                                </form>
                            </div>
                        </div>

                        <div id="searchEmpty" class="text-muted mt-3">No search performed yet.</div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    (function() {
        // Reuse search logic from upload_contributions
        function wireSearch(inputId, btnId, spinnerId, resultsId, accountsId) {
            var $input = $(inputId);
            var $searchBtn = $(btnId);
            var $results = $(resultsId);
            var $accounts = $(accountsId);
            var $spinner = $(spinnerId);

            var pending = null;
            var searchUrl = '{{ url("/submit/search/member") }}';

            function clearResults() {
                if (pending) try {
                    pending.abort();
                } catch (e) {}
                pending = null;
                $results.empty().append('<option selected disabled value="">--- Select Member ---</option>').prop('disabled', true);
                $accounts.empty().append('<option selected disabled value="">--- Select Account ---</option>').prop('disabled', true);
                $('#searchEmpty').text('No search performed yet.').show();
            }

            function doSearch(q) {
                clearResults();
                if (!q || q.trim().length === 0) {
                    toastr.warning('Enter a search term');
                    $input.focus();
                    return;
                }
                $searchBtn.prop('disabled', true);
                $spinner.removeClass('d-none');
                pending = $.get(searchUrl, {
                        q: q
                    })
                    .done(function(res) {
                        if (!res || !Array.isArray(res) || res.length === 0) {
                            $('#searchEmpty').text('No matches found').show();
                            toastr.info('No results');
                            return;
                        }
                        $('#searchEmpty').hide();
                        res.forEach(function(it) {
                            var val = it.raw_id || it.id;
                            var opt = $('<option>').val(val).text(it.label);
                            // preserve encrypted id and add member_no for controller compatibility
                            opt.attr('data-encrypted-id', it.id || '');
                            if (it.member_no) opt.attr('data-member-no', it.member_no);
                            else if (it.raw_id) opt.attr('data-member-no', it.raw_id);
                            $results.append(opt);
                            $results.prop('disabled', false);
                        });
                        toastr.success('Search returned ' + res.length + ' result(s)');
                    }).fail(function(xhr) {
                        var msg = 'Search failed';
                        try {
                            if (xhr && xhr.responseJSON) msg = xhr.responseJSON.error || xhr.responseJSON.message || msg;
                        } catch (e) {}
                        $('#searchEmpty').text(msg).show();
                        toastr.error(msg);
                    }).always(function() {
                        pending = null;
                        $searchBtn.prop('disabled', false);
                        $spinner.addClass('d-none');
                    });
            }

            $searchBtn.on('click', function() {
                doSearch($input.val());
            });

            $results.on('change', function() {
                var id = $(this).val();
                if (!id) return;
                $accounts.empty().append('<option selected disabled value="">Loading...</option>').prop('disabled', true);
                $.ajax({
                    url: '/submit/search/member/accounts',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        member_id: id
                    }
                }).done(function(res) {
                    if (res && res.accounts) {
                        $accounts.empty().append('<option selected disabled value="">--- Select Account ---</option>');
                        res.accounts.forEach(function(a) {
                            var text = (a.account_no || a.account_number || '') + (a.account_name ? ' - ' + a.account_name : '');
                            var opt = $('<option>').val(a.id).text(text);
                            // attach account_no so view can send account_no to controller
                            if (a.account_no) opt.attr('data-account-no', a.account_no);
                            else if (a.account_number) opt.attr('data-account-no', a.account_number);
                            opt.attr('data-fund-name', a.fund_name || '');
                            $accounts.append(opt);
                        });
                        $accounts.prop('disabled', false);
                    } else {
                        toastr.error(res.error || 'Failed to load accounts');
                    }
                }).fail(function() {
                    toastr.error('Failed to load accounts');
                    $accounts.empty().append('<option selected disabled value="">--- Select Account ---</option>').prop('disabled', true);
                });
            });
        }

        wireSearch('#memberSearch', '#searchBtn', '#searchBtnSpinner', '#searchResultsSelect', '#memberAccounts');
        wireSearch('#memberSearch2', '#searchBtn2', '#searchBtnSpinner2', '#searchResultsSelect2', '#memberAccounts2');

        function enableWhenReady(resultsSel, accountsSel, buttonSel) {
            $(resultsSel).add(accountsSel).on('change', function() {
                var r = $(resultsSel).val();
                var a = $(accountsSel).val();
                if (r && a) $(buttonSel).prop('disabled', false);
                else $(buttonSel).prop('disabled', true);
            });
        }

        enableWhenReady('#searchResultsSelect', '#memberAccounts', '#viewDetailed');
        enableWhenReady('#searchResultsSelect2', '#memberAccounts2', '#viewSummary');
        // Also enable Email Summary button when a member+account selected
        enableWhenReady('#searchResultsSelect2', '#memberAccounts2', '#emailSummaryBtn');

        $('#viewDetailed').on('click', function() {
            var btn = $(this);
            var spinner = $('#viewDetailedSpinner');
            var selectedMemberOpt = $('#searchResultsSelect option:selected');
            var selectedAccountOpt = $('#memberAccounts option:selected');
            var payload = {
                start_date: $('#d_start').val(),
                end_date: $('#d_end').val(),
                // send member_no/account_no as the controller expects; fall back to value
                member_no: selectedMemberOpt.data('member-no') || $('#searchResultsSelect').val(),
                account_no: selectedAccountOpt.data('account-no') || $('#memberAccounts').val()
            };
            btn.prop('disabled', true);
            spinner.removeClass('d-none');
            $.ajax({
                url: '{{ url("/get/statements/detailed") }}',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: payload
            }).done(function(res) {
                if (res && res.success) {
                    toastr.success(res.message || 'Detailed statement ready');
                } else {
                    toastr.error(res.message || 'Failed to prepare statement');
                }
            }).fail(function(xhr) {
                var msg = 'Failed to generate statement';
                try {
                    if (xhr && xhr.responseJSON) msg = xhr.responseJSON.message || xhr.responseJSON.error || msg;
                } catch (e) {}
                toastr.error(msg);
            }).always(function() {
                btn.prop('disabled', false);
                spinner.addClass('d-none');
            });
        });

        $('#viewSummary').on('click', async function() {
            var btn = $(this);
            var spinner = $('#viewSummarySpinner');
            var selectedMemberOpt2 = $('#searchResultsSelect2 option:selected');
            var selectedAccountOpt2 = $('#memberAccounts2 option:selected');
            var end = $('#s_end').val();
            var memberNo = selectedMemberOpt2.data('member-no') || $('#searchResultsSelect2').val();
            var accountNo = selectedAccountOpt2.data('account-no') || $('#memberAccounts2').val();

            btn.prop('disabled', true);
            spinner.removeClass('d-none');

            try {
                var token = $('meta[name="csrf-token"]').attr('content');
                var payload = new URLSearchParams();
                payload.append('end_date', end);
                payload.append('member_no', memberNo);
                payload.append('account_no', accountNo);

                var resp = await fetch('{{ url("/get/statements/summary/pdf") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': '*/*'
                    },
                    body: payload
                });

                var contentType = resp.headers.get('content-type') || '';

                if (resp.ok && contentType.indexOf('application/pdf') !== -1) {
                    var blob = await resp.blob();
                    var url = URL.createObjectURL(blob);
                    window.open(url, '_blank');
                } else {
                    // try parse json error, otherwise show text
                    var msg = 'Failed to generate PDF';
                    try {
                        if (contentType.indexOf('application/json') !== -1) {
                            var data = await resp.json();
                            msg = data.message || data.error || msg;
                        } else {
                            var txt = await resp.text();
                            // try to extract a message from HTML or plain text
                            msg = txt || msg;
                        }
                    } catch (e) {}
                    toastr.error(msg);
                }
            } catch (e) {
                toastr.error(e && e.message ? e.message : 'Network error while generating PDF');
            } finally {
                btn.prop('disabled', false);
                spinner.addClass('d-none');
            }
        });

        // Email summary flow: send email to member's registered email (server-side lookup)
        $('#emailSummaryBtn').on('click', function() {
            var btn = $(this);
            var spinner = $('#emailSummarySpinner');
            var selectedMemberOpt2 = $('#searchResultsSelect2 option:selected');
            var selectedAccountOpt2 = $('#memberAccounts2 option:selected');
            var end = $('#s_end').val();
            var accountNo = selectedAccountOpt2.data('account-no') || $('#memberAccounts2').val();

            if (!end || !accountNo) {
                toastr.error('Select account and end date first');
                return;
            }

            // show spinner on the email button
            btn.prop('disabled', true);
            spinner.removeClass('d-none');

            $.ajax({
                url: '{{ url("/email/statement/summary") }}',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    account_no: accountNo,
                    end_date: end
                }
            }).done(function(res) {
                if (res && res.success) {
                    toastr.success(res.message || 'Email job dispatched');
                } else {
                    toastr.error(res.message || 'Failed to dispatch email job');
                }
            }).fail(function(xhr) {
                var msg = 'Failed to send email job';
                try {
                    if (xhr && xhr.responseJSON) msg = xhr.responseJSON.message || xhr.responseJSON.error || msg;
                } catch (e) {}
                toastr.error(msg);
            }).always(function() {
                btn.prop('disabled', false);
                spinner.addClass('d-none');
            });
        });

    })();
</script>
@endsection