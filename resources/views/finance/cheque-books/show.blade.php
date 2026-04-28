@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">

        {{-- Back link --}}
        <div class="mb-3">
            <a href="{{ route('cheque-books.index') }}" class="btn btn-light btn-sm d-inline-flex align-items-center">
                <span class="material-symbols-outlined">arrow_back</span>
                <span class="ms-1">Back to Cheque Books</span>
            </a>
        </div>

        {{-- Header card --}}
        <div class="row mb-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body pb-2">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <small class="text-muted d-block">Reference</small>
                                <strong>{{ $book->reference_number ?: '—' }}</strong>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted d-block">Bank Account</small>
                                <strong>{{ $book->bankAccount ? $book->bankAccount->account_name : '—' }}</strong>
                            </div>
                            <div class="col-md-2">
                                <small class="text-muted d-block">Bank</small>
                                <strong>{{ ($book->bankAccount && $book->bankAccount->bank) ? $book->bankAccount->bank->bank_name : '—' }}</strong>
                            </div>
                            <div class="col-md-2">
                                <small class="text-muted d-block">First Cheque No.</small>
                                <strong>{{ $book->first_cheque_number }}</strong>
                            </div>
                            <div class="col-md-1">
                                <small class="text-muted d-block">Status</small>
                                <span class="badge {{ $book->status_badge }}">{{ $book->status_label }}</span>
                            </div>
                            <div class="col-md-1">
                                <small class="text-muted d-block">Total Leaves</small>
                                <strong>{{ $book->leafs }}</strong>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted d-block">Created By</small>
                                <strong>{{ $book->createdBy ? $book->createdBy->name : '—' }}</strong>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted d-block">Created At</small>
                                <strong>{{ $book->date_time ? $book->date_time->format('d M Y') : '—' }}</strong>
                            </div>
                            @if($book->notes)
                            <div class="col-12">
                                <small class="text-muted d-block">Notes</small>
                                <span>{{ $book->notes }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Stat tiles --}}
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="card bg-light-success border-0">
                    <div class="card-body d-flex align-items-center py-3">
                        <span class="material-symbols-outlined text-success me-3" style="font-size:36px;">check_circle</span>
                        <div>
                            <div class="fw-bold fs-4" id="statAvailable">—</div>
                            <small class="text-muted">Available</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light-secondary border-0">
                    <div class="card-body d-flex align-items-center py-3">
                        <span class="material-symbols-outlined text-secondary me-3" style="font-size:36px;">payments</span>
                        <div>
                            <div class="fw-bold fs-4" id="statUsed">—</div>
                            <small class="text-muted">Used</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light-warning border-0">
                    <div class="card-body d-flex align-items-center py-3">
                        <span class="material-symbols-outlined text-warning me-3" style="font-size:36px;">lock</span>
                        <div>
                            <div class="fw-bold fs-4" id="statLocked">—</div>
                            <small class="text-muted">Locked</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light-danger border-0">
                    <div class="card-body d-flex align-items-center py-3">
                        <span class="material-symbols-outlined text-danger me-3" style="font-size:36px;">cancel</span>
                        <div>
                            <div class="fw-bold fs-4" id="statCancelled">—</div>
                            <small class="text-muted">Cancelled</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Leaves table --}}
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center flex-wrap gap-2">
                        <h5 class="mb-0">Cheque Leaves</h5>
                        <div class="ms-auto d-flex gap-1 flex-wrap" id="statusFilterBtns">
                            <button class="btn btn-sm btn-outline-secondary filter-btn active" data-status="">All</button>
                            <button class="btn btn-sm btn-outline-success filter-btn" data-status="1">Available</button>
                            <button class="btn btn-sm btn-outline-warning filter-btn" data-status="2">Locked</button>
                            <button class="btn btn-sm btn-outline-secondary filter-btn" data-status="3">Used</button>
                            <button class="btn btn-sm btn-outline-danger filter-btn" data-status="4">Cancelled</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="leavesTable" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Cheque Number</th>
                                        <th>Status</th>
                                        <th>Locked By</th>
                                        <th>Locked At</th>
                                        <th>Used By</th>
                                        <th>Used At</th>
                                        <th>Used For</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Cancel Cheque Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancel Cheque <span id="cancelChequeNum"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="cancelForm">
                    <input type="hidden" id="cancelLeafId">
                    <div class="mb-3">
                        <label class="form-label">Reason for cancellation <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="cancel_reason" name="cancel_reason" rows="3" required
                            placeholder="Provide a reason (minimum 5 characters)"></textarea>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger btn-sm d-flex align-items-center" id="confirmCancelBtn">
                            <span class="material-symbols-outlined" id="cancelIcon">cancel</span>
                            <span class="ms-1" id="cancelText">Confirm Cancel</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    $(function () {
        var bookId      = {{ $book->id }};
        var leavesUrl   = "{{ url('/ajax/cheque-book-leaves') }}/" + bookId;
        var currentFilter = '';

        // ── DataTable ──────────────────────────────────────────────────────────────
        var table = $('#leavesTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: leavesUrl,
                data: function(d) { if (currentFilter !== '') d.status = currentFilter; },
                dataSrc: 'data'
            },
            columns: [
                { data: null, render: function(d,t,r,m) { return m.row + 1; }, orderable: false },
                { data: 'cheque_number' },
                { data: null, render: function(d,t,r) {
                    return '<span class="badge ' + r.status_badge + '">' + r.status_label + '</span>';
                }},
                { data: 'locked_by',   defaultContent: '—' },
                { data: 'locked_at',   defaultContent: '—' },
                { data: 'used_by',     defaultContent: '—' },
                { data: 'used_at',     defaultContent: '—' },
                { data: 'used_for',    defaultContent: '—' },
                {
                    data: null, orderable: false, searchable: false,
                    render: function(d,t,r) {
                        var btns = '';
                        // Release lock — only for Locked (status=2)
                        if (r.current_status_id === 2) {
                            btns += '<button class="btn btn-xs btn-warning release-lock me-1 d-inline-flex align-items-center" ' +
                                'data-id="' + r.id + '" style="padding:2px 6px;" title="Release Lock">' +
                                '<span class="material-symbols-outlined" style="font-size:16px;">lock_open</span>' +
                                '<span class="ms-1 release-text">Release</span>' +
                                '<span class="spinner-border spinner-border-sm release-spin" style="display:none;"></span>' +
                                '</button>';
                        }
                        // Cancel — for Available (1) or Locked (2)
                        if (r.current_status_id === 1 || r.current_status_id === 2) {
                            btns += '<button class="btn btn-xs btn-danger cancel-leaf d-inline-flex align-items-center" ' +
                                'data-id="' + r.id + '" data-num="' + r.cheque_number + '" style="padding:2px 6px;" title="Cancel">' +
                                '<span class="material-symbols-outlined" style="font-size:16px;">cancel</span>' +
                                '</button>';
                        }
                        return btns || '—';
                    }
                }
            ],
            paging: true,
            info: true,
            lengthChange: true,
            lengthMenu: [[25,50,100,-1],[25,50,100,'All']],
            drawCallback: function() { updateStats(this.api()); }
        });

        // ── Update stat tiles ──────────────────────────────────────────────────────
        function updateStats(api) {
            var all  = api.rows().data().toArray();
            var count = function(arr, s) { return arr.filter(function(r){ return r.current_status_id === s; }).length; };
            $('#statAvailable').text(count(all, 1));
            $('#statLocked').text(count(all, 2));
            $('#statUsed').text(count(all, 3));
            $('#statCancelled').text(count(all, 4));
        }

        // ── Status filter tabs ─────────────────────────────────────────────────────
        $(document).on('click', '.filter-btn', function() {
            $('.filter-btn').removeClass('active');
            $(this).addClass('active');
            currentFilter = $(this).data('status').toString();
            table.ajax.reload(function() { updateStats(table); }, false);
        });

        // ── Release Lock ───────────────────────────────────────────────────────────
        $(document).on('click', '.release-lock', function() {
            if (!confirm('Release the lock on this cheque? It will become available again.')) return;
            var $btn  = $(this);
            var $text = $btn.find('.release-text');
            var $spin = $btn.find('.release-spin');
            $text.hide(); $spin.show(); $btn.prop('disabled', true);

            $.ajax({
                url: "{{ url('/ajax/cheque-books/leaves') }}/" + $btn.data('id') + "/release-lock",
                type: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function(res) {
                    $text.show(); $spin.hide(); $btn.prop('disabled', false);
                    if (res.success) {
                        toastr.success('Lock released');
                        table.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Could not release lock');
                    }
                },
                error: function(xhr) {
                    $text.show(); $spin.hide(); $btn.prop('disabled', false);
                    var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Error';
                    toastr.error(msg);
                }
            });
        });

        // ── Open Cancel Modal ──────────────────────────────────────────────────────
        $(document).on('click', '.cancel-leaf', function() {
            $('#cancelLeafId').val($(this).data('id'));
            $('#cancelChequeNum').text('#' + $(this).data('num'));
            $('#cancel_reason').val('');
            $('#cancelModal').modal('show');
        });

        // ── Submit Cancel ──────────────────────────────────────────────────────────
        $('#cancelForm').on('submit', function(e) {
            e.preventDefault();
            var leafId = $('#cancelLeafId').val();
            var $btn   = $('#confirmCancelBtn');
            var $icon  = $('#cancelIcon');
            var $text  = $('#cancelText');
            $icon.text('sync').addClass('spin');
            $text.text('Cancelling...');
            $btn.prop('disabled', true);

            $.ajax({
                url: "{{ url('/ajax/cheque-books/leaves') }}/" + leafId + "/cancel",
                type: 'POST',
                data: { _token: '{{ csrf_token() }}', cancel_reason: $('#cancel_reason').val() },
                success: function(res) {
                    $icon.text('cancel').removeClass('spin');
                    $text.text('Confirm Cancel');
                    $btn.prop('disabled', false);
                    if (res.success) {
                        toastr.success('Cheque cancelled');
                        $('#cancelModal').modal('hide');
                        table.ajax.reload(null, false);
                    } else {
                        toastr.error(res.error || 'Error');
                    }
                },
                error: function(xhr) {
                    $icon.text('cancel').removeClass('spin');
                    $text.text('Confirm Cancel');
                    $btn.prop('disabled', false);
                    var msg = 'Error';
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        msg = Object.values(xhr.responseJSON.errors).join('\n');
                    } else if (xhr.responseJSON && xhr.responseJSON.error) {
                        msg = xhr.responseJSON.error;
                    }
                    toastr.error(msg);
                }
            });
        });

        // ── Reset cancel modal ─────────────────────────────────────────────────────
        $('#cancelModal').on('hidden.bs.modal', function() {
            $('#cancelForm')[0].reset();
        });
    });
</script>
@endsection


