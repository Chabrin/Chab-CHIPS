@if($application->handover_staff_id)
    <div class="mt-3">
        @if($application->handover_acknowledged_at)
            <div class="alert alert-success py-2 mb-2">
                <strong>Receipt acknowledged</strong> by {{ $application->handoverEmployee?->full_names }}
                on {{ $application->handover_acknowledged_at->format('d M Y H:i') }}.
            </div>
            @if($application->handover_acknowledgement_note)
                <div class="mb-2" style="white-space:pre-wrap;overflow-wrap:anywhere;">{{ $application->handover_acknowledgement_note }}</div>
            @endif
        @else
            <p class="text-muted mb-2">Awaiting acknowledgement of receipt from the selected colleague.</p>
        @endif
        <small class="text-muted d-block mb-2">Acknowledgement confirms receipt of the handover; leave approval is a separate process.</small>
        @foreach(app(\App\Services\Leave\LeaveHandoverService::class)->attachments($application) as $file)
            <div class="mb-2"><a href="{{ $file['url'] }}" class="text-primary">Download {{ $file['name'] }}</a> <small class="text-muted">({{ number_format($file['size'] / 1024, 1) }} KB)</small></div>
        @endforeach
    </div>
@endif
