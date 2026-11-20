@extends('layouts.main')
@section('content')
@php
    $labels = \App\Models\ScheduledDeduction::STATUS_LABELS;
    $openCount = ($counts['on_hold'] ?? 0) + ($counts['active'] ?? 0) + ($counts['paused'] ?? 0);
    $tabs = ['open' => ['Open', $openCount]];
    foreach ($labels as $key => $label) {
        $tabs[$key] = [$label, $counts[$key] ?? 0];
    }
    $tabs['all'] = ['All', $counts->sum()];
@endphp
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center flex-wrap gap-2">
                        <div>
                            <h5 class="mb-0">Scheduled Deductions</h5>
                            <small class="text-muted">Money a staff member owes, taken a set amount a month until it is paid off. Nothing is taken until the signed consent form is added.</small>
                        </div>
                        <div class="ms-auto d-flex gap-2">
                            @permission('manage_deduction_types')
                            <button type="button" class="btn btn-outline-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#typesModal">
                                <span class="material-symbols-outlined">tune</span>
                                <span class="ms-1">Deduction types</span>
                            </button>
                            @endpermission
                            @permission('create_scheduled_deductions')
                            <a href="{{ route('scheduled-deductions.create') }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add deduction</span>
                            </a>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="{{ route('scheduled-deductions.index') }}" class="d-flex flex-wrap align-items-center gap-2 mb-3">
                            <label for="status" class="form-label mb-0">Show</label>
                            <select name="status" id="status" class="form-select form-select-sm" style="width:auto; min-width:14rem;" onchange="this.form.submit()">
                                @foreach($tabs as $key => [$label, $count])
                                    <option value="{{ $key }}" {{ $filter === $key ? 'selected' : '' }}>{{ $label }} ({{ $count }})</option>
                                @endforeach
                            </select>
                        </form>

                        @if($deductions->isNotEmpty())
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="sd-table">
                                <thead>
                                    <tr>
                                        <th>Staff</th>
                                        <th>Type</th>
                                        <th class="text-end">Total owed</th>
                                        <th class="text-end">Monthly</th>
                                        <th class="text-end">Balance</th>
                                        <th>Starts</th>
                                        <th>Ends</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($deductions as $deduction)
                                        <tr>
                                            <td>
                                                <strong>{{ $deduction->staff?->full_names ?? 'Unknown' }}</strong><br>
                                                <small class="text-muted">{{ $deduction->staff?->staff_number }}</small>
                                            </td>
                                            <td>
                                                {{ $deduction->type?->name }}
                                                @if($deduction->description)<br><small class="text-muted">{{ $deduction->description }}</small>@endif
                                            </td>
                                            <td class="text-end">{{ number_format((float) $deduction->total_amount, 2) }}</td>
                                            <td class="text-end">{{ number_format((float) $deduction->monthly_amount, 2) }}</td>
                                            <td class="text-end">{{ number_format((float) $deduction->balance, 2) }}</td>
                                            <td data-order="{{ $deduction->start_month?->format('Y-m') }}">{{ $deduction->start_month?->format('M Y') }}</td>
                                            <td data-order="{{ $deduction->end_month?->format('Y-m') }}">{{ $deduction->end_month?->format('M Y') }}</td>
                                            <td><span class="badge bg-{{ $deduction->status_colour }}">{{ $deduction->status_label }}</span></td>
                                            <td class="text-end"><a href="{{ route('scheduled-deductions.show', \Illuminate\Support\Facades\Crypt::encrypt($deduction->id)) }}" class="btn btn-outline-primary btn-sm">View</a></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                            <p class="text-muted mb-0">There are no deductions here yet.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('modals')
@permission('manage_deduction_types')
<div class="modal fade" id="typesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Deduction types</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">These are the choices when adding a deduction. A hidden type is not offered any more; deductions already made with it are not changed.</p>
                <table class="table table-sm align-middle">
                    <tbody>
                        @foreach($types as $type)
                            <tr>
                                <td>
                                    {{ $type->name }}
                                    @if($type->is_company_loan)<span class="badge bg-info ms-1">Company loan</span>@endif
                                    @unless($type->is_active)<span class="badge bg-secondary ms-1">Hidden</span>@endunless
                                </td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('scheduled-deductions.types.toggle', $type->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-secondary btn-sm">{{ $type->is_active ? 'Hide' : 'Show' }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <hr>
                <form method="POST" action="{{ route('scheduled-deductions.types.store') }}" autocomplete="off">
                    @csrf
                    <label for="type_name" class="form-label">Add a type</label>
                    <input type="text" class="form-control" id="type_name" name="name" maxlength="100" required value="{{ old('name') }}">
                    @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    <div class="form-check mt-2">
                        <input type="checkbox" class="form-check-input" id="type_company_loan" name="is_company_loan" value="1">
                        <label class="form-check-label" for="type_company_loan">A loan from the company. The law limits its monthly repayment to half of what is left of the wages after the other deductions.</label>
                    </div>
                    <div class="d-flex justify-content-between mt-3">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add type</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endpermission
@endsection

@section('scripts')
<script>
    $(function () {
        if ($.fn.DataTable && $('#sd-table tbody tr').length) {
            $('#sd-table').DataTable({ order: [], pageLength: 25, columnDefs: [{ orderable: false, targets: -1 }] });
        }
        @if($errors->has('name'))
            var typesModal = document.getElementById('typesModal');
            if (typesModal && window.bootstrap) { new bootstrap.Modal(typesModal).show(); }
        @endif
    });
</script>
@endsection
