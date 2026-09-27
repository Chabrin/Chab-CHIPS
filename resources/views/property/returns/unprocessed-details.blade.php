@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card card-no-border">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0" id="header-title">Unprocessed Return — {{ $return->return_code }}</h5>
                        <a href="{{ route('returns.batches.index') }}" class="btn btn-primary btn-sm">
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

                        <!-- Financial Summary -->
                        <style>
                            .summary-table tbody tr:nth-child(odd) {
                                background-color: #f8f9fa;
                            }

                            .summary-table tbody tr:nth-child(even) {
                                background-color: #fff;
                            }
                        </style>
                        <div id="financial-summary" class="row mb-1 gx-2"
                            data-paid-status="{{ $return->paid_status }}"
                            data-approved-status="{{ $return->approved_status }}"
                            data-enc-id="{{ encrypt($return->id) }}"
                            data-units-url="{{ route('ajax.returns.units', encrypt($return->id)) }}"
                            data-summary-base="{{ url('/ajax/returns') }}"
                            data-recovery-url="{{ url('/ajax/returns/lines') }}">
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
                                            <td class="text-muted py-1" width="50%">Status</td>
                                            <td class="text-end py-1" id="val-status">
                                                {{ $return->approved_status == 1 ? 'Approved' : 'Pending Approval' }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-1">Prepared By</td>
                                            <td class="text-end py-1" id="val-prepared-by">{{ $return->prepared_by ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-1">Payment Status</td>
                                            <td class="text-end py-1" id="val-paid-status">Unpaid</td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" class="py-1" style="border-top:1px solid #dee2e6;">Threshold Summary</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-1">Payable Units</td>
                                            <td class="text-end py-1" id="val-payable-units">{{ ($return->payable_units ?? 0) }} / {{ ($return->total_units ?? 0) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-1">Payable Amount</td>
                                            <td class="text-end py-1" id="val-payable-amount">{{ number_format($return->payable_amount ?? 0, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted py-1">Withheld Amount</td>
                                            <td class="text-end py-1" id="val-non-payable-amount">{{ number_format($return->non_payable_amount ?? 0, 2) }}</td>
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

                        <!-- Content Tabs: Detail Lines / Approvals -->
                        <style>
                            #contentTabs .nav-link {
                                color: #555;
                                font-weight: 600;
                                font-size: 0.88rem;
                                border: none;
                                border-bottom: 3px solid transparent;
                                border-radius: 0;
                                padding: 8px 18px;
                                transition: color .2s, border-color .2s;
                            }

                            #contentTabs .nav-link:hover {
                                color: #3598db;
                                border-bottom-color: #b8dcf0;
                            }

                            #contentTabs .nav-link.active {
                                color: #3598db;
                                font-weight: 700;
                                background: none;
                                border-bottom: 3px solid #3598db;
                            }
                        </style>
                        <ul class="nav nav-tabs border-bottom mb-0" id="contentTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="details-content-tab" data-bs-toggle="tab" data-bs-target="#details-lines-content" type="button" role="tab">
                                    <i class="fa fa-list me-1"></i> Detail Lines
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="approvals-content-tab" data-bs-toggle="tab" data-bs-target="#approvals-content" type="button" role="tab">
                                    <i class="fa fa-check-circle me-1"></i> Approvals
                                </button>
                            </li>
                            <li class="ms-auto d-flex align-items-center gap-2">
                                <a href="{{ route('returns.pdf', encrypt($return->id)) }}" id="pdf-link" class="btn btn-primary btn-sm py-0 px-2 d-flex align-items-center">
                                    <span class="material-symbols-outlined" style="font-size:14px;">picture_as_pdf</span>
                                    <span class="ms-1" id="pdf-btn-text" style="font-size:0.72rem;">PDF</span>
                                    <span id="pdf-spinner" class="spinner-border spinner-border-sm ms-1 d-none" style="width:.7rem;height:.7rem;"></span>
                                </a>
                                <a href="{{ route('returns.excel', encrypt($return->id)) }}" id="excel-link" class="btn btn-sm py-0 px-2 d-flex align-items-center" style="background-color:#00674F;border-color:#00674F;color:#fff;">
                                    <span class="material-symbols-outlined" style="font-size:14px;">download</span>
                                    <span class="ms-1" style="font-size:0.72rem;">Excel</span>
                                </a>
                            </li>
                        </ul>

                        <div class="tab-content" id="contentTabsContent">
                            <!-- Detail Lines Tab -->
                            <div class="tab-pane fade show active pt-2" id="details-lines-content" role="tabpanel">
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
                                                <th class="text-end">Amt</th>
                                                <th class="text-end">Net</th>
                                                <th class="text-end">Pay %</th>
                                                <th class="text-center">Payable?</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div><!-- /details-lines-content -->

                            <!-- Approvals Tab -->
                            <div class="tab-pane fade pt-2" id="approvals-content" role="tabpanel">
                                <div id="approvals-loader" class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Loading approval workflow...</p>
                                </div>

                                <div id="approvals-main-content" class="row" style="display: none;">
                                    <!-- Left Column: Conversation Trail -->
                                    <div class="col-md-8">
                                        <div class="card">
                                            <div class="card-header py-2" style="background: #3598db; color: white;">
                                                <h6 class="mb-0" style="font-size:0.8rem;"><i class="fa fa-comments me-2"></i>Approval Comments Trail</h6>
                                            </div>
                                            <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                                                <div id="comments-trail"></div>

                                                <div id="action-buttons-section" style="display: none;" class="mt-3 pt-2 border-top">
                                                    <div class="d-flex gap-2">
                                                        <button class="btn btn-primary btn-sm" id="approve-btn" data-bs-toggle="modal" data-bs-target="#approveModal">
                                                            <i class="fa fa-check me-1"></i> Approve
                                                        </button>
                                                        <button class="btn btn-danger btn-sm" id="reject-btn" data-bs-toggle="modal" data-bs-target="#rejectModal" style="display: none;">
                                                            <i class="fa fa-times me-1"></i> Reject
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Right Column: Compact Timeline -->
                                    <div class="col-md-4">
                                        <div class="card">
                                            <div class="card-header py-2" style="background: #3598db; color: white;">
                                                <h6 class="mb-0" style="font-size:0.8rem;"><i class="fa fa-list-ul me-2"></i>Approval Timeline</h6>
                                            </div>
                                            <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                                                <div id="compact-timeline"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div id="approvals-no-workflow" style="display: none;">
                                    <div class="alert alert-warning">
                                        <i class="fa fa-info-circle me-2"></i><span id="approvals-no-workflow-msg">No approval workflow found</span>
                                    </div>
                                </div>
                            </div><!-- /approvals-content -->
                        </div><!-- /tab-content -->

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recovery Modal -->
<div class="modal fade" id="recoveryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2" style="background:#7366ff;color:#fff;">
                <h6 class="modal-title d-flex align-items-center gap-1 mb-0" style="font-size:0.85rem;">
                    <span class="material-symbols-outlined" style="font-size:16px;">local_police</span>
                    Mark Recovery
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-2" style="font-size:0.78rem;">
                    <strong id="rec-tenant-name"></strong>
                    <span class="text-muted ms-2" id="rec-unit-code" style="font-size:0.72rem;"></span>
                </div>
                <div class="mb-3">
                    <label class="form-label" style="font-size:0.78rem;font-weight:600;">Recovery Reason <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm" id="rec-reason-select">
                        <option value="">-- Select Reason --</option>
                        <option value="34">Paid Vacant</option>
                        <option value="35">Sat Deposit</option>
                        <option value="36">Absconded</option>
                        <option value="37">Paid Owner</option>
                    </select>
                </div>
                <div id="rec-feedback" class="alert d-none py-1" style="font-size:0.75rem;"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary" id="rec-save-btn" style="background:#7366ff;border-color:#7366ff;">
                    <span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;">save</span> Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fa fa-check-circle me-2 text-success"></i>Approve Return</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to approve this return?</p>
                <div class="mb-3">
                    <label class="form-label">Comments (Optional)</label>
                    <textarea class="form-control" id="approve-comments" rows="3" placeholder="Add any comments..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirm-approve-btn">
                    <i class="fa fa-check me-1"></i> Confirm Approval
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fa fa-times-circle me-2 text-danger"></i>Reject Return</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Please provide a reason for rejecting this return and select which level to return it to.</p>
                <div class="mb-3">
                    <label class="form-label">Return to Level <span class="text-danger">*</span></label>
                    <select class="form-select" id="return-to-level" required>
                        <option value="">-- Select Level --</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="reject-reason" rows="3" placeholder="Enter reason for rejection..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirm-reject-btn">
                    <i class="fa fa-times me-1"></i> Confirm Rejection
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        var $meta = $('#financial-summary');
        var summaryBaseUrl = $meta.data('summary-base');
        var currentEncId = $meta.data('enc-id');
        var currentUnitsUrl = $meta.data('units-url');
        var recoveryBaseUrl = $meta.data('recovery-url');
        var csrfToken = $('meta[name="csrf-token"]').attr('content');
        var approvalsLoaded = false;
        var isEditable = ($meta.data('paid-status') == 0 && $meta.data('approved-status') == 0);

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
                    data: 'unit_code'
                },
                {
                    data: 'entry_description',
                    render: function(d) {
                        return '<small>' + (d || '-') + '</small>';
                    }
                },
                {
                    data: 'dr_cr',
                    render: function(d) { return d || '-'; }
                },
                {
                    data: 'amount_payable_landlord',
                    className: 'text-end'
                },
                {
                    data: 'net_amount',
                    className: 'text-end',
                    render: function(d) { return d || '0.00'; }
                },
                {
                    data: 'pay_percentage',
                    className: 'text-end',
                    render: function(d) {
                        if (!d || d === '' || d === '0') return '0.00';
                        return parseFloat(d).toFixed(2);
                    }
                },
                {
                    data: 'payable_status',
                    className: 'text-center',
                    orderable: false,
                    render: function(d, t, r) {
                        var ps = parseInt(d, 10);
                        var label = ps === 1 ? 'Payable' : 'Not Payable';
                        var reason = r.payable_status_reason ? '<br><small class="text-muted" style="font-size:0.62rem;">' + escapeAttr(r.payable_status_reason) + '</small>' : '';
                        return label + reason;
                    }
                },
                {
                    data: 'encrypted_id',
                    className: 'text-center',
                    orderable: false,
                    searchable: false,
                    render: function(d, t, r) {
                        if (!isEditable || r.dr_cr !== 'CR' || parseInt(r.payable_status) !== 1) return '';
                        return '<span class="material-symbols-outlined btn-recovery" ' +
                            'style="font-size:16px;cursor:pointer;vertical-align:middle;color:#0d6efd;" ' +
                            'data-line-id="' + d + '" ' +
                            'data-tenant="' + escapeAttr(r.tenant_name) + '" ' +
                            'data-unit="' + escapeAttr(r.unit_code) + '" ' +
                            'data-payable-status="' + (r.payable_status || '') + '" ' +
                            'title="Mark Recovery">edit_square</span>';
                    }
                }
            ],
            paging: true,
            info: true,
            lengthChange: true,
            searching: true,
            ordering: true,
            order: [
                [1, 'asc']
            ],
            pageLength: 50
        });

        function loadDetailLines(url) {
            currentUnitsUrl = url;
            $.ajax({
                url: url,
                type: 'GET',
                success: function(response) {
                    if (response.success) {
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
            $('#header-title').text('Unprocessed Return — ' + r.return_code);
            $('#hdr-return-code').text(r.return_code);
            $('#hdr-property').text(r.property_label || r.property_code || '-');
            $('#hdr-landlord').text(r.landlord_label || r.landlord_code || '-');
            $('#hdr-period').text(r.period);
            $('#hdr-type').text(r.return_type);

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

            // Update status info
            var statusBadge = r.approved_status == 1 ?
                '<span class="badge badge-light-success">Approved</span>' :
                '<span class="badge badge-light-warning">Pending Approval</span>';
            $('#val-status').html(statusBadge);
            $('#val-prepared-by').text(r.prepared_by);

            // Update threshold summary
            $('#val-payable-units').text((r.payable_units || 0) + ' / ' + (r.total_units || 0));
            $('#val-payable-amount').text(r.payable_amount || '0.00');
            $('#val-non-payable-amount').text(r.non_payable_amount || '0.00');

            // Update edit-ability flag for Recovery modal
            isEditable = (r.paid_status == 0 && r.approved_status == 0);

            var remarks = (r.remarks || '').trim();
            if (remarks) {
                $('#val-remarks').text('Remarks: ' + remarks);
                $('#remarks-row').show();
            } else {
                $('#remarks-row').hide();
            }

            $('#pdf-link').attr('href', r.pdf_url);
            $('#excel-link').attr('href', r.excel_url);
        }

        // PDF download handler
        $('#pdf-link').on('click', function(e) {
            e.preventDefault();
            var url = $(this).attr('href');
            if (!url) {
                toastr.warning('PDF generation not available');
                return;
            }
            triggerDownload(url, 'pdf-spinner', 'pdf-btn-text', 'Generating...', 'PDF');
        });

        function triggerDownload(url, spinnerSelector, textSelector, loadingText, originalText) {
            var token = 'token_' + Date.now();
            $('#' + spinnerSelector).removeClass('d-none');
            $('#' + textSelector).text(loadingText);

            var downloadUrl = url + (url.indexOf('?') >= 0 ? '&' : '?') + 'download_token=' + token;
            window.location.href = downloadUrl;

            var checkInterval = setInterval(function() {
                var cookieName = 'download_complete_' + token;
                if (document.cookie.indexOf(cookieName) !== -1) {
                    $('#' + spinnerSelector).addClass('d-none');
                    $('#' + textSelector).text(originalText);
                    clearInterval(checkInterval);
                }
            }, 500);

            setTimeout(function() {
                if ($('#' + spinnerSelector).not('.d-none').length) {
                    $('#' + spinnerSelector).addClass('d-none');
                    $('#' + textSelector).text(originalText);
                }
                clearInterval(checkInterval);
            }, 30000);
        }

        // Initial load of detail lines
        loadDetailLines(currentUnitsUrl);;

        // Tab click handler
        $('#return-tabs').on('click', 'a.nav-link', function(e) {
            e.preventDefault();
            var $tab = $(this);
            if ($tab.hasClass('active')) return;

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
                        currentEncId = encId;
                        response.return.pdf_url = response.pdfUrl;
                        response.return.excel_url = response.excelUrl;
                        updatePage(response.return, response.lineTotals);
                        loadDetailLines(response.unitsUrl);
                        // Reload approvals if that tab is active
                        if ($('#approvals-content-tab').hasClass('active')) {
                            approvalsLoaded = false;
                            loadApprovals();
                        } else {
                            approvalsLoaded = false;
                        }
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

        // ═══════════════════════════════════════════════════════════════
        // APPROVALS TAB LOGIC
        // ═══════════════════════════════════════════════════════════════

        // Load approvals when tab is clicked
        $('#approvals-content-tab').on('shown.bs.tab', function() {
            if (!approvalsLoaded) loadApprovals();
        });

        function loadApprovals() {
            $('#approvals-loader').show();
            $('#approvals-main-content').hide();
            $('#approvals-no-workflow').hide();

            $.ajax({
                url: summaryBaseUrl + '/' + encodeURIComponent(currentEncId) + '/approvals',
                method: 'GET',
                success: function(response) {
                    $('#approvals-loader').hide();
                    if (response.success) {
                        var data = response.data;
                        renderCompactTimeline(data.timeline);
                        renderCommentsTrail(data.timeline);

                        if (data.can_approve) {
                            $('#action-buttons-section').show();
                            $('#waiting-message').remove();
                            if (data.can_reject) {
                                $('#reject-btn').show();
                                var $sel = $('#return-to-level');
                                $sel.empty().append('<option value="">-- Select Level --</option>');
                                (data.available_reject_levels || []).forEach(function(lv) {
                                    $sel.append('<option value="' + lv.level + '">Level ' + lv.level + ' - ' + escapeHtml(lv.approver_name) + '</option>');
                                });
                            } else {
                                $('#reject-btn').hide();
                            }
                        } else {
                            $('#action-buttons-section').hide();
                            $('#waiting-message').remove();
                            if (data.waiting_for_previous && data.waiting_message) {
                                $('#comments-trail').after('<div id="waiting-message" class="alert alert-info mt-2 py-2" style="font-size:0.78rem;"><i class="fa fa-hourglass-half me-2"></i>' + escapeHtml(data.waiting_message) + '</div>');
                            }
                        }
                        $('#approvals-main-content').show();
                        approvalsLoaded = true;
                    } else {
                        $('#approvals-no-workflow-msg').text(response.message || 'No approval workflow found');
                        $('#approvals-no-workflow').show();
                    }
                },
                error: function() {
                    $('#approvals-loader').hide();
                    $('#approvals-no-workflow-msg').text('Failed to load approval workflow.');
                    $('#approvals-no-workflow').show();
                }
            });
        }

        function renderCompactTimeline(timeline) {
            var html = '<div class="timeline-steps">';
            timeline.forEach(function(item, idx) {
                var hasApproved = item.actions && item.actions.some(function(a) {
                    return a.action === 'Approved';
                });
                var hasReturned = item.actions && item.actions.some(function(a) {
                    return a.action === 'Returned' || a.action === 'Rejected';
                });
                var icon = '';
                if (hasApproved) icon = '<i class="fa fa-check-circle" style="color:#28a745;font-size:1.1rem;"></i>';
                else if (hasReturned) icon = '<i class="fa fa-times-circle" style="color:#dc3545;font-size:1.1rem;"></i>';
                else if (item.status === 'Pending') icon = '<i class="fa fa-clock" style="color:#ffc107;font-size:1.1rem;"></i>';
                else icon = '<i class="fa fa-circle" style="color:#dee2e6;font-size:1.1rem;"></i>';

                html += '<div class="d-flex align-items-start mb-3">';
                html += '<div class="me-3" style="min-width:28px;text-align:center;">' + icon;
                if (idx < timeline.length - 1) html += '<div style="width:2px;height:35px;background:#dee2e6;margin:4px auto 0;"></div>';
                html += '</div>';
                html += '<div class="flex-grow-1">';
                html += '<div style="font-size:0.8rem;font-weight:600;color:#495057;">Level ' + item.level + ' - ' + escapeHtml(item.approver_name) + '</div>';
                if (item.actions) {
                    item.actions.forEach(function(a) {
                        html += '<div style="font-size:0.7rem;color:#6c757d;">' + escapeHtml(a.action) + ' - ' + formatDate(a.action_timestamp);
                        if (a.returned_to_level) html += ' → Level ' + a.returned_to_level;
                        html += '</div>';
                    });
                }
                html += '</div></div>';
            });
            html += '</div>';
            $('#compact-timeline').html(html);
        }

        function renderCommentsTrail(timeline) {
            var allActions = [];
            timeline.forEach(function(item) {
                if (item.actions) {
                    item.actions.forEach(function(a) {
                        allActions.push({
                            level: item.level,
                            approver_name: item.approver_name,
                            action: a.action,
                            comments: a.comments,
                            action_timestamp: a.action_timestamp,
                            returned_to_level: a.returned_to_level
                        });
                    });
                }
            });
            allActions.sort(function(a, b) {
                return new Date(a.action_timestamp) - new Date(b.action_timestamp);
            });

            var html = '';
            if (allActions.length === 0) {
                html = '<div class="text-center text-muted py-3"><i class="fa fa-info-circle fa-2x mb-2"></i><p>No actions yet</p></div>';
            } else {
                allActions.forEach(function(a, idx) {
                    if (idx > 0) html += '<hr style="margin:10px 0;border:0;border-top:1px solid #e9ecef;">';
                    var actionIcon = '';
                    if (a.action === 'Approved') actionIcon = '<i class="fa fa-check-circle" style="color:#28a745;font-size:0.75rem;"></i> ';
                    else if (a.action === 'Returned' || a.action === 'Rejected') actionIcon = '<i class="fa fa-times-circle" style="color:#dc3545;font-size:0.75rem;"></i> ';
                    else if (a.action === 'Initiated') actionIcon = '<i class="fa fa-play-circle" style="color:#3598db;font-size:0.75rem;"></i> ';

                    html += '<div class="row">';
                    html += '<div class="col-3"><div class="d-flex align-items-start">';
                    html += '<div class="me-2" style="min-width:24px;"><div style="width:24px;height:24px;border-radius:50%;background:#d6ebf7;display:flex;align-items:center;justify-content:center;"><i class="fa fa-user" style="color:#3598db;font-size:0.7rem;"></i></div></div>';
                    html += '<span style="font-size:0.78rem;font-weight:600;color:#212529;">' + escapeHtml(a.approver_name).toUpperCase() + '</span>';
                    html += '</div></div>';
                    html += '<div class="col-9">';
                    html += '<div style="margin-bottom:4px;">' + actionIcon + '<span style="font-size:0.65rem;color:#3598db;font-style:italic;">' + formatDate(a.action_timestamp) + ' • L' + a.level + '</span></div>';
                    if (a.comments) {
                        html += '<div style="font-size:0.85rem;color:#495057;">' + escapeHtml(a.comments) + '</div>';
                    } else {
                        html += '<div style="font-size:0.85rem;color:#6c757d;font-style:italic;">' + a.action;
                        if (a.returned_to_level) html += ' to Level ' + a.returned_to_level;
                        html += '</div>';
                    }
                    html += '</div></div>';
                });
            }
            $('#comments-trail').html(html);
        }

        // Confirm approve
        $('#confirm-approve-btn').on('click', function() {
            var $btn = $(this);
            var comments = $('#approve-comments').val().trim();
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Processing...');

            $.ajax({
                url: summaryBaseUrl + '/' + encodeURIComponent(currentEncId) + '/approvals/approve',
                method: 'POST',
                data: {
                    comments: comments,
                    _token: csrfToken
                },
                success: function(response) {
                    if (response.success) {
                        $('#approveModal').modal('hide');
                        $('#approve-comments').val('');
                        $('#approvals-content').prepend('<div class="alert alert-success alert-dismissible fade show" role="alert"><i class="fa fa-check-circle me-2"></i>' + response.message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
                        approvalsLoaded = false;
                        loadApprovals();
                        setTimeout(function() {
                            $('.alert-success').fadeOut();
                        }, 5000);
                    } else {
                        alert(response.message || 'Failed to approve return');
                    }
                    $btn.prop('disabled', false).html('<i class="fa fa-check me-1"></i> Confirm Approval');
                },
                error: function(xhr) {
                    var msg = 'Failed to approve return.';
                    if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    alert(msg);
                    $btn.prop('disabled', false).html('<i class="fa fa-check me-1"></i> Confirm Approval');
                }
            });
        });

        // Confirm reject
        $('#confirm-reject-btn').on('click', function() {
            var $btn = $(this);
            var reason = $('#reject-reason').val().trim();
            var returnToLevel = $('#return-to-level').val();

            if (!returnToLevel) {
                alert('Please select a level to return to');
                return;
            }
            if (!reason) {
                alert('Please provide a reason for rejection');
                return;
            }

            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Processing...');

            $.ajax({
                url: summaryBaseUrl + '/' + encodeURIComponent(currentEncId) + '/approvals/reject',
                method: 'POST',
                data: {
                    reason: reason,
                    return_to_level: returnToLevel,
                    _token: csrfToken
                },
                success: function(response) {
                    if (response.success) {
                        $('#rejectModal').modal('hide');
                        $('#reject-reason').val('');
                        $('#return-to-level').val('');
                        $('#approvals-content').prepend('<div class="alert alert-warning alert-dismissible fade show" role="alert"><i class="fa fa-info-circle me-2"></i>' + response.message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
                        approvalsLoaded = false;
                        loadApprovals();
                        setTimeout(function() {
                            $('.alert-warning').fadeOut();
                        }, 5000);
                    } else {
                        alert(response.message || 'Failed to reject return');
                    }
                    $btn.prop('disabled', false).html('<i class="fa fa-times me-1"></i> Confirm Rejection');
                },
                error: function(xhr) {
                    var msg = 'Failed to reject return.';
                    if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    alert(msg);
                    $btn.prop('disabled', false).html('<i class="fa fa-times me-1"></i> Confirm Rejection');
                }
            });
        });

        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            var d = new Date(dateString);
            return d.toLocaleDateString('en-GB', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric'
                }) + ', ' +
                d.toLocaleTimeString('en-GB', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
        }

        function escapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }

        function escapeAttr(text) {
            return (text || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        // ═══════════════════════════════════════════════════════════════
        // RECOVERY MODAL
        // ═══════════════════════════════════════════════════════════════
        var currentRecoveryLineId = null;

        $(document).on('click', '.btn-recovery', function() {
            currentRecoveryLineId = $(this).data('line-id');
            var existingStatus = $(this).data('payable-status');
            $('#rec-tenant-name').text($(this).data('tenant'));
            $('#rec-unit-code').text($(this).data('unit'));
            var validStatuses = [34, 35, 36, 37];
            $('#rec-reason-select').val(
                existingStatus && validStatuses.includes(parseInt(existingStatus)) ? existingStatus : ''
            );
            $('#rec-save-btn').prop('disabled', false).html(
                '<span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;">save</span> Save'
            );
            $('#rec-feedback').addClass('d-none').text('');
            $('#recoveryModal').modal('show');
        });

        $('#rec-save-btn').on('click', function() {
            var status = $('#rec-reason-select').val();
            if (!status) {
                $('#rec-feedback').removeClass('d-none alert-success').addClass('alert-warning').text('Please select a recovery reason.');
                return;
            }
            var $btn = $(this).prop('disabled', true).html(
                '<span class="spinner-border spinner-border-sm me-1"></span> Saving...'
            );
            $('#rec-feedback').addClass('d-none');

            $.ajax({
                url: recoveryBaseUrl + '/' + encodeURIComponent(currentRecoveryLineId) + '/recovery',
                method: 'POST',
                data: {
                    payable_status: status,
                    _token: csrfToken
                },
                success: function(response) {
                    if (response.success) {
                        $('#recoveryModal').modal('hide');
                        // Refresh detail lines table
                        loadDetailLines(currentUnitsUrl);
                        // Update threshold summary panel
                        var s = response.summary;
                        $('#val-payable-units').text(s.payable_units + ' / ' + s.total_units);
                        $('#val-payable-amount').text(s.payable_amount);
                        $('#val-non-payable-amount').text(s.non_payable_amount);
                    } else {
                        $('#rec-feedback').removeClass('d-none alert-warning').addClass('alert-danger').text(response.message || 'Failed to save.');
                        $btn.prop('disabled', false).html(
                            '<span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;">save</span> Save'
                        );
                    }
                },
                error: function(xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'An error occurred.';
                    $('#rec-feedback').removeClass('d-none alert-warning').addClass('alert-danger').text(msg);
                    $btn.prop('disabled', false).html(
                        '<span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;">save</span> Save'
                    );
                }
            });
        });
    });
</script>
@endsection