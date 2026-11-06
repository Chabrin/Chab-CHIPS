@extends('layouts.main')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/return-statement.css') }}?v={{ filemtime(public_path('assets/css/return-statement.css')) }}">
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card card-no-border">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0" id="header-title">Return Details — {{ $return->return_code }}</h5>
                        <a href="{{ route('processed.returns.index') }}" class="btn btn-primary btn-sm">
                            <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">arrow_back</span> Back
                        </a>
                    </div>
                    <div class="card-body py-2 px-3" style="font-size:0.80rem;">

                        {{-- Tabs for switching between returns --}}
                        @if($allReturns->count() > 1)
                        <style>
                            #return-tabs .nav-link {
                                color: #555;
                                background: none;
                                border: none;
                                border-bottom: 3px solid transparent;
                                border-radius: 0;
                                font-weight: 500;
                                transition: color .2s, border-color .2s;
                            }

                            #return-tabs .nav-link:hover {
                                color: #7366ff;
                                border-bottom-color: #c5c0ff;
                            }

                            #return-tabs .nav-link.active {
                                color: #7366ff;
                                font-weight: 600;
                                background: none;
                                border-bottom: 3px solid #7366ff;
                            }
                        </style>
                        <ul class="nav mb-2 border-bottom" id="return-tabs" style="font-size:0.80rem;">
                            @foreach($allReturns as $ret)
                            <li class="nav-item">
                                <a href="#" class="nav-link py-1 px-3 {{ $ret->id == $return->id ? 'active' : '' }}"
                                    data-enc-id="{{ encrypt($ret->id) }}" data-return-code="{{ $ret->return_code }}">
                                    {{ $ret->return_type }}
                                    <span class="spinner-border spinner-border-sm ms-1 d-none" style="width:.8rem;height:.8rem;" role="status"></span>
                                </a>
                            </li>
                            @endforeach
                        </ul>
                        @endif
                        <!-- Return Header Info -->
                        <div class="mb-1" id="header-info" style="font-size:0.72rem;color:#666;">
                            <span class="text-muted">Return Code:</span> <strong id="hdr-return-code">{{ $headerInfo['return_code'] }}</strong>
                            <span class="mx-1 text-muted">|</span>
                            <span class="text-muted">Property:</span> <strong id="hdr-property">{{ $headerInfo['property_label'] }}</strong>
                            <span class="mx-1 text-muted">|</span>
                            <span class="text-muted">Landlord:</span> <strong id="hdr-landlord">{{ $headerInfo['landlord_label'] }}</strong>
                            <span class="mx-1 text-muted">|</span>
                            <span class="text-muted">Period:</span> <strong id="hdr-period">{{ $headerInfo['period'] }}</strong>
                            <span class="mx-1 text-muted">|</span>
                            <span class="text-muted">Type:</span> <strong id="hdr-type">{{ $headerInfo['return_type'] }}</strong>
                        </div>

                        @if(!empty($headerInfo['merged']))
                        <!-- Merged return: the property returns it covers -->
                        <div id="merged-summary" class="border rounded p-2 mb-2" style="font-size:0.72rem;">
                            <div class="fw-bold mb-1">Merged return{{ $headerInfo['merged']['batch_reference'] ? ' &middot; batch ' . $headerInfo['merged']['batch_reference'] : '' }}</div>
                            <table class="table table-sm table-bordered mb-1" style="font-size:0.72rem;">
                                <thead class="table-light"><tr><th>Property</th><th>Return</th><th class="text-end">Net</th><th>Status</th></tr></thead>
                                <tbody>
                                    @foreach($headerInfo['merged']['properties'] as $row)
                                    <tr>
                                        <td>{{ $row['property'] }}</td>
                                        <td><a href="{{ $row['url'] }}">{{ $row['return_code'] }}</a></td>
                                        <td class="text-end">{{ number_format($row['net'], 2) }}</td>
                                        <td>{{ $row['status'] }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr><td colspan="2" class="text-end">Total of property returns</td><td class="text-end">{{ number_format($headerInfo['merged']['total'], 2) }}</td><td></td></tr>
                                    <tr><td colspan="2" class="text-end">Less landlord deductions (listed below)</td><td class="text-end">{{ number_format($headerInfo['merged']['deductions'], 2) }}</td><td></td></tr>
                                    <tr class="fw-bold"><td colspan="2" class="text-end">Net payable</td><td class="text-end">{{ number_format($headerInfo['merged']['net'], 2) }}</td><td></td></tr>
                                </tfoot>
                            </table>
                            <div class="text-muted">Paid into: {{ $headerInfo['merged']['bank'] ? trim(($headerInfo['merged']['bank']['bank_name'] ?? '') . ' ' . ($headerInfo['merged']['bank']['account_name'] ?? '') . ' ' . ($headerInfo['merged']['bank']['account_number'] ?? '')) : 'no bank account chosen for this batch: paid by cheque' }}</div>
                        </div>
                        @endif

                        <!-- Detail Lines -->
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-1 mb-1">
                            <h6 class="mb-0" style="font-size:0.8rem;">Return statement</h6>
                            <div class="d-flex gap-2">
                                <a href="{{ route('processed.returns.excel', encrypt($return->id)) }}" id="excel-link" class="btn btn-sm py-0 px-2 d-flex align-items-center" style="background-color:#00674F;border-color:#00674F;color:#fff;">
                                    <span class="material-symbols-outlined" style="font-size:14px;">download</span>
                                    <span class="ms-1" id="excel-btn-text" style="font-size:0.72rem;">Excel</span>
                                    <span id="excel-spinner" class="spinner-border spinner-border-sm ms-1 d-none" style="width:.7rem;height:.7rem;"></span>
                                </a>
                                <a href="{{ route('processed.returns.pdf', encrypt($return->id)) }}" id="pdf-link" class="btn btn-primary btn-sm py-0 px-2 d-flex align-items-center">
                                    <span class="material-symbols-outlined" style="font-size:14px;">picture_as_pdf</span>
                                    <span class="ms-1" id="pdf-btn-text" style="font-size:0.72rem;">PDF</span>
                                    <span id="pdf-spinner" class="spinner-border spinner-border-sm ms-1 d-none" style="width:.7rem;height:.7rem;"></span>
                                </a>
                            </div>
                        </div>
                        <details id="return-transaction-details" class="mb-3 border rounded p-2">
                            <summary class="fw-semibold">Transaction details and processing totals</summary>
                        <style>
                            #details-table td,
                            #details-table th {
                                padding: 2px 4px !important;
                                font-size: 0.78rem !important;
                                line-height: 1.2;
                            }
                        </style>
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="details-table" style="width:100%;font-size:0.66rem;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Narration</th>
                                        <th>Tenant</th>
                                        <th>Unit</th>
                                        <th>Description</th>
                                        <th>Dr/Cr</th>
                                        <th class="text-end">Landlord Amt</th>
                                        <th class="text-end">Net</th>
                                        <th class="text-end">VAT</th>
                                        <th>Ref</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <!-- Financial Summary -->
                        <style>
                            .summary-table tbody tr:nth-child(odd) {
                                background-color: #fff;
                            }

                            .summary-table tbody tr:nth-child(even) {
                                background-color: #fff;
                            }
                        </style>
                        <div id="financial-summary" class="row mb-1 gx-2">
                            <div class="col-md-6">
                                <table class="table table-borderless table-sm mb-0 summary-table" style="font-size:0.72rem;">
                                    <tbody id="credits-debits-body">
                                        <tr>
                                            <td class="py-1 fw-bold" width="50%" colspan="2">Credits</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-1 ps-3">Rent</td>
                                            <td class="text-end py-1" id="val-rent">{{ number_format($lineTotals->rent_total ?? 0, 2) }}</td>
                                        </tr>
                                        <tr id="row-add-back" style="{{ ($lineTotals->add_back ?? 0) > 0 ? '' : 'display:none;' }}">
                                            <td class="text-muted py-1 ps-3">Add Back Deductions</td>
                                            <td class="text-end py-1" id="val-add-back">{{ number_format($lineTotals->add_back ?? 0, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="py-1 fw-bold ps-3">Total Credits</td>
                                            <td class="text-end py-1 fw-bold" style="color:#3598db;" id="val-total-cr">{{ number_format($lineTotals->total_cr ?? 0, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="py-1 fw-bold" colspan="2">Debits</td>
                                        </tr>
                                        <tr id="row-deductions" style="{{ ($lineTotals->deductions ?? 0) > 0 ? '' : 'display:none;' }}">
                                            <td class="text-muted py-1 ps-3">Deductions Before Commission</td>
                                            <td class="text-end py-1" id="val-deductions">{{ number_format($lineTotals->deductions ?? 0, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-1 ps-3">Commissionable Total</td>
                                            <td class="text-end py-1" id="val-comm-total">{{ number_format($return->commission_total ?? 0, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-1 ps-3" id="lbl-commission">Commission @ {{ number_format($return->commission_rate ?? 0, 2) }}%</td>
                                            <td class="text-end py-1" id="val-commission">{{ number_format($lineTotals->commission ?? 0, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-1 ps-3">VAT on Commission</td>
                                            <td class="text-end py-1" id="val-vat">{{ number_format($lineTotals->vat ?? 0, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="py-1 fw-bold ps-3">Total Debits</td>
                                            <td class="text-end py-1 fw-bold" style="color:#3598db;" id="val-total-dr">{{ number_format($lineTotals->total_dr ?? 0, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-bold py-1">Net Amount (Credits − Debits)</td>
                                            <td class="text-end py-1 fw-bold" style="color:#3598db;" id="val-net">{{ number_format(($lineTotals->total_cr ?? 0) - ($lineTotals->total_dr ?? 0), 2) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless table-sm mb-0 summary-table" style="font-size:0.72rem;">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted py-1" width="50%">Amount Paid</td>
                                            <td class="text-end py-1 fw-bold" id="val-amount-paid">{{ number_format($return->amount_paid ?? 0, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-1">Voucher</td>
                                            <td class="text-end py-1" id="val-voucher">{{ $return->voucher_number ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-1">Cheque</td>
                                            <td class="text-end py-1" id="val-cheque">{{ $return->cheque_number ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-1">Payment Date</td>
                                            <td class="text-end py-1" id="val-payment-date">{{ $return->payment_date ?? '-' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="row mb-1" id="remarks-row" style="{{ ($return->remarks || $return->remarks_2) ? '' : 'display:none;' }}">
                            <div class="col-12">
                                <div class="p-1" style="font-size:0.7rem;color:#212529;" id="val-remarks">Remarks: {{ $return->remarks }} {{ $return->remarks_2 }}</div>
                            </div>
                        </div>

                        </details>
                        <div id="return-statement" aria-live="polite"><p class="text-muted py-3">Loading return statement...</p></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('assets/js/return-statement.js') }}?v={{ filemtime(public_path('assets/js/return-statement.js')) }}"></script>

<script>
    $(document).ready(function() {
        var summaryBaseUrl = '{{ url("/ajax/processed/returns") }}';
        var currentReturnType = '{{ $return->return_type }}';

        // Pads every number in a code so codes compare in natural order: B2 before B10
        function naturalKey(value) {
            return String(value || '').toLowerCase().replace(/\d+/g, function(n) {
                return n.padStart(12, '0');
            });
        }

        var table = $('#details-table').DataTable({
            data: [],
            columns: [{
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(d, t, r, m) {
                        return m.row + 1;
                    }
                },
                {
                    data: 'narration'
                },
                {
                    data: 'tenant_name'
                },
                {
                    data: 'unit_code',
                    render: function(d, type) {
                        // Sort and type detection use the natural key (B2 before B10); the cell still shows the code as it is
                        return (type === 'sort' || type === 'type') ? naturalKey(d) : d;
                    }
                },
                {
                    data: 'entry_description',
                    render: function(d) {
                        return '<small>' + (d || '-') + '</small>';
                    }
                },
                {
                    data: 'dr_cr',
                    render: function(d) {
                        if (d === 'CR') return '<span class="badge badge-light-success">CR</span>';
                        if (d === 'DR') return '<span class="badge badge-light-danger">DR</span>';
                        return d;
                    }
                },
                {
                    data: 'amount_payable_landlord',
                    className: 'text-end'
                },
                {
                    data: 'net_amount',
                    className: 'text-end',
                    render: function(d) {
                        return '<strong>' + d + '</strong>';
                    }
                },
                {
                    data: 'vat_amount',
                    className: 'text-end'
                },
                {
                    data: 'transaction_ref'
                }
            ],
            paging: true,
            info: true,
            lengthChange: true,
            searching: true,
            ordering: true,
            order: [
                [1, 'asc'],
                [3, 'asc']
            ],
            pageLength: 50
        });

        $('#return-transaction-details').on('toggle', function() {
            if (this.open) table.columns.adjust();
        });

        function loadDetailLines(url) {
            $.ajax({
                url: url,
                type: 'GET',
                success: function(response) {
                    if (response.success) {
                        renderReturnStatement(response);
                        table.clear();
                        table.rows.add(response.data);
                        table.draw();
                    }
                },
                error: function(xhr) {
                    toastr.error((xhr.responseJSON && xhr.responseJSON.error) || 'Failed to load return detail lines.');
                }
            });
        }

        function updatePage(r, lt) {
            $('#header-title').text('Return Details — ' + r.return_code);
            $('#hdr-return-code').text(r.return_code);
            $('#hdr-property').text(r.property_label || r.property_code || '-');
            $('#hdr-landlord').text(r.landlord_label || r.landlord_code || '-');
            $('#hdr-period').text(r.period);
            $('#hdr-type').text(r.return_type);
            $('#merged-summary').toggle(r.return_type === 'MASTERRETURN');
            currentReturnType = r.return_type;

            $('#val-rent').text(lt.rent_total);
            if (lt.add_back_raw > 0) {
                $('#val-add-back').text(lt.add_back);
                $('#row-add-back').show();
            } else {
                $('#row-add-back').hide();
            }
            $('#val-total-cr').text(lt.total_cr);

            if (lt.deductions_raw > 0) {
                $('#val-deductions').text(lt.deductions);
                $('#row-deductions').show();
            } else {
                $('#row-deductions').hide();
            }
            $('#val-comm-total').text(r.commission_total);
            $('#lbl-commission').text('Commission @ ' + r.commission_rate + '%');
            $('#val-commission').text(lt.commission);
            $('#val-vat').text(lt.vat);
            $('#val-total-dr').text(lt.total_dr);
            $('#val-net').text(lt.net_amount);

            $('#val-amount-paid').text(r.amount_paid);
            $('#val-voucher').text(r.voucher_number);
            $('#val-cheque').text(r.cheque_number);
            $('#val-payment-date').text(r.payment_date);

            var remarks = (r.remarks || '').trim();
            if (remarks) {
                $('#val-remarks').text('Remarks: ' + remarks);
                $('#remarks-row').show();
            } else {
                $('#remarks-row').hide();
            }

            $('#excel-link').attr('href', r.excel_url);
            $('#pdf-link').attr('href', r.pdf_url);
        }

        // Download with spinner: polls a cookie set by server when file is ready
        function triggerDownload(url, spinnerId, btnTextId, loadingText, originalText) {
            var token = Math.random().toString(36).substr(2, 9);
            $('#' + spinnerId).removeClass('d-none');
            $('#' + btnTextId).text(loadingText);
            window.location.href = url + (url.indexOf('?') >= 0 ? '&' : '?') + 'download_token=' + token;
            var cookieName = 'download_complete_' + token;
            var interval = setInterval(function() {
                if (document.cookie.indexOf(cookieName + '=1') !== -1) {
                    clearInterval(interval);
                    document.cookie = cookieName + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
                    $('#' + spinnerId).addClass('d-none');
                    $('#' + btnTextId).text(originalText);
                }
            }, 500);
            // Fallback: hide spinner after 60s regardless
            setTimeout(function() {
                clearInterval(interval);
                $('#' + spinnerId).addClass('d-none');
                $('#' + btnTextId).text(originalText);
            }, 60000);
        }

        // Excel spinner
        $('#excel-link').on('click', function(e) {
            e.preventDefault();
            if (returnStatementHasUnsavedRemarks()) { alert('Save your remarks before downloading the statement.'); return; }
            var allowed = ['MINIRETURN', 'MAINRETURN', 'MASTERRETURN'];
            if (allowed.indexOf(currentReturnType) === -1) {
                Swal.fire({
                    icon: 'error',
                    title: 'Download Not Available',
                    text: currentReturnType + ' returns do not support Excel download.'
                });
                return;
            }
            triggerDownload($(this).attr('href'), 'excel-spinner', 'excel-btn-text', 'Downloading...', 'Excel');
        });

        // PDF spinner
        $('#pdf-link').on('click', function(e) {
            e.preventDefault();
            if (returnStatementHasUnsavedRemarks()) { alert('Save your remarks before downloading the statement.'); return; }
            var allowed = ['MINIRETURN', 'MAINRETURN', 'MASTERRETURN'];
            if (allowed.indexOf(currentReturnType) === -1) {
                Swal.fire({
                    icon: 'error',
                    title: 'Download Not Available',
                    text: currentReturnType + ' returns do not support PDF download.'
                });
                return;
            }
            triggerDownload($(this).attr('href'), 'pdf-spinner', 'pdf-btn-text', 'Generating...', 'PDF');
        });

        // Initial load of detail lines
        loadDetailLines('{{ route("ajax.processed.returns.units", encrypt($return->id)) }}');

        // Tab/pill click handler
        $('#return-tabs').on('click', 'a.nav-link', function(e) {
            e.preventDefault();
            var $tab = $(this);
            if ($tab.hasClass('active')) return;
            if (returnStatementHasUnsavedRemarks()) { alert('Save your remarks before switching returns.'); return; }

            var encId = $tab.data('enc-id');
            var $spinner = $tab.find('.spinner-border');

            $('#return-tabs a.nav-link').removeClass('active');
            $tab.addClass('active');
            $spinner.removeClass('d-none');

            $.ajax({
                url: summaryBaseUrl + '/' + encodeURIComponent(encId) + '/summary',
                type: 'GET',
                success: function(response) {
                    if (response.success) {
                        response.return.excel_url = response.excelUrl;
                        response.return.pdf_url = response.pdfUrl;
                        updatePage(response.return, response.lineTotals);
                        loadDetailLines(response.unitsUrl);
                    }
                },
                error: function() {
                    alert('Failed to load return data');
                },
                complete: function() {
                    $spinner.addClass('d-none');
                }
            });
        });
    });
</script>
@endsection
