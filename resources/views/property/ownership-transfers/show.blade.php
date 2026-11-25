@extends('layouts.main')
@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/ownership-transfer.css') }}?v={{ filemtime(public_path('assets/css/ownership-transfer.css')) }}">
@endsection
@section('content')
@php
    $isDirector = \App\Services\PropertyTransferAccess::isDirector(auth()->user());
    $canCreate = \App\Services\PropertyTransferAccess::allows(auth()->user(), 'create');
    $canReview = \App\Services\PropertyTransferAccess::allows(auth()->user(), 'review') && ($isDirector || (int) $transfer->created_by !== (int) auth()->id());
    $canApprove = \App\Services\PropertyTransferAccess::allows(auth()->user(), 'approve') && ($isDirector || (int) $transfer->reviewed_by !== (int) auth()->id());
    $canComplete = \App\Services\PropertyTransferAccess::allows(auth()->user(), 'execute');
    $isClosed = in_array($transfer->status, ['completed', 'cancelled']);
    $needsReview = in_array($transfer->status, ['draft', 'needs_review']);
    $blocked = count($snapshot['summary']['blockers']) > 0;
    $isToday = $transfer->effective_date->isSameDay(today());
    $dateMissed = $transfer->effective_date->lt(today()) && !$isClosed;
    $stage = match ($transfer->status) { 'reviewed' => 3, 'approved' => 4, 'completed' => 5, default => 2 };
    $statusLabels = ['draft' => 'Awaiting reconciliation', 'needs_review' => 'Fresh review required', 'reviewed' => 'Awaiting approval', 'approved' => 'Approved', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
    $returnLabels = ['each_separate' => 'Each charge separately', 'rent_separate_rest_combined' => 'Rent separately; other charges combined', 'all_combined' => 'All charges combined'];
    $nextTitle = match (true) {
        $transfer->status === 'completed' => 'Transfer completed',
        $transfer->status === 'cancelled' => 'Request cancelled',
        $dateMissed => 'Effective date has passed',
        $needsReview => 'Review and reconcile',
        $transfer->status === 'reviewed' => 'Approve the handover',
        $isToday => 'Complete the transfer today',
        default => 'Scheduled for '.$transfer->effective_date->format('d M Y'),
    };
@endphp
<div class="page-body ownership-transfer transfer-review" style="padding-top:10px">
    <div class="container-fluid">
        <header class="transfer-page-heading">
            <div><div class="transfer-reference">{{ $transfer->reference }} <span class="transfer-status transfer-status-{{ $transfer->status }}">{{ $statusLabels[$transfer->status] ?? ucfirst($transfer->status) }}</span></div><h1>{{ $property->property_name }}</h1><p>Ownership handover review · Property {{ $property->reference_number }}</p></div>
            <a class="btn btn-light" href="{{ route('property-transfers.index', ['property_id' => $property->id]) }}">Back to transfer history</a>
        </header>
        @include('property.ownership-transfers.messages')
        <div class="transfer-purpose"><strong>What this page does</strong><span>Check the handover position, record reconciliation, approve the new terms and complete ownership on the effective date. Creating or approving this request does not change the landlord yet.</span></div>
        @if($transfer->status !== 'cancelled')
        <ol class="transfer-progress" aria-label="Transfer progress">
            @foreach(['Request created', 'Reconciliation', 'Approval', 'Ownership completed'] as $label)
            <li class="{{ $loop->iteration < $stage ? 'is-done' : ($loop->iteration === $stage ? 'is-current' : '') }}" @if($loop->iteration === $stage) aria-current="step" @endif><span aria-hidden="true">{{ $loop->iteration < $stage ? '✓' : $loop->iteration }}</span><strong>{{ $label }}</strong></li>
            @endforeach
        </ol>
        @endif
        <section class="transfer-section" aria-label="Ownership handover overview">
            <div class="transfer-owner-grid">
                <div><span>Outgoing landlord</span><strong>{{ $transfer->fromClient->names }}</strong><small>{{ $transfer->fromClient->reference_number }}</small></div>
                <div><span>Incoming landlord</span><strong>{{ $transfer->toClient->names }}</strong><small>{{ $transfer->toClient->reference_number }}</small></div>
                <div><span>Effective date</span><strong>{{ $transfer->effective_date->format('d M Y') }}</strong><small>{{ $transfer->status === 'completed' ? 'Ownership updated' : 'First day of the month' }}</small></div>
            </div>
            <nav class="transfer-review-nav" aria-label="Review sections"><a href="#handover-position">Handover figures</a><a href="#transfer-agreements">SLA &amp; documents</a><a href="#transfer-decision">Next action</a><a href="#transfer-history">Approval history</a></nav>
        </section>
        <div class="transfer-review-layout">
            <main class="transfer-review-main">
                <section class="transfer-section" id="handover-position" aria-labelledby="handover-position-title">
                    <div class="transfer-section-heading"><div><h2 id="handover-position-title">Handover position</h2><p>{{ $transfer->status === 'completed' ? 'Figures frozen at completion.' : 'Current figures. Changes after review require a fresh reconciliation and approval.' }}</p></div></div>
                    <div class="transfer-section-body">
                        @if($blocked && !$isClosed)
                        <div class="transfer-blockers" role="alert"><strong>Resolve before proceeding</strong><ul>
                            @foreach($snapshot['summary']['blockers'] as $blocker)
                            <li>{{ $blocker }}</li>
                            @endforeach
                        </ul></div>
                        @elseif(!$isClosed)
                        <p class="transfer-clear">No blocking balances or pending items were found. Verify the schedules and payout instructions before confirming reconciliation.</p>
                        @endif
                        <div class="transfer-metrics">
                            <div><span>Outgoing ledger balance</span><strong>{{ number_format($snapshot['summary']['ledger_balance'], 2) }}</strong><small>Must be settled before handover</small></div>
                            <div><span>Outstanding tenant invoices</span><strong>{{ number_format($snapshot['summary']['tenant_arrears'], 2) }}</strong><small>{{ $snapshot['summary']['outstanding_invoices'] }} invoice(s) in the schedule</small></div>
                            <div><span>Receipts awaiting allocation</span><strong>{{ number_format($snapshot['summary']['unallocated_receipts'], 2) }}</strong><small>Reconcile against tenant credits</small></div>
                            <div><span>Continuing records</span><strong>{{ $snapshot['summary']['active_leases'] }} leases · {{ $snapshot['summary']['deposit_records'] }} deposits</strong><small>Tenant accounts and signed lease history continue</small></div>
                        </div>
                        <dl class="transfer-facts">
                            <div><dt>Arrears collection rights</dt><dd>{{ $transfer->arrears_policy === 'transfer' ? 'Incoming landlord' : 'Outgoing landlord' }}<small>{{ count($snapshot['eligible_invoice_ids']) }} outstanding invoice(s) belong to this outgoing landlord. Arrears retained by earlier owners stay with those owners.</small></dd></div>
                            <div><dt>Incoming payout account</dt><dd>{{ $snapshot['rows']['bank']['account_name'] }}<small>Account ending {{ substr($snapshot['rows']['bank']['account_number'], -4) }}</small></dd></div>
                        </dl>
                        <div class="transfer-schedules">@include('property.ownership-transfers.schedules')</div>
                    </div>
                </section>
                <section class="transfer-section" id="transfer-agreements" aria-labelledby="transfer-agreements-title">
                    <div class="transfer-section-heading"><div><h2 id="transfer-agreements-title">Service agreements &amp; documents</h2><p>Review the outgoing agreement and the terms proposed for the incoming landlord.</p></div></div>
                    <div class="transfer-section-body">
                        <div class="transfer-agreement-links">
                            <a href="{{ route('property-transfers.agreement', [$transfer, 'outgoing']) }}" class="transfer-agreement-link" target="_blank" rel="noopener" aria-label="View previous SLA (opens in a new tab)"><span>Outgoing agreement</span><strong>View previous SLA <span aria-hidden="true">↗</span></strong><small>Read the existing agreement and its billing items · Opens in a new tab</small></a>
                            @if($transfer->new_sla_id)
                            <a href="{{ route('property-transfers.agreement', [$transfer, 'incoming']) }}" class="transfer-agreement-link" target="_blank" rel="noopener" aria-label="View new approved SLA (opens in a new tab)"><span>Incoming agreement</span><strong>View new approved SLA <span aria-hidden="true">↗</span></strong><small>Created when this transfer was completed · Opens in a new tab</small></a>
                            @else
                            <div class="transfer-agreement-pending"><span>Incoming agreement</span><strong>Created at completion</strong><small>The terms below are approved before the new SLA is created.</small></div>
                            @endif
                        </div>
                        <dl class="transfer-facts transfer-facts-two">
                            <div><dt>New commission</dt><dd>{{ number_format((float) $transfer->terms['commission_percentage'], 2) }}%</dd></div>
                            <div><dt>New SLA duration</dt><dd>{{ $transfer->terms['years'] }} year(s)</dd></div>
                            <div><dt>VAT</dt><dd>{{ $transfer->terms['vat'] ?? 'No' }}{{ ($transfer->terms['vat'] ?? 'No') === 'Yes' ? ' · '.($transfer->terms['vat_per'] ?? 0).'%' : '' }}</dd></div>
                            <div><dt>Remittance day</dt><dd>{{ $transfer->terms['landlord_payment_date'] ?? 'Not set' }}</dd></div>
                            <div><dt>Returns layout</dt><dd>{{ $returnLabels[$transfer->terms['returns_combination'] ?? 'each_separate'] ?? 'Not set' }}</dd></div>
                            <div><dt>Registration fee</dt><dd>Not charged again</dd></div>
                        </dl>
                        <div class="transfer-reason"><h3>Reason &amp; handover instructions</h3><p>{{ $transfer->reason }}</p></div>
                        <h3>Supporting documents <span class="transfer-optional">Optional</span></h3>
                        <ul class="transfer-document-list">
                            @forelse($transfer->documents ?? [] as $index => $document)
                            <li><a href="{{ route('property-transfers.document', [$transfer, $index]) }}">{{ $document['name'] }}</a><span>Download</span></li>
                            @empty
                            <li class="transfer-empty">No supporting documents attached.</li>
                            @endforelse
                        </ul>
                    </div>
                </section>
                <section class="transfer-section" id="transfer-history" aria-labelledby="transfer-history-title">
                    <div class="transfer-section-heading"><div><h2 id="transfer-history-title">Approval history</h2><p>Every review, approval and ownership change is recorded here.</p></div></div>
                    <div class="transfer-history-list">
                        @forelse($transfer->events as $event)
                        <article><div><strong>{{ ucwords(str_replace('_', ' ', $event->action)) }}</strong><time>{{ $event->created_at->format('d M Y · H:i') }}</time></div><small>{{ $event->actor?->name ?? 'System' }}</small><p>{{ $event->notes }}</p></article>
                        @empty
                        <p class="transfer-empty">No actions recorded yet.</p>
                        @endforelse
                    </div>
                </section>
            </main>
            <aside class="transfer-decision-column">
                <section class="transfer-section transfer-decision" id="transfer-decision" aria-labelledby="transfer-decision-title">
                    <div class="transfer-section-heading"><div><p>Next action</p><h2 id="transfer-decision-title">{{ $nextTitle }}</h2></div></div>
                    <div class="transfer-section-body">
                        @if($transfer->status === 'completed')
                        <p>The outgoing account is closed for new activity on this property. The incoming landlord and new SLA are linked.</p>
                        @elseif($transfer->status === 'cancelled')
                        <p>This request is closed. Property ownership was not changed by this request.</p>
                        @elseif($dateMissed)
                        <p>The completion date was {{ $transfer->effective_date->format('d M Y') }}. Cancel this request and create a new one with a future first-of-month date.</p>
                        @elseif($needsReview)
                        <p>Check the ledger, invoices, deposits and payout instructions. Recording reconciliation confirms that you checked the handover position.</p>
                        @elseif($transfer->status === 'reviewed')
                        <p>Reconciliation is recorded. Review the notes and proposed SLA terms, then give final approval.</p>
                        @else
                        <p>{{ $isToday ? 'Check the figures and complete the handover before raising invoices for the incoming period.' : 'Approval is recorded. Ownership remains with the outgoing landlord until you complete the transfer on the effective date.' }}</p>
                        <p class="transfer-note">A reminder appears in the Director's notification bell on the effective date.</p>
                        @endif
                        @if($isDirector && !$isClosed)
                        <div class="transfer-director-note">You can reconcile and approve from this Director account. Each step is recorded under your name.</div>
                        @endif
                        @if($transfer->finance_notes)
                        <details class="transfer-review-notes"><summary>Recorded reconciliation notes</summary><p>{{ $transfer->finance_notes }}</p></details>
                        @endif
                        @if(!$isClosed && !$dateMissed)
                            @if(($needsReview && $canReview) || ($transfer->status === 'reviewed' && $canApprove) || ($transfer->status === 'approved' && $canComplete))
                            <form action="{{ route('property-transfers.decide', $transfer) }}" method="post" class="transfer-decision-form">
                                @csrf
                                @if($transfer->status !== 'approved')
                                <label for="notes">{{ $needsReview ? 'Reconciliation notes' : 'Approval notes' }} <span class="text-danger">*</span></label>
                                <textarea name="notes" id="notes" class="form-control" rows="3" minlength="10" maxlength="4000" placeholder="{{ $needsReview ? 'Summarise what you checked and any handover instructions.' : 'Record your decision on the handover and SLA terms.' }}" required>{{ old('notes') }}</textarea>
                                <small>At least 10 characters. Saved in approval history.</small>
                                @endif
                                @if($needsReview)
                                <label class="transfer-confirmation" for="confirmed"><input type="checkbox" name="confirmed" value="1" id="confirmed" @checked(old('confirmed')) required><span>I checked the final returns, ledger, expenses, tenant credits and deposits; confirmed deposit custody; and verified the payout account, recurring payments and SLA terms.</span></label>
                                <button name="action" value="review" class="btn btn-primary" @disabled($blocked)>{{ $isDirector ? 'Record reconciliation (Director)' : 'Record Finance review' }}</button>
                                @elseif($transfer->status === 'reviewed')
                                <button name="action" value="approve" class="btn btn-primary" @disabled($blocked)>Approve handover &amp; SLA terms</button>
                                @else
                                <button name="action" value="complete" class="btn btn-primary" @disabled(!$isToday || $blocked)>Complete ownership transfer</button>
                                <small>Available {{ $transfer->effective_date->format('d M Y') }}. The approved figures must still match.</small>
                                @endif
                                @if($blocked)
                                <p class="transfer-error">Resolve the items under Handover position before proceeding.</p>
                                @endif
                            </form>
                            @else
                            <p class="transfer-note">{{ $needsReview ? 'An authorized reviewer must record the reconciliation. A Director can review their own request.' : 'An authorized approver must complete the next step. Only a Director can approve their own reconciliation.' }}</p>
                            @endif
                        @endif
                        @if($transfer->snapshot)
                        <a class="btn btn-outline-primary transfer-statement-link" target="_blank" rel="noopener" href="{{ route('property-transfers.statement', $transfer) }}">Print / save handover statement</a>
                        @endif
                        @if(!$isClosed && $canCreate)
                        <details class="transfer-other-actions"><summary>Other actions</summary><p>Use a fresh review if figures change. Cancel only if this request should not proceed.</p>
                            <form action="{{ route('property-transfers.decide', $transfer) }}" method="post">
                                @csrf
                                <label for="management-notes">Reason <span class="text-danger">*</span></label>
                                <textarea id="management-notes" name="notes" class="form-control" rows="2" minlength="10" maxlength="4000" required>{{ old('notes') }}</textarea>
                                @if(in_array($transfer->status, ['reviewed', 'approved']))
                                <button name="action" value="reset" class="btn btn-outline-secondary">Request fresh review</button>
                                @endif
                                <button name="action" value="cancel" class="btn btn-outline-danger">Cancel request</button>
                            </form>
                        </details>
                        @endif
                        <p class="transfer-scope-note">Only this property's ownership changes. Other properties and historical statements remain intact. This action does not move money between bank accounts.</p>
                    </div>
                </section>
            </aside>
        </div>
    </div>
</div>
@endsection
