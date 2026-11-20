@extends('layouts.main')
@section('content')
@php
    use App\Models\ScheduledDeduction as SD;
    $taken = $schedule['taken'];
    $takenTotal = (float) $taken->sum('amount');
    $open = $deduction->isOpen();
@endphp
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center flex-wrap gap-2">
                        <div>
                            <h5 class="mb-0">{{ $deduction->type?->name }} for {{ $deduction->staff?->full_names ?? 'Unknown' }}
                                <span class="badge bg-{{ $deduction->status_colour }} ms-2">{{ $deduction->status_label }}</span>
                            </h5>
                            <small class="text-muted">
                                {{ $deduction->staff?->staff_number }}
                                @if($deduction->description) &middot; {{ $deduction->description }} @endif
                                &middot; added {{ $deduction->created_at?->format('d M Y') }}{{ $deduction->creator ? ' by ' . $deduction->creator->name : '' }}
                            </small>
                        </div>
                        <div class="ms-auto d-flex flex-wrap gap-2">
                            <a href="{{ route('scheduled-deductions.index') }}" class="btn btn-light btn-sm">Back to the list</a>
                            @if($open)
                                @permission('update_scheduled_deductions')
                                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#consentModal">{{ $deduction->hasConsent() ? 'Replace consent form' : 'Add consent form' }}</button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#monthlyModal">Change monthly amount</button>
                                    @if($deduction->status === SD::ACTIVE)
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#pauseModal">Pause</button>
                                    @elseif($deduction->status === SD::PAUSED)
                                        <form method="POST" action="{{ route('scheduled-deductions.resume', $encryptedId) }}" class="d-inline">@csrf
                                            <button type="submit" class="btn btn-outline-success btn-sm">Resume</button>
                                        </form>
                                    @endif
                                @endpermission
                                @permission('close_scheduled_deductions')
                                    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#closeModal">Close early</button>
                                @endpermission
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        @if($deduction->status === SD::ON_HOLD)
                            <div class="alert alert-warning py-2">
                                <strong>On hold.</strong> Nothing is taken from the pay until the signed consent form is added.
                            </div>
                        @elseif($deduction->status === SD::PAUSED)
                            <div class="alert alert-secondary py-2">
                                <strong>Paused{{ $deduction->paused_at ? ' on ' . $deduction->paused_at->format('d M Y') : '' }}.</strong> Nothing is taken until it is resumed. Reason: {{ $deduction->pause_reason }}
                            </div>
                        @elseif($deduction->status === SD::CLOSED)
                            <div class="alert alert-dark py-2">
                                <strong>Closed early{{ $deduction->closed_at ? ' on ' . $deduction->closed_at->format('d M Y') : '' }}.</strong> KES {{ number_format((float) $deduction->balance, 2) }} was still owed. Reason: {{ $deduction->close_reason }}
                            </div>
                        @elseif($deduction->status === SD::COMPLETED)
                            <div class="alert alert-primary py-2"><strong>Paid off.</strong> Nothing more is taken.</div>
                        @endif

                        @if(!empty($schedule['problems']))
                            <div class="alert alert-danger py-2">{{ implode(' ', $schedule['problems']) }}</div>
                        @endif

                        <div class="row g-2">
                            @foreach([
                                ['Total owed', 'KES ' . number_format((float) $deduction->total_amount, 2)],
                                ['Taken each month', 'KES ' . number_format((float) $deduction->monthly_amount, 2)],
                                ['Taken so far', 'KES ' . number_format($takenTotal, 2)],
                                ['Still owed', 'KES ' . number_format((float) $deduction->balance, 2)],
                                ['Months left', $open ? $schedule['months_left'] : 0],
                                ['Last month', $open && $schedule['end_month'] ? \Carbon\Carbon::parse($schedule['end_month'] . '-01')->format('M Y') : '-'],
                            ] as [$label, $value])
                                <div class="col-6 col-md-4 col-xl-2">
                                    <div class="border rounded p-2 h-100" style="background:#fff;color:#24352c;">
                                        <div class="small text-muted text-uppercase">{{ $label }}</div>
                                        <div class="fw-semibold">{{ $value }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header card-no-border"><h5 class="mb-0">Schedule</h5></div>
                    <div class="card-body">
                        @if($taken->isEmpty() && empty($schedule['rows']))
                            <p class="text-muted mb-0">Nothing is left to take.</p>
                        @else
                            <div class="table-responsive" style="max-height: 28rem; overflow-y: auto;">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr><th>Month</th><th class="text-end">Amount (KES)</th><th class="text-end">Still owed after (KES)</th><th>State</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach($taken as $row)
                                            <tr>
                                                <td>{{ $row->deduction_date->format('M Y') }}</td>
                                                <td class="text-end">{{ number_format((float) $row->amount, 2) }}</td>
                                                <td class="text-end">{{ number_format((float) $row->balance, 2) }}</td>
                                                <td><span class="badge bg-success">Taken</span></td>
                                            </tr>
                                        @endforeach
                                        @foreach($schedule['rows'] as $row)
                                            <tr>
                                                <td>{{ $row['label'] }}</td>
                                                <td class="text-end">{{ number_format($row['amount'], 2) }}</td>
                                                <td class="text-end">{{ number_format($row['balance'], 2) }}</td>
                                                <td><span class="badge bg-light text-dark border" style="color:#24352c !important;">To come</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if($deduction->status !== SD::ACTIVE && $open)
                                <small class="text-muted d-block mt-2">These months are what is left to take once the deduction is active.</small>
                            @endif
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card mb-3">
                    <div class="card-header card-no-border"><h5 class="mb-0">Signed consent form</h5></div>
                    <div class="card-body">
                        @if($deduction->hasConsent())
                            <p class="mb-1"><strong>{{ $deduction->consent_name }}</strong></p>
                            <p class="text-muted small mb-2">Added {{ $deduction->consent_at?->format('d M Y H:i') }}{{ $deduction->consenter ? ' by ' . $deduction->consenter->name : '' }}</p>
                            <a href="{{ route('scheduled-deductions.consent.download', $encryptedId) }}" target="_blank" class="btn btn-outline-primary btn-sm">View the form</a>
                        @else
                            <p class="text-muted mb-0">No signed form yet. The deduction stays on hold until one is added.</p>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-header card-no-border"><h5 class="mb-0">History</h5></div>
                    <div class="card-body">
                        @forelse($events as $event)
                            <div class="border-bottom pb-2 mb-2">
                                <div class="d-flex justify-content-between">
                                    <strong>{{ $event->label }}</strong>
                                    <small class="text-muted">{{ $event->created_at?->format('d M Y H:i') }}</small>
                                </div>
                                @if($event->details)<div class="small">{{ $event->details }}</div>@endif
                                @if($event->reason)<div class="small">Reason: {{ $event->reason }}</div>@endif
                                <div class="small text-muted">{{ $event->user?->name ?? 'The system' }}</div>
                            </div>
                        @empty
                            <p class="text-muted mb-0">Nothing has been done to this deduction yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('modals')
@if($open)
@permission('update_scheduled_deductions')
<div class="modal fade" id="consentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md"><div class="modal-content">
        <form method="POST" action="{{ route('scheduled-deductions.consent.store', $encryptedId) }}" enctype="multipart/form-data">
            @csrf
            <div class="modal-header"><h5 class="modal-title">{{ $deduction->hasConsent() ? 'Replace the consent form' : 'Add the consent form' }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <label for="consent_file" class="form-label">Signed consent form (PDF) <span class="text-danger">*</span></label>
                <input type="file" class="form-control" name="consent" id="consent_file" accept="application/pdf,.pdf" required>
                @error('consent')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                @if($deduction->status === SD::ON_HOLD)<small class="text-muted">Once it is saved the deduction becomes active.</small>@endif
                @if($deduction->hasConsent())<small class="text-muted d-block">The earlier file is kept and named in the history.</small>@endif
            </div>
            <div class="modal-footer justify-content-between"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save form</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="monthlyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md"><div class="modal-content">
        <form method="POST" action="{{ route('scheduled-deductions.monthly', $encryptedId) }}">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Change the monthly amount</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="small text-muted">KES {{ number_format((float) $deduction->balance, 2) }} is still owed. The months left are worked out again from the new amount.</p>
                <label for="new_monthly" class="form-label">New amount each month (KES) <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0.01" class="form-control" name="monthly_amount" id="new_monthly" required value="{{ old('monthly_amount', $deduction->monthly_amount) }}">
                @error('monthly_amount')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                <label for="monthly_reason" class="form-label mt-3">Reason <span class="text-danger">*</span></label>
                <textarea class="form-control" name="reason" id="monthly_reason" rows="2" required minlength="5" maxlength="1000"></textarea>
            </div>
            <div class="modal-footer justify-content-between"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div></div>
</div>

@if($deduction->status === SD::ACTIVE)
<div class="modal fade" id="pauseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md"><div class="modal-content">
        <form method="POST" action="{{ route('scheduled-deductions.pause', $encryptedId) }}">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Pause this deduction</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="small text-muted">Nothing is taken while it is paused. The months paused are not made up later: the deduction simply finishes later.</p>
                <label for="pause_reason" class="form-label">Reason <span class="text-danger">*</span></label>
                <textarea class="form-control" name="reason" id="pause_reason" rows="2" required minlength="5" maxlength="1000"></textarea>
            </div>
            <div class="modal-footer justify-content-between"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-secondary">Pause</button></div>
        </form>
    </div></div>
</div>
@endif
@endpermission

@permission('close_scheduled_deductions')
<div class="modal fade" id="closeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md"><div class="modal-content">
        <form method="POST" action="{{ route('scheduled-deductions.close', $encryptedId) }}">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Close this deduction early</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="small text-muted">Nothing more is taken and it cannot be opened again. KES {{ number_format((float) $deduction->balance, 2) }} that is still owed stays on the record.</p>
                <label for="close_reason" class="form-label">Reason <span class="text-danger">*</span></label>
                <textarea class="form-control" name="reason" id="close_reason" rows="2" required minlength="5" maxlength="1000"></textarea>
                @error('reason')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="modal-footer justify-content-between"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Close deduction</button></div>
        </form>
    </div></div>
</div>
@endpermission
@endif
@endsection
