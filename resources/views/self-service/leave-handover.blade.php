@extends('layouts.main')

@section('styles')
<style>
#leave-relief-details .relief-header { flex-wrap: wrap; padding-bottom: 24px; }
#leave-relief-details .relief-header h4 { line-height: 1.35; }
#leave-relief-details .relief-summary { padding: 20px; background: #f4f7fb; border: 1px solid #dbe3ed; border-radius: 10px; color: #24364b; }
#leave-relief-details .relief-section { margin-top: 28px; }
#leave-relief-details .relief-section h6 { margin-bottom: 12px; }
#leave-relief-details .relief-notes { padding: 22px; border: 1px solid #cbd7e4; border-left: 4px solid #3498db; border-radius: 8px; background: #f8fafc; color: #24364b; font-size: 16px; line-height: 1.75; white-space: pre-wrap; overflow-wrap: anywhere; }
#leave-relief-details .relief-duties { min-height: 112px; }
#leave-relief-details .relief-file { padding: 14px 16px; border: 1px solid #dbe3ed; border-radius: 8px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; }
#leave-relief-details .relief-file-name { flex: 1 1 240px; min-width: 0; overflow-wrap: anywhere; }
#leave-relief-details .relief-file a { flex-shrink: 0; }
#leave-relief-details .alert-info { background: #eef6ff; border-color: #c9dff5; color: #214c72; line-height: 1.6; }
#leave-relief-details .relief-receipt { border-top: 1px solid #dbe3ed; padding-top: 24px; }
body.dark-only #leave-relief-details .relief-notes,
body.dark-only #leave-relief-details .relief-summary { background: #182636; color: #edf3fa; border-color: #496078; }
body.dark-only #leave-relief-details .relief-file,
body.dark-only #leave-relief-details .relief-receipt { border-color: #496078; }
@media (max-width: 575px) {
    #leave-relief-details .relief-notes, #leave-relief-details .relief-summary { padding: 16px; }
    #leave-relief-details .relief-header h4 { font-size: 21px; }
}
</style>
@endsection

@section('content')
<div class="page-body pt-3" id="leave-relief-details">
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-xl-10">
                <div class="card">
                    <div class="card-header card-no-border d-flex justify-content-between align-items-center gap-3 relief-header">
                        <div>
                            <small class="text-muted">RELIEF ARRANGEMENTS</small>
                            <h4 class="mb-0 mt-1">Relief arrangements assigned to you</h4>
                        </div>
                        <a href="{{ url('/dashboard') }}" class="btn btn-light btn-sm">Back to dashboard</a>
                    </div>
                    <div class="card-body pt-0">
                        @if($handover['status'] === 5)
                            <div class="alert alert-success">The leave has been approved. Review the work and items below for the leave period.</div>
                        @elseif($handover['status'] === 41)
                            <div class="alert alert-secondary">The leave application was rejected. These relief arrangements are not active.</div>
                        @elseif($handover['status'] === 24)
                            <div class="alert alert-info">The leave is pending HR final approval. These relief arrangements are proposed until final approval.</div>
                        @else
                            <div class="alert alert-info">These relief arrangements are proposed. The leave is still awaiting approval.</div>
                        @endif
                        <div class="row g-3 mx-0 relief-summary">
                            <div class="col-sm-6"><div class="text-muted small">Handed over by</div><strong>{{ $handover['employee_name'] }}</strong></div>
                            <div class="col-sm-6"><div class="text-muted small">Assigned to</div><strong>{{ $handover['recipient_name'] }}</strong></div>
                            <div class="col-12"><div class="text-muted small">Leave period</div>{{ $handover['start_date'] }} — {{ $handover['end_date'] }}</div>
                        </div>
                        <section class="relief-section" aria-labelledby="relief-duties-heading">
                            <h6 id="relief-duties-heading">Relief duties and arrangements</h6>
                            <div class="relief-notes relief-duties">{{ $handover['notes'] ?? 'No handover notes are currently recorded.' }}</div>
                        </section>
                        @if(count($handover['attachments']))
                            <section class="relief-section" aria-labelledby="relief-files-heading">
                            <h6 id="relief-files-heading">Relief attachments</h6>
                            @foreach($handover['attachments'] as $file)
                                <div class="relief-file mb-2">
                                    <span class="relief-file-name">{{ $file['name'] }} <small class="text-muted">({{ number_format($file['size'] / 1024, 1) }} KB)</small></span>
                                    <a href="{{ $file['url'] }}" class="btn btn-light btn-sm">Download</a>
                                </div>
                            @endforeach
                            </section>
                        @endif
                        <div class="relief-section relief-receipt">
                            <h6>Acknowledge receipt</h6>
                            <p class="text-muted small">Confirm that you have received and reviewed the relief notes and files. This is separate from supervisor and HR leave approval.</p>
                            @if($handover['acknowledged_at'])
                                <div class="alert alert-success">You acknowledged these relief arrangements on {{ $handover['acknowledged_at'] }}. The applicant, supervisor and HR can see this.</div>
                                @if($handover['acknowledgement_note'])
                                    <div class="relief-notes">{{ $handover['acknowledgement_note'] }}</div>
                                @endif
                            @elseif(in_array($handover['status'], [1, 24, 5], true))
                                @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
                                <form method="POST" action="{{ route('leave-handover.acknowledge', $handover['id']) }}">
                                    @csrf
                                    <input type="hidden" name="revision" value="{{ $handover['revision'] }}">
                                    <label class="form-label" for="handover_acknowledgement_note">Acknowledgement comment <span class="text-muted small">(optional)</span></label>
                                    <textarea class="form-control mb-3" name="note" id="handover_acknowledgement_note" rows="3" maxlength="2000" placeholder="For example, confirm receipt or note a question about the handover.">{{ old('note') }}</textarea>
                                    <button type="submit" class="btn btn-primary">Acknowledge receipt of relief arrangements</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
