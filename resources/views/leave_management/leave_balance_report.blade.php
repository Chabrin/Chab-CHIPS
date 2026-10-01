<!-- filepath: d:\otherProjects\Chips\chips_2025\resources\views\leave_management\leave_balance_report.blade.php -->

@extends('layouts.main')

@section('styles')
<!-- Select2 CSS -->
<link rel="stylesheet" href="{{ asset('assets/css/vendors/select2.css') }}">
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Leave Balance Report</h5>
                        <div class="ms-auto d-flex gap-2">
                            <select id="staff-filter" class="form-select" style="width: 300px;">
                                <option value="">Select Staff Member</option>
                            </select>
                            <select id="year-filter" class="form-select" style="width: auto;">
                                @for($y = date('Y'); $y >= date('Y') - 5; $y--)
                                    <option value="{{ $y }}" {{ $y == date('Y') ? 'selected' : '' }}>{{ $y }}</option>
                                @endfor
                            </select>
                            <button type="button" class="btn btn-success btn-sm d-flex align-items-center" id="export-btn" style="display: none;">
                                <span class="material-symbols-outlined">download</span>
                                <span class="ms-1">Export CSV</span>
                            </button>
                            <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="refresh-btn" style="display: none;">
                                <span class="material-symbols-outlined">refresh</span>
                                <span class="ms-1">Refresh</span>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="report-container">
                            <div class="empty-state">
                                <span class="material-symbols-outlined">person_search</span>
                                <h4>Select a Staff Member</h4>
                                <p>Please select a staff member from the dropdown above to view their leave balance report.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .staff-card {
        margin-bottom: 2rem;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
    }
    
    .staff-header {
        background-color: #f8f9fa;
        padding: 1rem;
        border-bottom: 2px solid #dee2e6;
    }
    
    .staff-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }
    
    .info-item {
        display: flex;
        flex-direction: column;
    }
    
    .info-label {
        font-size: 0.75rem;
        color: #6c757d;
        text-transform: uppercase;
        font-weight: 600;
        margin-bottom: 0.25rem;
    }
    
    .info-value {
        font-size: 0.95rem;
        color: #212529;
        font-weight: 500;
    }
    
    .leave-balances-table {
        width: 100%;
        margin-top: 0;
    }
    
    .leave-balances-table thead {
        background-color: #e9ecef;
    }
    
    .leave-balances-table th {
        padding: 0.75rem 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        text-align: center;
        border: 1px solid #dee2e6;
    }
    
    .leave-balances-table td {
        padding: 0.75rem 0.5rem;
        font-size: 0.875rem;
        text-align: center;
        border: 1px solid #dee2e6;
    }
    
    .leave-balances-table td:first-child {
        text-align: left;
        font-weight: 600;
    }
    
    .balance-positive {
        color: #28a745;
        font-weight: 600;
    }
    
    .balance-negative {
        color: #dc3545;
        font-weight: 600;
    }
    
    .balance-zero {
        color: #6c757d;
    }
    
    .pending-days {
        color: #ffc107;
        font-weight: 600;
    }
    
    .no-plan-badge {
        background-color: #ffc107;
        color: #000;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 20;
    }
    
    .material-symbols-outlined.spin {
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    .empty-state {
        text-align: center;
        padding: 3rem;
        color: #6c757d;
    }
    
    .empty-state .material-symbols-outlined {
        font-size: 4rem;
        opacity: 0.3;
        margin-bottom: 1rem;
    }
    
    /* Select2 customization */
    .select2-container--default .select2-selection--single {
        height: 38px;
        padding: 6px 12px;
        border-color: #dee2e6;
    }
    
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 24px;
    }
    
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px;
    }
</style>


<!-- Select2 JS -->
<script src="{{ asset('assets/js/toastr.min.js') }}"></script>
<script>
$(document).ready(function() {
    let currentYear = $('#year-filter').val();
    let currentStaffId = '';
    
    // Initialize Select2
    $('#staff-filter').select2({
        placeholder: 'Search and select a staff member...',
        allowClear: true,
        width: '300px',
        matcher: function(params, data) {
            // If there are no search terms, return all data
            if ($.trim(params.term) === '') {
                return data;
            }

            // Skip if there is no 'text' property
            if (typeof data.text === 'undefined') {
                return null;
            }

            // Search in both text and staff number
            if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) {
                return data;
            }

            return null;
        }
    });
    
    // Load staff list for dropdown
    function loadStaffList() {
        $.get('/ajax/hr/staff/list', function(response) {
            let options = '<option value="">Select Staff Member</option>';
            response.staff.forEach(function(staff) {
                options += `<option value="${staff.id}">${staff.full_names} - ${staff.staff_number || 'N/A'} - ${staff.department || 'No Dept'}</option>`;
            });
            $('#staff-filter').html(options);
        });
    }
    
    function renderReport(data) {
        let html = '';
        
        if (data.length === 0) {
            html = `
                <div class="empty-state">
                    <span class="material-symbols-outlined">person_off</span>
                    <h4>No Data Found</h4>
                    <p>No leave balance data available for the selected staff member.</p>
                </div>
            `;
        } else {
            data.forEach(function(staff) {
                let hasPlan = staff.leave_balances.some(b => b.has_plan);
                
                html += `
                    <div class="staff-card">
                        <div class="staff-header">
                            <div class="staff-info">
                                <div class="info-item">
                                    <span class="info-label">Staff Number</span>
                                    <span class="info-value">${staff.staff_number}</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Full Names</span>
                                    <span class="info-value">${staff.full_names}</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Department</span>
                                    <span class="info-value">${staff.department}</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Position</span>
                                    <span class="info-value">${staff.position}</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Email</span>
                                    <span class="info-value">${staff.email}</span>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="leave-balances-table">
                                <thead>
                                    <tr>
                                        <th>Leave Type</th>
                                        <th>Plan Period</th>
                                        <th>Entitled Days</th>
                                        <th>Days Taken</th>
                                        <th>Days Pending</th>
                                        <th>Current Balance</th>
                                        <th>Available Balance</th>
                                    </tr>
                                </thead>
                                <tbody>
                `;
                
                staff.leave_balances.forEach(function(balance) {
                    if (!balance.has_plan) {
                        html += `
                            <tr style="background-color: #fff3cd;">
                                <td>${balance.leave_type_name}</td>
                                <td colspan="6" class="text-center">
                                    <span class="no-plan-badge">No Leave Plan Created for ${currentYear}</span>
                                </td>
                            </tr>
                        `;
                    } else {
                        let balanceClass = 'balance-zero';
                        if (balance.current_balance > 0) balanceClass = 'balance-positive';
                        if (balance.current_balance < 0) balanceClass = 'balance-negative';
                        
                        let availableClass = 'balance-zero';
                        if (balance.available_balance > 0) availableClass = 'balance-positive';
                        if (balance.available_balance < 0) availableClass = 'balance-negative';
                        
                        html += `
                            <tr>
                                <td>${balance.leave_type_name}</td>
                                <td>${balance.plan_start_date} to ${balance.plan_end_date}</td>
                                <td>${balance.entitled_days}</td>
                                <td>${balance.days_taken}</td>
                                <td class="${balance.days_pending > 0 ? 'pending-days' : ''}">${balance.days_pending}</td>
                                <td class="${balanceClass}">${balance.current_balance}</td>
                                <td class="${availableClass}">${balance.available_balance}</td>
                            </tr>
                        `;
                    }
                });
                
                html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
            });
        }
        
        $('#report-container').html(html);
    }
    
    function loadReport() {
        if (!currentStaffId) {
            $('#report-container').html(`
                <div class="empty-state">
                    <span class="material-symbols-outlined">person_search</span>
                    <h4>Select a Staff Member</h4>
                    <p>Please select a staff member from the dropdown above to view their leave balance report.</p>
                </div>
            `);
            $('#export-btn, #refresh-btn').hide();
            return;
        }
        
        let $refreshBtn = $('#refresh-btn');
        let $icon = $refreshBtn.find('.material-symbols-outlined');
        $icon.addClass('spin');
        $refreshBtn.prop('disabled', true);
        
        // Show buttons when staff is selected
        $('#export-btn, #refresh-btn').show();
        
        $.ajax({
            url: '/ajax/leave-management/leave-balance-report-data',
            type: 'GET',
            data: { 
                year: currentYear,
                staff_id: currentStaffId
            },
            dataType: 'json',
            success: function(response) {
                $icon.removeClass('spin');
                $refreshBtn.prop('disabled', false);
                
                if (response.success) {
                    renderReport(response.data);
                } else {
                    toastr.error(response.error || 'Failed to load report');
                }
            },
            error: function() {
                $icon.removeClass('spin');
                $refreshBtn.prop('disabled', false);
                toastr.error('Failed to load leave balance report');
            }
        });
    }
    
    // Staff filter change
    $('#staff-filter').on('change', function() {
        currentStaffId = $(this).val();
        loadReport();
    });
    
    // Year filter change
    $('#year-filter').on('change', function() {
        currentYear = $(this).val();
        if (currentStaffId) {
            loadReport();
        }
    });
    
    // Refresh button
    $('#refresh-btn').on('click', function() {
        loadReport();
    });
    
    // Export button
    $('#export-btn').on('click', function() {
        if (!currentStaffId) {
            toastr.warning('Please select a staff member first');
            return;
        }
        
        let $btn = $(this);
        let $icon = $btn.find('.material-symbols-outlined');
        let originalText = $btn.find('span.ms-1').text();
        
        $icon.addClass('spin');
        $btn.find('span.ms-1').text('Exporting...');
        $btn.prop('disabled', true);
        
        let exportUrl = `/ajax/leave-management/export-leave-balance-report?year=${currentYear}&staff_id=${currentStaffId}`;
        
        window.location.href = exportUrl;
        
        setTimeout(function() {
            $icon.removeClass('spin');
            $btn.find('span.ms-1').text(originalText);
            $btn.prop('disabled', false);
        }, 2000);
    });
    
    // Initial load
    loadStaffList();
});
</script>
@endsection
