<div class="return-statement">
    <div class="table-responsive border rounded">
        <table class="table table-sm align-middle mb-0 statement-table">
            <colgroup>
                <col style="width:50px"><col style="width:150px">
                @foreach(range(1, 10) as $column)<col style="width:90px">@endforeach
                <col style="width:220px">
            </colgroup>
            <thead><tr>
                @foreach(\App\Services\ReturnStatementService::COLUMNS as $key => $label)
                <th class="{{ !in_array($key, ['unit_code','tenant_name','remarks']) ? 'text-end' : '' }}">{{ $label }}</th>
                @endforeach
            </tr></thead>
            <tbody>
                @forelse($tenantRows as $row)
                <tr>
                    @foreach(\App\Services\ReturnStatementService::COLUMNS as $key => $label)
                        @if($key === 'remarks')
                        <td>
                            <form class="statement-remarks-form" action="{{ route('returns.remarks', ['id' => encrypt($return->id), 'lineId' => encrypt($row['remarks_line_id'])]) }}" method="post">
                                <label class="visually-hidden">Remarks for {{ $row['tenant_name'] }}, room {{ $row['unit_code'] }}</label>
                                <textarea class="form-control form-control-sm statement-remarks" name="remarks" rows="1" maxlength="{{ \App\Services\ReturnStatementService::REMARKS_LIMIT }}" title="Up to {{ \App\Services\ReturnStatementService::REMARKS_LIMIT }} characters" aria-label="Remarks for {{ $row['tenant_name'] }}, room {{ $row['unit_code'] }}">{{ $row['remarks'] }}</textarea>
                                <input type="hidden" name="expected_remarks" value="{{ $row['remarks'] }}">
                                <button type="submit" class="btn btn-outline-primary btn-sm statement-save">Save</button>
                                <small class="statement-save-status" role="status" aria-live="polite"></small>
                            </form>
                        </td>
                        @elseif(in_array($key, ['unit_code', 'tenant_name']))
                        <td>{{ $row[$key] }}</td>
                        @else
                        <td class="text-end statement-amount">{{ number_format($row[$key], 2) }}</td>
                        @endif
                    @endforeach
                </tr>
                @empty
                <tr><td colspan="13" class="text-center text-muted py-4">No tenant lines found for this return.</td></tr>
                @endforelse
            </tbody>
            @if(count($tenantRows))
            <tfoot><tr>
                @foreach(\App\Services\ReturnStatementService::COLUMNS as $key => $label)
                <td class="{{ isset($totals[$key]) ? 'text-end statement-amount' : '' }}">{{ $key === 'tenant_name' ? 'TOTALS' : (isset($totals[$key]) ? number_format($totals[$key], 2) : '') }}</td>
                @endforeach
            </tr></tfoot>
            @endif
        </table>
    </div>
    <div class="row justify-content-end mt-4">
        <section class="col-lg-6 col-xl-5" aria-label="Financial summary">
            <div class="border rounded p-3">
                <h6 class="mb-3">Financial summary</h6>
                <table class="table table-sm mb-0">
                    @foreach($summaryRows as [$key, $label, $kind])
                    <tr class="{{ $kind === 'closing' ? 'border-top fw-bold' : ($kind === 'strong' ? 'fw-bold' : '') }}"><td>{{ $label }}</td><td class="text-end statement-amount">{{ number_format($summary[$key], 2) }}</td></tr>
                    @endforeach
                </table>
            </div>
        </section>
    </div>
    <div class="row justify-content-end mt-3">
        <section class="col-lg-9 col-xl-8" aria-label="Deductions and amount payable">
            <div class="border rounded p-3">
                <h6 class="mb-3">Deductions, opening balance and amount payable</h6>
                <table class="table table-sm mb-0">
                    <thead><tr><th>Description</th><th>Reference</th><th>Date</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                        @foreach($settlementRows as $row)
                        <tr class="{{ $row['kind'] === 'closing' ? 'border-top fw-bold' : ($row['kind'] === 'strong' ? 'fw-bold' : ($row['kind'] === 'gap' ? 'text-danger' : '')) }}">
                            <td>{{ $row['kind'] === 'item' ? 'Less / add: ' : '' }}{{ $row['label'] }}</td>
                            <td>{{ $row['ref'] }}</td>
                            <td>{{ $row['date'] }}</td>
                            <td class="text-end statement-amount">{{ number_format($row['amount'], 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    @if(count($arrears))
    <div class="row justify-content-end mt-3">
        <section class="col-lg-9 col-xl-8" aria-label="Arrears details">
            <div class="border rounded p-3">
                <h6 class="mb-3">Arrears details</h6>
                <table class="table table-sm mb-0">
                    <thead><tr><th>Date</th><th>Details</th><th>Room</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                        @foreach($arrears as $row)
                        <tr><td>{{ $row['date'] }}</td><td>{{ $row['details'] }}</td><td>{{ $row['room'] }}</td><td class="text-end statement-amount">{{ number_format($row['amount'], 2) }}</td></tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr class="fw-bold"><td colspan="3">Total</td><td class="text-end statement-amount">{{ number_format(array_sum(array_column($arrears, 'amount')), 2) }}</td></tr></tfoot>
                </table>
            </div>
        </section>
    </div>
    @endif
</div>
