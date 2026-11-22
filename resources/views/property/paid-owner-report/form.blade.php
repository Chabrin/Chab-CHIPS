@extends('layouts.auth')

@section('title', 'Tenant paid you directly')

@section('content')
<div style="min-height:100vh; background:#f5f6fa; padding:24px 0;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card card-no-border">
                    <div class="card-body py-4">
                        <h4 class="mb-1">Tenant paid you directly</h4>
                        <p class="text-muted mb-3" style="font-size:0.9rem;">
                            Dear {{ $landlord }}, if a tenant paid you rent directly instead of through us, tell us here. We will check it and make sure that
                            rent is not paid out to you a second time. Nothing changes on the tenant's or your account until we have checked it.
                        </p>

                        @if($errors->any())
                        <div class="alert alert-danger py-2" style="font-size:0.85rem;">
                            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                        </div>
                        @endif

                        @if(!count($options))
                        <div class="alert alert-info mb-0">There is no unpaid rent on your properties at the moment, so there is nothing to report.</div>
                        @else
                        <form method="POST" action="{{ $submitUrl }}" enctype="multipart/form-data" id="reportForm">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Property <span class="text-danger">*</span></label>
                                <select class="form-select" id="property"></select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Unit and tenant <span class="text-danger">*</span></label>
                                <select class="form-select" id="unit" disabled></select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Which rent did the tenant pay you for? <span class="text-danger">*</span></label>
                                <select class="form-select" id="invoice" name="invoice_id" disabled required></select>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-sm-6">
                                    <label class="form-label">Amount the tenant paid you (KSh) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="amount" id="amount" step="0.01" min="1" value="{{ old('amount') }}" required>
                                    <div class="form-text" id="amountHint"></div>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Date the tenant paid you <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="paid_on" max="{{ date('Y-m-d') }}" value="{{ old('paid_on') }}" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Payment reference (M-Pesa code, cheque number or bank reference), if you have one</label>
                                <input type="text" class="form-control" name="reference" maxlength="100" value="{{ old('reference') }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Proof <span class="text-danger">*</span> (a PDF of the receipt, the M-Pesa or bank statement, or your signed acknowledgement that the tenant paid you)</label>
                                <input type="file" class="form-control" name="proof" accept="application/pdf" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Anything else we should know</label>
                                <textarea class="form-control" name="note" rows="2" maxlength="500">{{ old('note') }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Send to Chabrin</button>
                        </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var options = @json($options);
    var propertySel = document.getElementById('property');
    var unitSel = document.getElementById('unit');
    var invoiceSel = document.getElementById('invoice');
    var amount = document.getElementById('amount');
    var hint = document.getElementById('amountHint');
    if (!propertySel) { return; }

    function fill(select, placeholder, items, label, value) {
        select.innerHTML = '';
        var first = document.createElement('option');
        first.value = ''; first.textContent = placeholder;
        select.appendChild(first);
        items.forEach(function (item, index) {
            var o = document.createElement('option');
            o.value = value ? value(item) : index; o.textContent = label(item);
            select.appendChild(o);
        });
        select.disabled = !items.length;
    }
    function money(n) { return Number(n).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    fill(propertySel, 'Choose the property', options, function (p) { return p.name; });

    propertySel.addEventListener('change', function () {
        var p = options[this.value];
        fill(unitSel, 'Choose the unit', p ? p.units : [], function (u) { return u.name + ' - ' + (u.tenant || 'tenant'); });
        fill(invoiceSel, 'Choose the unit first', [], function () { return ''; });
        hint.textContent = '';
    });
    unitSel.addEventListener('change', function () {
        var p = options[propertySel.value];
        var u = p ? p.units[this.value] : null;
        fill(invoiceSel, 'Choose the rent', u ? u.invoices : [], function (i) { return (i.narration || i.number) + ' - KSh ' + money(i.balance) + ' unpaid'; }, function (i) { return i.id; });
        hint.textContent = '';
    });
    invoiceSel.addEventListener('change', function () {
        var p = options[propertySel.value];
        var u = p ? p.units[unitSel.value] : null;
        var id = parseInt(this.value, 10);
        var invoice = u ? u.invoices.filter(function (i) { return i.id === id; })[0] : null;
        if (invoice) {
            amount.max = invoice.balance;
            amount.value = invoice.balance;
            hint.textContent = 'Up to KSh ' + money(invoice.balance) + ' is still unpaid on this rent.';
        } else {
            hint.textContent = '';
        }
    });
})();
</script>
@endpush
