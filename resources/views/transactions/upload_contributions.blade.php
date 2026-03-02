@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Post Contributions</h4>
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('dashboard') }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back_ios_new</span>
                                <span class="ms-1">Back</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body" style="min-height:380px;">

                        <form id="postContributionForm">
                            <div class="row g-3">
                                <div class="col-lg-5">
                                    <label for="memberSearch" class="form-label">Search member</label>
                                    <div class="input-group">
                                        <input id="memberSearch" type="search" class="form-control" placeholder="Enter member number, email or ID...">
                                        <button id="searchBtn" class="btn btn-primary btn-sm d-flex align-items-center btn-icon" type="button">
                                            <span id="searchBtnSpinner" class="spinner-border spinner-border-sm d-none me-2" role="status" aria-hidden="true"></span>
                                            <span class="material-symbols-outlined">search_activity</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="col-lg-7">
                                    <label for="searchResultsSelect" class="form-label">Select Member <span class="text-danger">*</span></label>
                                    <select id="searchResultsSelect" class="form-select" disabled>
                                        <option selected disabled value="">--- Select Member ---</option>
                                    </select>
                                </div>

                                <div class="col-lg-4">
                                    <label for="memberAccounts" class="form-label">Select Account</label>
                                    <select id="memberAccounts" class="form-select" disabled>
                                        <option selected disabled value="">--- Select Account ---</option>
                                    </select>
                                </div>

                                <div class="col-lg-8">
                                    <div id="memberInfo" class="border rounded p-2 d-none">
                                        <div class="row">
                                            <div class="col-12 mb-1"><strong id="mi_name"></strong></div>
                                            <div class="col-md-4"><small class="text-muted">Member No</small>
                                                <div id="mi_no"></div>
                                            </div>
                                            <div class="col-md-8"><small class="text-muted">Email</small>
                                                <div id="mi_email"></div>
                                            </div>
                                            <div class="col-12 mt-2"><small class="text-muted">Fund</small>
                                                <div id="mi_fund"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <label for="navPrice" class="form-label">NAV (Price) <span class="text-danger">*</span></label>
                                    <input style="border:none !important" id="navPrice" type="text" class="form-control" readonly required>
                                </div>
                                <div class="col-lg-3">
                                    <label for="transDate" class="form-label">Trans Date <span class="text-danger">*</span></label>
                                    <input id="transDate" name="trans_date" type="date" class="form-control" required disabled>
                                </div>

                                <div class="col-lg-3">
                                    <label for="employerAmount" class="form-label">Employer Amount <span class="text-danger">*</span></label>
                                    <input id="employerAmount" name="employer_amount" type="number" step="0.01" min="0" value="0.00" class="form-control" required>
                                </div>
                                <div class="col-lg-3">
                                    <label for="employeeAmount" class="form-label">Employee Amount <span class="text-danger">*</span></label>
                                    <input id="employeeAmount" name="employee_amount" type="number" step="0.01" min="0" value="0.00" class="form-control" required>
                                </div>

                                <div class="col-lg-3">
                                    <label for="mop" class="form-label">Method of Payment <span class="text-danger">*</span></label>
                                    <select id="mop" name="mop" class="form-select" required>
                                        <option selected disabled value="">--- Select MOP ---</option>
                                        <option value="Cash">Cash</option>
                                        <option value="Cheque">Cheque</option>
                                    </select>
                                </div>

                                <div class="col-lg-3">
                                    <label for="reference" class="form-label">Reference (optional)</label>
                                    <input id="reference" name="reference" type="text" class="form-control">
                                </div>

                                <!-- Cheque Fields - Initially Hidden -->
                                <div id="chequeFields" class="col-12 d-none">
                                    <div class="row g-3">
                                        <div class="col-lg-4">
                                            <label for="drawerName" class="form-label">Drawer Name <span class="text-danger">*</span></label>
                                            <input id="drawerName" name="drawer_name" type="text" class="form-control" maxlength="30">
                                        </div>
                                        <div class="col-lg-4">
                                            <label for="chequeAccountNo" class="form-label">Cheque Account No <span class="text-danger">*</span></label>
                                            <input id="chequeAccountNo" name="cheque_account_no" type="text" class="form-control" maxlength="20">
                                        </div>
                                        <div class="col-lg-4">
                                            <label for="docNo" class="form-label">Document/Cheque No <span class="text-danger">*</span></label>
                                            <input id="docNo" name="doc_no" type="text" class="form-control" maxlength="30">
                                        </div>
                                        <div class="col-lg-6">
                                            <label for="drawerPayee" class="form-label">Drawer/Payee</label>
                                            <input id="drawerPayee" name="drawer_payee" type="text" class="form-control" maxlength="70">
                                        </div>
                                        <div class="col-lg-6">
                                            <label for="chequeComments" class="form-label">Cheque Comments</label>
                                            <input id="chequeComments" name="cheque_comments" type="text" class="form-control" maxlength="50">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-6 d-flex align-items-end justify-content-end">
                                    <button id="submitBtn" type="submit" class="btn btn-primary btn-sm d-flex align-items-center" disabled>
                                        <span id="submitSpinner" class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                                        <span class="material-symbols-outlined">
                                            arrow_upload_ready
                                        </span>
                                        <span class="ms-1 submit-text">Post Contribution</span>
                                    </button>
                                </div>
                                <div class="col-12">
                                    <div id="searchEmpty" class="text-muted">No search performed yet.</div>
                                </div>
                            </div>
                        </form>

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
        var $input = $('#memberSearch');
        var $searchBtn = $('#searchBtn');
        var $results = $('#searchResultsSelect');
        var $memberAccounts = $('#memberAccounts');
        var $memberInfo = $('#memberInfo');
        var $mi_name = $('#mi_name');
        var $mi_no = $('#mi_no');
        var $mi_email = $('#mi_email');
        var $mi_fund = $('#mi_fund');
        var $navPrice = $('#navPrice');
        var $transDate = $('#transDate');
        var $employerAmount = $('#employerAmount');
        var $employeeAmount = $('#employeeAmount');
        var $reference = $('#reference');
        var $mop = $('#mop');
        var $chequeFields = $('#chequeFields');
        var $drawerName = $('#drawerName');
        var $chequeAccountNo = $('#chequeAccountNo');
        var $docNo = $('#docNo');
        var $drawerPayee = $('#drawerPayee');
        var $chequeComments = $('#chequeComments');
        var $submitBtn = $('#submitBtn');
        var $searchEmpty = $('#searchEmpty');
        var pending = null;

        var searchUrl = '{{ url("/host/search/member") }}';

        function clearResults() {
            if (pending) {
                try {
                    pending.abort();
                } catch (e) {}
                pending = null;
            }
            $results.empty().append('<option selected disabled value="">--- Select Member ---</option>').prop('disabled', true);
            $memberAccounts.empty().append('<option selected disabled value="">--- Select Account ---</option>').prop('disabled', true);
            $memberInfo.addClass('d-none');
            $mi_name.text('');
            $mi_no.text('');
            $mi_email.text('');
            $mi_fund.text('');
            $navPrice.val('');
            $transDate.val('');
            $transDate.prop('disabled', true);
            $employerAmount.val('0.00');
            $employeeAmount.val('0.00');
            $mop.val('');
            $chequeFields.addClass('d-none');
            $drawerName.val('');
            $chequeAccountNo.val('');
            $docNo.val('');
            $drawerPayee.val('');
            $chequeComments.val('');
            $searchEmpty.text('No search performed yet.').show();
            $submitBtn.prop('disabled', true);
        }

        function parseLabel(label) {
            // label format: "member_no — all_names — e_mail_or_id"
            var parts = label.split('  ');
            if (parts.length < 3) parts = label.split(' — ');
            if (parts.length < 3) return {
                no: '',
                name: label,
                email: ''
            };
            return {
                no: parts[0].trim(),
                name: parts[1].trim(),
                email: parts[2].trim()
            };
        }

        function doSearch(q) {
            clearResults();
            if (!q || q.trim().length === 0) {
                toastr.warning('Enter a search term');
                $input.focus();
                return;
            }
            $searchBtn.prop('disabled', true);
            $('#searchBtnSpinner').removeClass('d-none');
            pending = $.get(searchUrl, {
                    q: q
                })
                .done(function(res) {
                    if (!res || !Array.isArray(res) || res.length === 0) {
                        $searchEmpty.text('No matches found').show();
                        toastr.info('No results');
                        return;
                    }
                    $searchEmpty.hide();
                    res.forEach(function(it) {
                        // it: {id: encrypted, raw_id: numeric, label: 'no — name — email'}
                        var val = it.raw_id || it.id;
                        var opt = $('<option>').val(val).text(it.label);
                        var parsed = parseLabel(it.label);
                        opt.attr('data-member-no', parsed.no || '');
                        opt.attr('data-member-name', parsed.name || '');
                        opt.attr('data-member-email', parsed.email || '');
                        opt.attr('data-encrypted-id', it.id || '');
                        $results.append(opt);
                        // enable the member select once we have at least one option
                        $results.prop('disabled', false);
                    });
                    toastr.success('Search returned ' + res.length + ' result(s)');
                }).fail(function(xhr) {
                    var msg = 'Search failed';
                    try {
                        if (xhr && xhr.responseJSON) msg = xhr.responseJSON.error || xhr.responseJSON.message || msg;
                    } catch (e) {}
                    $searchEmpty.text(msg).show();
                    toastr.error(msg);
                }).always(function() {
                    pending = null;
                    $searchBtn.prop('disabled', false);
                    $('#searchBtnSpinner').addClass('d-none');
                });
        }

        $searchBtn.on('click', function() {
            doSearch($input.val());
        });

        $results.on('change', function() {
            var $opt = $(this).find('option:selected');
            var id = $(this).val();
            if (!id) return;
            var name = $opt.attr('data-member-name') || $opt.text();
            var no = $opt.attr('data-member-no') || '';
            var email = $opt.attr('data-member-email') || '';
            $mi_name.text(name);
            $mi_no.text(no);
            $mi_email.text(email);
            $memberInfo.removeClass('d-none');
            $searchEmpty.hide();
            var postUrl = '/submit/search/member/accounts';
            $memberAccounts.empty().append('<option selected disabled value="">Loading...</option>').prop('disabled', true);
            $.ajax({
                url: postUrl,
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    member_id: id
                }
            }).done(function(res) {
                if (res && res.accounts) {
                    $memberAccounts.empty().append('<option selected disabled value="">--- Select Account ---</option>');
                    res.accounts.forEach(function(a) {
                        var text = (a.account_no || a.account_number || '') + (a.account_name ? ' - ' + a.account_name : '');
                        var opt = $('<option>').val(a.id).text(text);
                        opt.attr('data-security-code', a.security_code || '');
                        opt.attr('data-fund-name', a.fund_name || '');
                        $memberAccounts.append(opt);
                    });
                    $memberAccounts.prop('disabled', false);
                    // ensure member select stays enabled when accounts are loaded
                    $results.prop('disabled', false);
                } else {
                    toastr.error(res.error || 'Failed to load accounts');
                }
            }).fail(function() {
                toastr.error('Failed to load accounts');
                $memberAccounts.empty().append('<option selected disabled value="">--- Select Account ---</option>').prop('disabled', true);
            });
        });

        $memberAccounts.on('change', function() {
            var $opt = $(this).find('option:selected');
            var acctId = $(this).val();
            if (!acctId) return;
            var fundName = $opt.attr('data-fund-name') || '';
            var securityCode = $opt.attr('data-security-code') || '';
            $mi_fund.text(fundName);
            $navPrice.val('');
            $transDate.val('');
            $employerAmount.val('0.00');
            $employeeAmount.val('0.00');
            $submitBtn.prop('disabled', true);

            if (!securityCode) return;

            // enable date input only after account selection
            if (securityCode) {
                $transDate.prop('disabled', false);
            } else {
                $transDate.prop('disabled', true);
            }
            // clear navPrice until date is selected and lookup performed
            $navPrice.val('');
            validateForm();
        });

        $employerAmount.on('input', function() {
            validateForm();
        });
        $employeeAmount.on('input', function() {
            validateForm();
        });

        // MOP change handler - show/hide cheque fields
        $mop.on('change', function() {
            var mopValue = $(this).val();
            if (mopValue === 'Cheque') {
                $chequeFields.removeClass('d-none');
                // Make cheque fields required
                $drawerName.attr('required', true);
                $chequeAccountNo.attr('required', true);
                $docNo.attr('required', true);
            } else {
                $chequeFields.addClass('d-none');
                // Remove required attribute and clear values
                $drawerName.removeAttr('required').val('');
                $chequeAccountNo.removeAttr('required').val('');
                $docNo.removeAttr('required').val('');
                $drawerPayee.val('');
                $chequeComments.val('');
            }
            validateForm();
        });

        // block past dates for transDate
        (function setTransDateMin() {
            var today = new Date();
            var y = today.getFullYear();
            var m = String(today.getMonth() + 1).padStart(2, '0');
            var d = String(today.getDate()).padStart(2, '0');
            var min = y + '-' + m + '-' + d;
            $transDate.attr('min', min);
        })();

        // when user picks a date, fetch NAV price for that account+date
        $transDate.on('change', function() {
            var date = $(this).val();
            var accountId = $memberAccounts.val();
            if (!accountId || !date) {
                validateForm();
                return;
            }

            $navPrice.val('');
            $.ajax({
                url: '/show/navs/perdate',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    account_id: accountId,
                    date: date
                }
            }).done(function(res) {
                if (res && (res.price !== undefined && res.price !== null)) {
                    $navPrice.val(res.price);
                } else {
                    toastr.warning(res.error || 'No NAV price found for selected date');
                }
            }).fail(function(xhr) {
                var msg = 'Failed to lookup NAV';
                try {
                    if (xhr && xhr.responseJSON) msg = xhr.responseJSON.error || xhr.responseJSON.message || msg;
                } catch (e) {}
                toastr.error(msg);
            }).always(function() {
                validateForm();
            });
        });

        $results.on('change', function() {
            validateForm();
        });

        function validateForm() {
            var memberId = $results.val();
            var accountId = $memberAccounts.val();
            var employerVal = parseFloat($employerAmount.val());
            var employeeVal = parseFloat($employeeAmount.val());
            var navVal = $navPrice.val();
            var tdate = $transDate.val();
            var mopVal = $mop.val();

            // require employer > 0 OR employee > 0
            var amountsOk = (employerVal && employerVal > 0) || (employeeVal && employeeVal > 0);

            // Check cheque fields if MOP is Cheque
            var chequeOk = true;
            if (mopVal === 'Cheque') {
                chequeOk = !!$drawerName.val() && !!$chequeAccountNo.val() && !!$docNo.val();
            }

            var ok = !!memberId && !!accountId && !!navVal && !!tdate && !!mopVal && amountsOk && chequeOk;
            $submitBtn.prop('disabled', !ok);
            return ok;
        }

        $('#postContributionForm').on('submit', function(e) {
            e.preventDefault();
            var memberId = $results.val();
            var accountId = $memberAccounts.val();
            var employerAmount = $employerAmount.val();
            var employeeAmount = $employeeAmount.val();
            var empAmt = parseFloat(employerAmount) || 0;
            var emp2Amt = parseFloat(employeeAmount) || 0;
            if (!memberId || !accountId) {
                toastr.warning('Select member and account');
                return;
            }
            if (!(empAmt > 0 || emp2Amt > 0)) {
                toastr.warning('At least one of Employer or Employee amount must be greater than 0');
                return;
            }

            // Check MOP and cheque validation
            var mopVal = $mop.val();
            if (!mopVal) {
                toastr.warning('Please select Method of Payment');
                return;
            }
            if (mopVal === 'Cheque') {
                if (!$drawerName.val() || !$chequeAccountNo.val() || !$docNo.val()) {
                    toastr.warning('Please fill in all required cheque fields');
                    return;
                }
            }

            var payload = {
                member_id: memberId,
                account_id: accountId,
                employer_amount: employerAmount,
                employee_amount: employeeAmount,
                mop: mopVal,
                reference: $reference.val() || null,
                nav_price: $navPrice.val() || null,
                trans_date: $transDate.val() || null
            };

            // Add cheque fields if MOP is Cheque
            if (mopVal === 'Cheque') {
                payload.drawer_name = $drawerName.val();
                payload.cheque_account_no = $chequeAccountNo.val();
                payload.doc_no = $docNo.val();
                payload.drawer_payee = $drawerPayee.val() || null;
                payload.cheque_comments = $chequeComments.val() || null;
            }

            // preserve icon, show spinner and update label
            $submitBtn.prop('disabled', true);
            $('#submitSpinner').removeClass('d-none');
            $submitBtn.find('.submit-text').text('Posting...');
            $.ajax({
                url: '/transactions/post',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: payload
            }).done(function(res) {
                if (res && (res.success || res.status === 'ok')) {
                    toastr.success('Contribution posted');
                    clearResults();
                    $input.val('');
                } else {
                    toastr.error(res.error || 'Failed to post contribution');
                    // stop spinner and re-enable
                    $('#submitSpinner').addClass('d-none');
                    $submitBtn.prop('disabled', false);
                }
            }).fail(function(xhr) {
                var msg = 'Failed to post contribution';
                try {
                    if (xhr && xhr.responseJSON) msg = xhr.responseJSON.error || xhr.responseJSON.message || msg;
                } catch (e) {}
                toastr.error(msg);
                // stop spinner and re-enable button
                $('#submitSpinner').addClass('d-none');
                $submitBtn.prop('disabled', false);
            }).always(function() {
                $('#submitSpinner').addClass('d-none');
                $submitBtn.find('.submit-text').text('Post Contribution');
            });
        });

    })();
</script>
@endsection