@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">

        {{-- Header --}}
        <div class="d-flex align-items-center mb-3 gap-2">
            <a href="{{ route('service.agreements.index') }}" class="btn btn-sm btn-light border">
                <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;">arrow_back</span>
            </a>
            <div>
                <h5 class="mb-0">Move-in Inspection Settings</h5>
                <small class="text-muted">Toggle which SLAs require a move-in inspection before tenant onboarding.</small>
            </div>
        </div>

        {{-- Stats --}}
        <div class="row g-2 mb-3">
            <div class="col-4">
                <div class="card border-0 shadow-sm text-center py-3">
                    <div class="fs-4 fw-bold" style="color:#1a1a2e;">{{ $stats['total'] }}</div>
                    <div class="text-muted" style="font-size:0.78rem;">Total SLAs</div>
                </div>
            </div>
            <div class="col-4">
                <div class="card border-0 shadow-sm text-center py-3">
                    <div class="fs-4 fw-bold text-success">{{ $stats['required'] }}</div>
                    <div class="text-muted" style="font-size:0.78rem;">Inspection Required</div>
                </div>
            </div>
            <div class="col-4">
                <div class="card border-0 shadow-sm text-center py-3">
                    <div class="fs-4 fw-bold text-secondary">{{ $stats['optional'] }}</div>
                    <div class="text-muted" style="font-size:0.78rem;">Optional / Not Set</div>
                </div>
            </div>
        </div>

        {{-- Filter bar --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-2">
                <div class="row g-2 align-items-center">
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0">
                                <span class="material-symbols-outlined" style="font-size:16px;">search</span>
                            </span>
                            <input type="text" id="filterSearch" class="form-control border-start-0" placeholder="Search by reference, property or client...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select id="filterStatus" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="expired">Expired / Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="filterInspection" class="form-select form-select-sm">
                            <option value="">All Settings</option>
                            <option value="1">Required</option>
                            <option value="0">Optional</option>
                        </select>
                    </div>
                    <div class="col-md-2 text-end">
                        <button class="btn btn-sm btn-light border" id="clearFilters">Clear</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Table --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0" id="slaTable" style="font-size:0.83rem;">
                        <thead style="background:#1a1a2e;color:#fff;">
                            <tr>
                                <th class="ps-3" style="width:28px;">#</th>
                                <th>Reference</th>
                                <th>Client</th>
                                <th>Property</th>
                                <th>Status</th>
                                <th>Dates</th>
                                <th class="text-center" style="width:160px;">Move-in Inspection</th>
                            </tr>
                        </thead>
                        <tbody id="slaTableBody">
                        @forelse($slas as $i => $sla)
                            @php
                                $isActive  = $sla->current_status_id == 5 && now()->lte(\Carbon\Carbon::parse($sla->date_end));
                                $statusStr = $isActive ? 'active' : 'expired';
                            @endphp
                            <tr class="sla-row"
                                data-search="{{ strtolower(($sla->reference_number ?? '') . ' ' . ($sla->property->property_name ?? '') . ' ' . ($sla->client->names ?? '')) }}"
                                data-status="{{ $statusStr }}"
                                data-inspection="{{ $sla->move_in_inspection_required ? '1' : '0' }}">
                                <td class="ps-3 text-muted align-middle">{{ $i + 1 }}</td>
                                <td class="align-middle fw-semibold">{{ $sla->reference_number ?? '—' }}</td>
                                <td class="align-middle">{{ $sla->client->names ?? '—' }}</td>
                                <td class="align-middle">{{ $sla->property->property_name ?? '—' }}</td>
                                <td class="align-middle">
                                    @if($isActive)
                                        <span class="badge rounded-pill bg-success" style="font-size:0.68rem;">Active</span>
                                    @else
                                        <span class="badge rounded-pill bg-secondary" style="font-size:0.68rem;">Inactive</span>
                                    @endif
                                </td>
                                <td class="align-middle" style="font-size:0.78rem;">
                                    {{ $sla->date_start ? \Carbon\Carbon::parse($sla->date_start)->format('d M Y') : '—' }}
                                    <span class="text-muted">→</span>
                                    {{ $sla->date_end   ? \Carbon\Carbon::parse($sla->date_end)->format('d M Y')   : '—' }}
                                </td>
                                <td class="text-center align-middle">
                                    <div class="form-check form-switch d-inline-block" style="padding-left:2.5rem;">
                                        <input class="form-check-input inspection-toggle"
                                               type="checkbox"
                                               role="switch"
                                               id="sla_toggle_{{ $sla->id }}"
                                               data-sla-id="{{ $sla->id }}"
                                               {{ $sla->move_in_inspection_required ? 'checked' : '' }}
                                               style="cursor:pointer;width:2.5rem;height:1.25rem;">
                                    </div>
                                    <span class="ms-1 toggle-label-{{ $sla->id }}" style="font-size:0.72rem;color:{{ $sla->move_in_inspection_required ? '#198754' : '#6c757d' }};">
                                        {{ $sla->move_in_inspection_required ? 'Required' : 'Optional' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No service agreements found.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
.material-symbols-outlined { font-variation-settings:'FILL' 0,'wght' 300,'GRAD' 0,'opsz' 20; }
.sla-row:hover td { background:#f8f9fa; }
</style>

<script>
'use strict';

const FLAG_URL = "{{ route('service.agreements.move-in-flag') }}";
const CSRF    = "{{ csrf_token() }}";

/* ── Toggle handler ── */
$(document).on('change', '.inspection-toggle', function() {
    const slaId   = $(this).data('sla-id');
    const checked = $(this).prop('checked');
    const $toggle = $(this);
    const $label  = $('.toggle-label-' + slaId);

    $toggle.prop('disabled', true);

    $.ajax({
        url: FLAG_URL,
        method: 'POST',
        data: { sla_id: slaId, required: checked ? 1 : 0, _token: CSRF },
        success: function(r) {
            if (r.success) {
                $label.text(checked ? 'Required' : 'Optional')
                      .css('color', checked ? '#198754' : '#6c757d');
                // Update data attribute for filter
                $toggle.closest('tr').attr('data-inspection', checked ? '1' : '0');
                toastr.success(checked ? 'Inspection set to Required' : 'Inspection set to Optional');

                // Update stats
                const req = $('.inspection-toggle:checked').length;
                const opt = $('.inspection-toggle').length - req;
                $('.fs-4.fw-bold.text-success').text(req);
                $('.fs-4.fw-bold.text-secondary').text(opt);
            } else {
                toastr.error('Failed to update. Please try again.');
                $toggle.prop('checked', !checked);
            }
        },
        error: function() {
            toastr.error('Failed to update. Please try again.');
            $toggle.prop('checked', !checked);
        },
        complete: function() {
            $toggle.prop('disabled', false);
        }
    });
});

/* ── Filtering ── */
function applyFilters() {
    const search    = $('#filterSearch').val().toLowerCase().trim();
    const status    = $('#filterStatus').val();
    const inspection= $('#filterInspection').val();

    $('.sla-row').each(function() {
        const rowSearch    = $(this).data('search') || '';
        const rowStatus    = $(this).data('status') || '';
        const rowInspection= String($(this).data('inspection'));

        const matchSearch    = !search     || rowSearch.includes(search);
        const matchStatus    = !status     || rowStatus === status;
        const matchInspection= inspection === '' || rowInspection === inspection;

        $(this).toggle(matchSearch && matchStatus && matchInspection);
    });
}

$('#filterSearch').on('input', applyFilters);
$('#filterStatus').on('change', applyFilters);
$('#filterInspection').on('change', applyFilters);
$('#clearFilters').on('click', function() {
    $('#filterSearch').val('');
    $('#filterStatus').val('');
    $('#filterInspection').val('');
    applyFilters();
});
</script>
@endsection
