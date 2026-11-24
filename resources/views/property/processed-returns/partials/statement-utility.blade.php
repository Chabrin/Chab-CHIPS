<div class="return-statement">
    @php($columns = $columns ?? \App\Services\ReturnStatementService::COLUMNS)
    <div class="table-responsive border rounded">
        <table class="table table-sm align-middle mb-0 statement-table">
            <thead><tr>
                @foreach($columns as $key => $label)
                <th class="{{ !in_array($key, ['unit_code','tenant_name','remarks']) ? 'text-end' : '' }}">{{ $label }}</th>
                @endforeach
            </tr></thead>
            <tbody>
                @forelse($tenantRows as $row)
                <tr>
                    @foreach($columns as $key => $label)
                        @if($key === 'remarks')
                        <td>{{ $row['remarks'] ?? '' }}</td>
                        @elseif(in_array($key, ['unit_code', 'tenant_name']))
                        <td>{{ $row[$key] }}</td>
                        @else
                        <td class="text-end statement-amount">{{ number_format($row[$key], 2) }}</td>
                        @endif
                    @endforeach
                </tr>
                @empty
                <tr><td colspan="{{ count($columns) }}" class="text-center text-muted py-4">No utility lines on this return.</td></tr>
                @endforelse
            </tbody>
            @if(count($tenantRows))
            <tfoot><tr>
                @foreach($columns as $key => $label)
                <td class="{{ isset($totals[$key]) ? 'text-end statement-amount' : '' }}">{{ $key === 'tenant_name' ? 'TOTALS' : (isset($totals[$key]) ? number_format($totals[$key], 2) : '') }}</td>
                @endforeach
            </tr></tfoot>
            @endif
        </table>
    </div>
    <div class="row justify-content-end mt-4">
        <section class="col-lg-6 col-xl-5" aria-label="Utility summary">
            <div class="border rounded p-3">
                <h6 class="mb-3">Utility summary</h6>
                <table class="table table-sm mb-0">
                    @foreach($summaryRows as [$key, $label, $kind])
                    <tr class="{{ $kind === 'closing' ? 'border-top fw-bold' : '' }}"><td>{{ $label }}</td><td class="text-end statement-amount">{{ number_format($summary[$key], 2) }}</td></tr>
                    @endforeach
                </table>
                <small class="text-muted d-block mt-2">Commission, VAT, deductions and the amount payable are on the rent report. The two reports add up to the return.</small>
            </div>
        </section>
    </div>
</div>
