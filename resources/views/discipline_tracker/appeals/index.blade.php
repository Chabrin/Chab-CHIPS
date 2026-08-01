@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Discipline Appeals</h4>
                        <div class="ms-auto">
                            <a href="{{ route('discipline.tracker.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to Cases</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Case Number</th>
                                        <th>Employee</th>
                                        <th>Original Decision</th>
                                        <th>Grounds</th>
                                        <th>Filed</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($appeals as $appeal)
                                    <tr>
                                        <td><strong>{{ $appeal->case->case_number }}</strong></td>
                                        <td>{{ $appeal->case->staff->full_names ?? '-' }}</td>
                                        <td>{{ $appeal->outcome->decision ?? '-' }}</td>
                                        <td>{{ Str::limit($appeal->grounds, 80) }}</td>
                                        <td>{{ $appeal->filed_at->format('d M Y, h:i A') }}</td>
                                        <td>
                                            @php
                                                $statusColor = ['Pending' => 'warning', 'Upheld' => 'success', 'Rejected' => 'secondary', 'Withdrawn' => 'secondary'][$appeal->status] ?? 'secondary';
                                            @endphp
                                            <span class="badge bg-{{ $statusColor }}">{{ $appeal->status }}</span>
                                        </td>
                                        <td>
                                            @if($appeal->status === 'Pending')
                                                <button type="button" class="btn btn-sm btn-primary" onclick="openDecideAppealModal({{ $appeal->id }}, '{{ $appeal->case->case_number }}')">
                                                    Decide
                                                </button>
                                            @else
                                                <small class="text-muted">
                                                    {{ $appeal->decision_action }} by {{ $appeal->reviewedBy->name ?? '-' }}
                                                    on {{ $appeal->reviewed_at ? $appeal->reviewed_at->format('d M Y') : '-' }}
                                                </small>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="7" class="text-center text-muted">No appeals filed yet.</td></tr>
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

<!-- Decide Appeal Modal -->
<div class="modal fade" id="decideAppealModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Decide Appeal — <span id="decide_case_number"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="decideAppealForm">
                @csrf
                <input type="hidden" name="appeal_id" id="decide_appeal_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="decision_action" class="form-label">Decision <span class="text-danger">*</span></label>
                        <select class="form-select" name="decision_action" id="decision_action" required>
                            <option value="">Select...</option>
                            <option value="Uphold Original Decision">Uphold Original Decision</option>
                            <option value="Overturn Decision">Overturn Decision</option>
                            <option value="Modify Decision">Modify Decision</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="review_notes" class="form-label">Review Notes <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="review_notes" id="review_notes" rows="4" required placeholder="Explain the basis for this decision..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Record Decision</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openDecideAppealModal(appealId, caseNumber) {
    $('#decide_appeal_id').val(appealId);
    $('#decide_case_number').text(caseNumber);
    $('#decideAppealModal').modal('show');
}

$(document).ready(function() {
    $('#decideAppealForm').on('submit', function(e) {
        e.preventDefault();
        let appealId = $('#decide_appeal_id').val();
        $.ajax({
            url: "{{ url('/discipline/tracker/appeals') }}/" + appealId + "/decide",
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    setTimeout(function() { location.reload(); }, 1000);
                }
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.error || 'Failed to record appeal decision');
            }
        });
    });
});
</script>
@endsection
