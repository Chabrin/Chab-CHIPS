@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Merge Details - {{ $merge->merge_reference }}</h4>
                        <div class="ms-auto d-flex gap-2">
                            @if((int)$merge->current_status_id === 2)
                            @permission('reverse_property_merge')
                            <button type="button" class="btn btn-danger btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#reverseMergeModal">
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">undo</span>
                                <span>Reverse Merge</span>
                            </button>
                            @endpermission
                            @endif
                            <a href="{{ route('property.merge.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        @if(session('success'))
                        <div class="alert alert-light-success alert-dismissible fade show" role="alert">
                            <strong>Success!</strong> {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif
                        @if(session('error'))
                        <div class="alert alert-light-danger alert-dismissible fade show" role="alert">
                            <strong>Error!</strong> {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif

                        <!-- Alert container for AJAX responses -->
                        <div id="alertContainer"></div>

                        <!-- Status Badge -->
                        <div class="row mb-4">
                            <div class="col-12">
                                @php
                                    $statusId = (int) $merge->current_status_id;
                                    $statusClass = match($statusId) {
                                        1 => 'warning',
                                        2 => 'success',
                                        3 => 'danger',
                                        default => 'secondary'
                                    };
                                    $statusIcon = match($statusId) {
                                        1 => 'pending',
                                        2 => 'check_circle',
                                        3 => 'undo',
                                        default => 'help'
                                    };
                                @endphp
                                <div class="alert alert-light-{{ $statusClass }} d-flex align-items-center">
                                    <span class="material-symbols-outlined me-2" style="font-size: 32px;">{{ $statusIcon }}</span>
                                    <div>
                                        <h5 class="mb-0">Status:
                                            <span class="badge bg-{{ $statusClass }}">{{ $merge->status_label }}</span>
                                        </h5>
                                        <small class="text-muted">Reference: {{ $merge->merge_reference }} | Type: {{ ucfirst($merge->merge_type) }} Merge</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Merge Information -->
                        <div class="row">
                            <!-- Left Column -->
                            <div class="col-lg-6">
                                <div class="card border mb-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">
                                            <span class="material-symbols-outlined me-2" style="font-size:18px; vertical-align:middle;">person</span>
                                            Client Information
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm mb-0">
                                            <tr>
                                                <td class="fw-bold" style="width: 40%;">Client Name:</td>
                                                <td>{{ $merge->client->names ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Client Ref:</td>
                                                <td>{{ $merge->client->reference_number ?? 'N/A' }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <div class="card border mb-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">
                                            <span class="material-symbols-outlined me-2" style="font-size:18px; vertical-align:middle;">home</span>
                                            Target Property
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm mb-0">
                                            <tr>
                                                <td class="fw-bold" style="width: 40%;">Property Name:</td>
                                                <td>{{ $merge->targetProperty->property_name ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Reference:</td>
                                                <td>{{ $merge->targetProperty->reference_number ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Merged Name:</td>
                                                <td><strong>{{ $merge->merged_property_name }}</strong></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column -->
                            <div class="col-lg-6">
                                <div class="card border mb-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">
                                            <span class="material-symbols-outlined me-2" style="font-size:18px; vertical-align:middle;">account_balance</span>
                                            Bank Account
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm mb-0">
                                            <tr>
                                                <td class="fw-bold" style="width: 40%;">Bank:</td>
                                                <td>{{ $merge->bankAccount->bank->bank_name ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Account Name:</td>
                                                <td>{{ $merge->bankAccount->account_name ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Account Number:</td>
                                                <td>{{ $merge->bankAccount->account_number ?? 'N/A' }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <div class="card border mb-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">
                                            <span class="material-symbols-outlined me-2" style="font-size:18px; vertical-align:middle;">info</span>
                                            Audit Information
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm mb-0">
                                            <tr>
                                                <td class="fw-bold" style="width: 40%;">Created By:</td>
                                                <td>{{ $merge->createdBy->name ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Created At:</td>
                                                <td>{{ $merge->date_time ? $merge->date_time->format('d M Y H:i') : ($merge->created_at ? $merge->created_at->format('d M Y H:i') : 'N/A') }}</td>
                                            </tr>
                                            @if($merge->approved_by)
                                            <tr>
                                                <td class="fw-bold">Approved By:</td>
                                                <td>{{ $merge->approvedBy->name ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold">Approved At:</td>
                                                <td>{{ $merge->approved_at ? $merge->approved_at->format('d M Y H:i') : 'N/A' }}</td>
                                            </tr>
                                            @endif
                                            @if($merge->reversed_by)
                                            <tr>
                                                <td class="fw-bold text-danger">Reversed By:</td>
                                                <td class="text-danger">{{ $merge->reversedBy->name ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold text-danger">Reversed At:</td>
                                                <td class="text-danger">{{ $merge->reversed_at ? $merge->reversed_at->format('d M Y H:i') : 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-bold text-danger">Reversal Reason:</td>
                                                <td class="text-danger">{{ $merge->reversal_reason }}</td>
                                            </tr>
                                            @endif
                                            @if($merge->notes)
                                            <tr>
                                                <td class="fw-bold">Notes:</td>
                                                <td>{{ $merge->notes }}</td>
                                            </tr>
                                            @endif
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Merged Items -->
                        <div class="card border mb-3">
                            <div class="card-header bg-light d-flex align-items-center">
                                <h6 class="mb-0">
                                    <span class="material-symbols-outlined me-2" style="font-size:18px; vertical-align:middle;">merge</span>
                                    Merged Items
                                </h6>
                                <span class="ms-auto badge bg-info">{{ $merge->items->count() }} items</span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Source Property</th>
                                                @if($merge->merge_type === 'unit')
                                                <th>Unit</th>
                                                @else
                                                <th>Units Moved</th>
                                                @endif
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($merge->items as $index => $item)
                                            <tr>
                                                <td>{{ $index + 1 }}</td>
                                                <td>{{ $item->sourceProperty->property_name ?? 'N/A' }}</td>
                                                @if($merge->merge_type === 'unit')
                                                <td>{{ $item->unit->unit_name ?? ($item->unit->unit_code ?? 'N/A') }}</td>
                                                @else
                                                <td>
                                                    @php
                                                        $unitCount = isset($item->original_data['units']) ? count($item->original_data['units']) : 0;
                                                    @endphp
                                                    <span class="badge bg-info">{{ $unitCount }} units</span>
                                                </td>
                                                @endif
                                                <td>{{ $item->date_time ? $item->date_time->format('d M Y H:i') : 'N/A' }}</td>
                                            </tr>
                                            @empty
                                            <tr>
                                                <td colspan="{{ $merge->merge_type === 'unit' ? 4 : 4 }}" class="text-center text-muted">No items found.</td>
                                            </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reverse Merge Modal -->
@if((int)$merge->current_status_id === 2)
<div class="modal fade" id="reverseMergeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <span class="material-symbols-outlined me-2" style="vertical-align:middle;">undo</span>
                    Reverse Merge
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>You are about to reverse merge <strong>{{ $merge->merge_reference }}</strong>.</p>
                <p class="text-muted">This will move all properties/units back to their original locations and reactivate any deactivated properties.</p>
                <div class="mb-3">
                    <label for="reversal_reason" class="form-label">Reason for Reversal <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="reversal_reason" rows="3" placeholder="Enter reason for reversing this merge..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmReverseBtn">
                    <span class="spinner-border spinner-border-sm me-2 d-none" id="reverseSpinner"></span>
                    Reverse Merge
                </button>
            </div>
        </div>
    </div>
</div>
@endif

<script>
$(document).ready(function() {
    @if((int)$merge->current_status_id === 2)
    // Reverse merge
    $('#confirmReverseBtn').on('click', function() {
        var reason = $('#reversal_reason').val().trim();
        if (!reason) {
            alert('Please provide a reason for the reversal.');
            return;
        }

        $('#reverseSpinner').removeClass('d-none');
        $(this).prop('disabled', true);

        $.ajax({
            url: "{{ route('property.merge.reverse', $merge->encrypted_id) }}",
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            data: { reversal_reason: reason },
            success: function(response) {
                $('#reverseMergeModal').modal('hide');
                if (response.success) {
                    var html = `
                        <div class="alert alert-light-success alert-dismissible fade show" role="alert">
                            <span class="material-symbols-outlined me-2" style="vertical-align:middle;">check_circle</span>
                            ${response.message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    `;
                    $('#alertContainer').html(html);
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showError(response.message || 'Reversal failed.');
                }
            },
            error: function(xhr) {
                $('#reverseMergeModal').modal('hide');
                var msg = xhr.responseJSON ? xhr.responseJSON.message : 'An error occurred during reversal.';
                showError(msg);
            },
            complete: function() {
                $('#reverseSpinner').addClass('d-none');
                $('#confirmReverseBtn').prop('disabled', false);
            }
        });
    });
    @endif

    function showError(message) {
        var html = `
            <div class="alert alert-light-danger alert-dismissible fade show" role="alert">
                <span class="material-symbols-outlined me-2" style="vertical-align:middle;">error</span>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        $('#alertContainer').html(html);
        $('html, body').animate({ scrollTop: 0 }, 300);
    }
});
</script>
@endsection