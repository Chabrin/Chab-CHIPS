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
                    @foreach(app(\App\Services\ReturnStatementService::class)->summaryLabels($return) as $key => $label)
                    <tr class="{{ $key === 'net_income' ? 'border-top fw-bold' : '' }}"><td>{{ $label }}</td><td class="text-end statement-amount">{{ number_format($summary[$key], 2) }}</td></tr>
                    @endforeach
                </table>
            </div>
        </section>
    </div>
</div>
