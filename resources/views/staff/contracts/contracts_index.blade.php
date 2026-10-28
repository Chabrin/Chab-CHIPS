@extends('layouts.main')
@section('content')

@php
$canReadStaff = auth()->user()->hasPermission('read_contracts');
$canUpdateStaff = auth()->user()->hasPermission('update_contracts');
@endphp

<style>
    .contracts-page .contracts-heading { gap:20px; flex-wrap:wrap; padding-bottom:20px; }
    .contracts-page .contracts-heading h4 { color:#173c36; font-size:26px; font-weight:750; text-transform:none; letter-spacing:-.5px; margin:0; }
    .contracts-page .contracts-heading p { color:#687d78; margin:6px 0 0; font-size:14px; letter-spacing:0; }
    .contracts-page .contracts-title { border-left:4px solid #377d60; padding-left:16px; }
    .contracts-page .contract-summary { display:flex; align-items:center; justify-content:space-between; gap:16px; min-height:138px; border:1px solid; border-radius:14px; padding:22px 24px; }
    .contracts-page .contract-summary strong { display:block; font-size:36px; line-height:1.15; font-weight:750; letter-spacing:-1px; margin:5px 0; overflow-wrap:anywhere; }
    .contracts-page .contract-summary-label { display:block; font-size:14px; font-weight:650; letter-spacing:0; }
    .contracts-page .contract-summary small { font-size:12px; letter-spacing:0; }
    .contracts-page .contract-summary-icon { display:grid; place-items:center; flex-shrink:0; width:52px; height:52px; border-radius:14px; background:rgba(255,255,255,.8); }
    .contracts-page .contract-summary-icon span { font-size:28px; }
    .contracts-page .contract-summary-soon { background:#fff8eb; border-color:#f0dfb9; color:#8b5813; }
    .contracts-page .contract-summary-expired { background:#fff1f1; border-color:#f0d1d4; color:#a13645; }
    .contracts-page .contract-summary-missing { background:#edf5fa; border-color:#cfdfeb; color:#355f7c; }
    .contracts-page .contract-filter-bar { display:flex; flex-wrap:wrap; align-items:center; gap:18px 30px; background:#f7faf8; border:1px solid #e1eae5; border-radius:12px; padding:18px 22px; margin:22px 0 28px; }
    .contracts-page .contract-filter-control { flex:0 1 280px; width:100%; }
    .contracts-page .contract-filter-control label { color:#294f42; font-size:13px; font-weight:700; margin-bottom:6px; }
    .contracts-page .contract-filter-control select { background-color:#fff; border-color:#cfddd5; border-radius:8px; font-size:14px; }
    .contracts-page .contract-filter-note { display:flex; align-items:flex-start; gap:9px; flex:1 1 300px; max-width:660px; color:#61756b; font-size:13px; line-height:1.6; letter-spacing:0; }
    .contracts-page .contract-filter-note .material-symbols-outlined { font-size:19px; margin-top:2px; flex-shrink:0; }
    @media(max-width:767px) {
        .contracts-page .contracts-heading .ms-auto { margin-left:0!important; flex-wrap:wrap; }
        .contracts-page .contract-summary { min-height:112px; padding:18px; }
        .contracts-page .contract-summary strong { font-size:30px; }
        .contracts-page .contract-filter-control { flex-basis:100%; }
    }
</style>
<div class="page-body contracts-page" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center contracts-heading">
                        <div class="contracts-title">
                            <h4>Employee contracts</h4>
                            <p>Track contract dates, upcoming expirations and records needing review.</p>
                        </div>
                        <div class="ms-auto d-flex gap-2">
                            @permission('read_contracts')
                                <button type="button" id="contracts-export" class="btn btn-outline-success btn-sm d-flex align-items-center" disabled title="Export all contracts matching the current filters">
                                    <span class="material-symbols-outlined" style="font-size:18px;">download</span>
                                    <span class="ms-1">Export CSV</span>
                                </button>
                            @endpermission
                            @permission('update_contracts')
                                <a class="btn btn-secondary btn-sm d-flex align-items-center" href="{{ route('contract.bulk.edit') }}">
                                    <span class="material-symbols-outlined" style="font-size:18px;">table_edit</span>
                                    <span class="ms-1">Bulk Edit</span>
                                </a>
                            @endpermission
                            @permission('create_contracts')
                                <a class="btn btn-primary btn-sm d-flex align-items-center" href="{{ route('contract.create') }}">
                                    <span class="material-symbols-outlined">add_circle</span>
                                    <span class="ms-1">Add Contracts</span>
                                </a>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="contracts-export-status" class="small text-muted mb-2" role="status" aria-live="polite"></div>
                        <div class="row g-3 mb-3" aria-label="Contract expiry summary">
                            <div class="col-md-4"><div class="contract-summary contract-summary-soon">
                                <div><span class="contract-summary-label">Expiring soon</span><strong id="contracts-expiring-count">—</strong><small>Within the next 30 days</small></div>
                                <div class="contract-summary-icon" aria-hidden="true"><span class="material-symbols-outlined">event_upcoming</span></div>
                            </div></div>
                            <div class="col-md-4"><div class="contract-summary contract-summary-expired">
                                <div><span class="contract-summary-label">Expired contracts</span><strong id="contracts-expired-count">—</strong><small>Past their recorded end date</small></div>
                                <div class="contract-summary-icon" aria-hidden="true"><span class="material-symbols-outlined">event_busy</span></div>
                            </div></div>
                            <div class="col-md-4"><div class="contract-summary contract-summary-missing">
                                <div><span class="contract-summary-label">Missing dates</span><strong id="contracts-missing-count">—</strong><small>Review start and end dates</small></div>
                                <div class="contract-summary-icon" aria-hidden="true"><span class="material-symbols-outlined">edit_calendar</span></div>
                            </div></div>
                        </div>
                        <div class="contract-filter-bar">
                            <div class="contract-filter-control">
                            <label for="contract-expiry-filter" class="form-label">Expiry status</label>
                            <select id="contract-expiry-filter" class="form-select" style="max-width:280px;">
                                <option value="">All contracts</option>
                                <option value="upcoming">Upcoming</option>
                                <option value="active">Active</option>
                                <option value="expiring_soon">Expiring within 30 days</option>
                                <option value="expired">Expired</option>
                                <option value="missing_dates">Missing dates</option>
                                <option value="invalid_dates">Invalid dates</option>
                            </select>
                            </div>
                            <div class="contract-filter-note"><span class="material-symbols-outlined" aria-hidden="true">info</span><span>Counts cover contracts for active employees. Expiry follows the recorded dates; it does not change contract approval or employment status.</span></div>
                        </div>
                        <div id="contract-alert-filter" class="alert alert-info d-none">
                            Showing the contract linked from your notification.
                            <button type="button" class="btn btn-link btn-sm" id="clear-contract-alert">Show all contracts</button>
                        </div>
                        <div id="contract-load-error" class="alert alert-danger d-none" role="alert">Unable to load contracts. Refresh the page to try again.</div>
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="contracts_table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Date/Time</th>
                                        <th>Staff</th>
                                        <th>Position</th>
                                        <th>Date Start</th>
                                        <th>Date End</th>
                                        <th>Expiry Status</th>
                                        <th>Days Remaining</th>
                                        <th>Basic Salary</th>
                                        <th>Created By</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Data loaded via DataTables AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('assets/js/hr-contract-export.js') }}?v={{ filemtime(public_path('assets/js/hr-contract-export.js')) }}"></script>
<script>
    $(document).ready(function() {
        let table = document.querySelector('#contracts_table');
        
        let selectedContract = new URLSearchParams(window.location.search).get('contract_id');
        if (selectedContract) $('#contract-alert-filter').removeClass('d-none');
        $.fn.dataTable.ext.search.push(function(settings, data, index, row) {
            if (settings.nTable !== table) return true;
            const filter = $('#contract-expiry-filter').val();
            return (!filter || row.expiry?.status === filter) && (!selectedContract || String(row.id) === selectedContract);
        });

        const contractsTable = $(table).DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "{{ url('/ajax/hr/contracts/list') }}",
                type: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                dataSrc: function(response) {
                    const contracts = response.contracts || [];
                    $('#contracts-expiring-count').text(contracts.filter(row => row.expiry?.status === 'expiring_soon').length);
                    $('#contracts-expired-count').text(contracts.filter(row => row.expiry?.status === 'expired').length);
                    $('#contracts-missing-count').text(contracts.filter(row => row.expiry?.status === 'missing_dates').length);
                    return contracts;
                },
                error: function() {
                    $('#contracts-export').prop('disabled', true);
                    $('#contract-load-error').removeClass('d-none');
                    $('#contracts-expiring-count, #contracts-expired-count, #contracts-missing-count').text('Unavailable');
                }
            },
            columns: [
                { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => meta.row + 1 },
                { data: 'date_time', title: 'Date/Time', render: data => data ? new Date(data).toLocaleString() : '-' },
                { 
                    data: null, 
                    title: 'Staff', 
                    render: function(data, type, row) {
                        let staffNumber = row.staff_number ? row.staff_number : '-';
                        let staffName = row.employee && row.employee.name ? row.employee.name : '-';
                        return `${staffNumber} - ${staffName}`;
                    }
                },
                { data: 'position', title: 'Position', render: data => data && data.position_name ? data.position_name : '-' },
                { data: 'date_start', title: 'Date Start', render: data => data ? data : '-' },
                { data: 'date_end', title: 'Date End', render: data => data ? data : '-' },
                {
                    data: 'expiry',
                    render: function(expiry, type) {
                        if (type !== 'display') return expiry?.label || '';
                        const colors = { expired: 'danger', expiring_soon: 'warning text-dark', active: 'success', upcoming: 'info text-dark', missing_dates: 'secondary', invalid_dates: 'danger' };
                        const labels = { expired: 'Expired', expiring_soon: 'Expiring soon', active: 'Active', upcoming: 'Upcoming', missing_dates: 'Missing dates', invalid_dates: 'Invalid dates' };
                        return `<span class="badge bg-${colors[expiry?.status] || 'secondary'}">${labels[expiry?.status] || 'Unknown'}</span>`;
                    }
                },
                {
                    data: 'expiry.days_remaining',
                    render: function(days, type, row) {
                        if (type !== 'display') return days;
                        if (days === null || row.expiry.status === 'invalid_dates') return '—';
                        const unit = Math.abs(days) === 1 ? 'day' : 'days';
                        if (days < 0) return `${Math.abs(days)} ${unit} ago`;
                        return days === 0 ? 'Expires today' : `${days} ${unit} remaining`;
                    }
                },
                { data: 'basic_salary', title: 'Basic Salary', render: data => data !== undefined ? `KES ${data}` : '-' },
                { data: 'created_by', title: 'Created By', render: data => data && data.name ? data.name : '-' },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    render: function(id, type, row) {
                        let buttons = '<div class="d-flex flex-row gap-1">';
                        // View button
                        if (row.can_read && row.employee && row.employee.encrypted_id) {
                            buttons += `
                                <a class="staff-link" href="/hr/employees/${row.employee.encrypted_id}" title="View">
                                    <span class="material-symbols-outlined view-icon" style="font-size:18px;">visibility</span>
                                </a>
                                <div class="spinner-border spinner-border-sm text-primary row-spinner" role="status" style="display: none;">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            `;
                        }
                        // Edit button
                        if (row.can_update && row.employee && row.employee.encrypted_id) {
                            buttons += `
                                <a href="/hr/contracts/${row.employee.encrypted_id}/edit" title="Edit">
                                    <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                                </a>
                            `;
                        }
                        buttons += '</div>';
                        return buttons;
                    }
                }
            ],
            paging: true,
            info: true,
            lengthChange: true
        });
        $(table).on('draw.dt', function() {
            const count = contractsTable.rows({search:'applied', page:'all'}).count();
            $('#contracts-export').prop('disabled', count === 0);
            $('#contracts-export-status').text(count === 0 ? 'No matching contracts to export.' : '');
        });
        $('#contracts-export').on('click', function() {
            try {
                const count = window.ContractCsvExport.download(contractsTable);
                $('#contracts-export-status').text(count ? `Downloaded ${count} matching contract(s) across all pages.` : 'No matching contracts to export.');
            } catch (error) {
                $('#contracts-export-status').text('Unable to export contracts. Please try again.');
            }
        });
        $('#contract-expiry-filter').on('change', function() { contractsTable.draw(); });
        $('#clear-contract-alert').on('click', function() {
            selectedContract = null;
            $('#contract-alert-filter').addClass('d-none');
            const address = new URL(window.location.href);
            address.searchParams.delete('contract_id');
            window.history.replaceState({}, '', address);
            contractsTable.draw();
        });
    });
</script>

@endsection

