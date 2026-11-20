@extends('layouts.main')
@section('content')
@php
    $planBy = old('plan_by', 'monthly');
@endphp
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header card-no-border">
                        <h5 class="mb-0">Add a scheduled deduction</h5>
                        <small class="text-muted">Say how much is owed and how it is paid off. The schedule on the right shows every month before you save.</small>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('scheduled-deductions.store') }}" id="sdForm" enctype="multipart/form-data" autocomplete="off" novalidate>
                            @csrf
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="staff_id" class="form-label">Staff member <span class="text-danger">*</span></label>
                                    <select class="form-select" name="staff_id" id="staff_id" required>
                                        <option value="">Choose a staff member</option>
                                        @foreach($staff as $person)
                                            <option value="{{ $person->id }}" {{ (string) old('staff_id') === (string) $person->id ? 'selected' : '' }}>{{ $person->staff_number }} - {{ $person->full_names }}</option>
                                        @endforeach
                                    </select>
                                    @error('staff_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="type_id" class="form-label">Type of deduction <span class="text-danger">*</span></label>
                                    <select class="form-select" name="type_id" id="type_id" required>
                                        <option value="">Choose a type</option>
                                        @foreach($types as $type)
                                            <option value="{{ $type->id }}" {{ (string) old('type_id') === (string) $type->id ? 'selected' : '' }}>{{ $type->name }}{{ $type->is_company_loan ? ' (company loan)' : '' }}</option>
                                        @endforeach
                                    </select>
                                    @error('type_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                    <small class="text-muted d-block">HELB is not here: it is deducted by law, so it is on <a href="{{ route('deductions.helb') }}">HELB Loans</a>.</small>
                                    @permission('manage_deduction_types')
                                    <small class="text-muted">Need another type? Add it from <a href="{{ route('scheduled-deductions.index') }}">Deduction types</a>.</small>
                                    @endpermission
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">What it is for</label>
                                <input type="text" class="form-control" name="description" id="description" maxlength="255" value="{{ old('description') }}" placeholder="For example: advance for school fees, laptop damaged">
                                @error('description')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="total_amount" class="form-label">Total owed (KES) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0.01" class="form-control" name="total_amount" id="total_amount" required value="{{ old('total_amount') }}">
                                    @error('total_amount')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="start_month" class="form-label">First month it is taken <span class="text-danger">*</span></label>
                                    <input type="month" class="form-control" name="start_month" id="start_month" required value="{{ old('start_month', now()->format('Y-m')) }}">
                                    @error('start_month')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label d-block">Work out the schedule from</label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="plan_by" id="plan_monthly" value="monthly" {{ $planBy === 'monthly' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="plan_monthly">The monthly amount</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="plan_by" id="plan_months" value="months" {{ $planBy === 'months' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="plan_months">The number of months</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="plan_by" id="plan_end" value="end" {{ $planBy === 'end' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="plan_end">The month it should finish by</label>
                                </div>
                            </div>

                            <div class="mb-3" data-plan="monthly">
                                <label for="monthly_amount" class="form-label">Taken each month (KES)</label>
                                <input type="number" step="0.01" min="0.01" class="form-control" name="monthly_amount" id="monthly_amount" value="{{ old('monthly_amount') }}">
                                <small class="text-muted">The last month takes only what is left.</small>
                                @error('monthly_amount')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3" data-plan="months">
                                <label for="months" class="form-label">Number of months</label>
                                <input type="number" step="1" min="1" max="{{ \App\Services\DeductionSchedule::MAX_MONTHS }}" class="form-control" name="months" id="months" value="{{ old('months') }}">
                                @error('months')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3" data-plan="end">
                                <label for="end_month" class="form-label">Finish by</label>
                                <input type="month" class="form-control" name="end_month" id="end_month" value="{{ old('end_month') }}">
                                @error('end_month')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            @error('schedule')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

                            <div class="mb-3">
                                <label for="consent" class="form-label">Signed consent form (PDF)</label>
                                <input type="file" class="form-control" name="consent" id="consent" accept="application/pdf,.pdf">
                                <small class="text-muted">The staff member must agree in writing. Without the signed form the deduction is saved but on hold, and nothing is taken until you add it.</small>
                                @error('consent')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>

                            @include('partials.form-actions', ['form' => 'sdForm', 'buttonId' => 'saveDeduction', 'cancelUrl' => route('scheduled-deductions.index'), 'saveLabel' => 'Save deduction'])
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header card-no-border">
                        <h5 class="mb-0">Schedule</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-0" id="sdPlaceholder">Fill in the total owed and how it is paid to see the months.</p>
                        <div id="sdProblems" class="alert alert-danger py-2 d-none"></div>
                        <div id="sdNotes" class="alert alert-info py-2 d-none"></div>
                        <div id="sdSummary" class="mb-2 fw-semibold d-none"></div>
                        <div id="sdTableWrap" class="table-responsive d-none" style="max-height: 24rem; overflow-y: auto;">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr><th>#</th><th>Month</th><th class="text-end">Taken (KES)</th><th class="text-end">Still owed (KES)</th></tr>
                                </thead>
                                <tbody id="sdRows"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    (function () {
        var form = document.getElementById('sdForm');
        var previewUrl = @json(route('scheduled-deductions.preview'));
        var timer = null;
        var latest = 0;

        function money(n) {
            return Number(n).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function planBy() {
            var checked = form.querySelector('input[name="plan_by"]:checked');
            return checked ? checked.value : 'monthly';
        }

        function showPlanFields() {
            var by = planBy();
            form.querySelectorAll('[data-plan]').forEach(function (box) {
                box.classList.toggle('d-none', box.getAttribute('data-plan') !== by);
            });
        }

        function show(id, visible) {
            document.getElementById(id).classList.toggle('d-none', !visible);
        }

        function render(data) {
            var problems = document.getElementById('sdProblems');
            var notes = document.getElementById('sdNotes');
            problems.textContent = (data.problems || []).join(' ');
            notes.textContent = (data.notes || []).join(' ');
            show('sdProblems', (data.problems || []).length > 0);
            show('sdNotes', (data.notes || []).length > 0);
            show('sdPlaceholder', false);

            var rows = data.rows || [];
            show('sdTableWrap', rows.length > 0);
            show('sdSummary', rows.length > 0);
            if (!rows.length) { return; }

            document.getElementById('sdSummary').textContent = 'KES ' + money(data.monthly) + ' a month for ' + data.months + ' months, ending ' + data.end_month
                + (data.last_amount !== data.monthly ? '. The last month takes KES ' + money(data.last_amount) + '.' : '.');
            var body = document.getElementById('sdRows');
            body.innerHTML = '';
            rows.forEach(function (row) {
                var tr = document.createElement('tr');
                [row.n, row.label, money(row.amount), money(row.balance)].forEach(function (value, index) {
                    var td = document.createElement('td');
                    td.textContent = value;
                    if (index > 1) { td.className = 'text-end'; }
                    tr.appendChild(td);
                });
                body.appendChild(tr);
            });
        }

        function refresh() {
            var total = parseFloat(form.total_amount.value);
            if (!(total > 0)) {
                show('sdProblems', false); show('sdNotes', false); show('sdSummary', false); show('sdTableWrap', false); show('sdPlaceholder', true);
                return;
            }
            var by = planBy();
            if ((by === 'monthly' && !form.monthly_amount.value) || (by === 'months' && !form.months.value) || (by === 'end' && !form.end_month.value)) {
                show('sdProblems', false); show('sdNotes', false); show('sdSummary', false); show('sdTableWrap', false); show('sdPlaceholder', true);
                return;
            }
            var ticket = ++latest;
            var body = new FormData();
            ['_token', 'total_amount', 'start_month', 'plan_by', 'monthly_amount', 'months', 'end_month'].forEach(function (name) {
                if (form.elements[name]) { body.append(name, form.elements[name].type === 'radio' ? planBy() : form.elements[name].value); }
            });
            fetch(previewUrl, { method: 'POST', body: body, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (data) { if (data && ticket === latest) { render(data); } })
                .catch(function () {});
        }

        function later() {
            clearTimeout(timer);
            timer = setTimeout(refresh, 250);
        }

        // The staff list is long: make it searchable by name or staff number
        if (window.jQuery && jQuery.fn.select2) {
            jQuery('#staff_id').select2({ width: '100%', placeholder: 'Search by name or staff number' });
        }

        form.addEventListener('input', later);
        form.addEventListener('change', function () { showPlanFields(); later(); });
        showPlanFields();
        refresh();
    })();
</script>
@endsection
