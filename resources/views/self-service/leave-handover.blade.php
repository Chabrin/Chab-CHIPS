@extends('layouts.main')

@section('content')
<div class="page-body pt-3">
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-xl-9">
                <div class="card">
                    <div class="card-header card-no-border d-flex justify-content-between align-items-center gap-3">
                        <div>
                            <small class="text-muted">WORK HANDOVER</small>
                            <h4 class="mb-0 mt-1">Handover assigned to you</h4>
                        </div>
                        <a href="{{ url('/dashboard') }}" class="btn btn-light btn-sm">Back to dashboard</a>
                    </div>
                    <div class="card-body pt-0">
                        @if($handover['status'] === 5)
                            <div class="alert alert-success">The leave has been approved. Review the work and items below for the leave period.</div>
                        @elseif($handover['status'] === 41)
                            <div class="alert alert-secondary">The leave application was rejected. This handover is not active.</div>
                        @else
                            <div class="alert alert-info">This handover is proposed. The leave is still awaiting approval.</div>
                        @endif
                        <div class="row g-3 mb-4">
                            <div class="col-sm-6"><div class="text-muted small">Handed over by</div><strong>{{ $handover['employee_name'] }}</strong></div>
                            <div class="col-sm-6"><div class="text-muted small">Assigned to</div><strong>{{ $handover['recipient_name'] }}</strong></div>
                            <div class="col-12"><div class="text-muted small">Leave period</div>{{ $handover['start_date'] }} — {{ $handover['end_date'] }}</div>
                        </div>
                        <h6>Work and items to hand over</h6>
                        <div class="border rounded bg-light p-3" style="white-space:pre-wrap;overflow-wrap:anywhere;">{{ $handover['notes'] ?? 'No handover notes are currently recorded.' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
