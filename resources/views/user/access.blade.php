@extends('layouts.main')

@section('content')
<style>
    .access-log-cell {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 200px;
    }

    .access-filters .form-label {
        font-size: .85rem;
        margin-bottom: .25rem;
    }

    .access-filters .form-control {
        height: calc(1.6em + .5rem);
        padding: .25rem .5rem;
        font-size: .85rem;
    }

    .btn-icon {
        display: inline-flex;
        align-items: center;
    }

    .btn-icon .material-symbols-outlined {
        font-size: 18px;
    }

    .nav-tabs .nav-link {
        border-radius: 8px 8px 0 0;
    }

    .nav-tabs .nav-link:hover {
        border-color: transparent !important;
        background-color: transparent !important;
        color: inherit !important;
    }

    .nav-tabs .nav-link.active {
        background-color: transparent !important;
        color: inherit !important;
        border-color: transparent !important;
    }

    .location-info {
        font-size: 0.875rem;
        color: #6c757d;
    }

    .session-count {
        font-weight: bold;
        color: #435ebe;
    }

    /* Clickable username styling */
    .view-user-details {
        cursor: pointer;
        transition: color 0.2s ease;
    }

    .view-user-details:hover {
        color: #435ebe !important;
        text-decoration: underline !important;
    }

    /* Condensed DataTables styling */
    .display.compact {
        font-size: 0.875rem;
    }

    .display.compact thead th,
    .display.compact tbody td {
        padding: 4px 8px !important;
        line-height: 1.2 !important;
    }

    .display.compact tbody tr {
        height: auto !important;
    }

    /* Remove table stripes */
    .display tbody tr:nth-child(even) {
        background-color: transparent !important;
    }
</style>

<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">User Access Logs</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button id="refreshBtn" class="btn btn-outline-primary btn-sm btn-icon">
                                <span class="material-symbols-outlined">refresh</span>
                                <span class="ms-1">Refresh</span>
                            </button>
                            <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">close</span>
                                <span class="ms-1">Close</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Tab Navigation -->
                        <ul class="nav nav-tabs mb-3" id="accessTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary" type="button" role="tab">
                                    <span class="material-symbols-outlined me-1">groups</span>
                                    User Summary
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="all-logs-tab" data-bs-toggle="tab" data-bs-target="#all-logs" type="button" role="tab">
                                    <span class="material-symbols-outlined me-1">list</span>
                                    All Access Logs
                                </button>
                            </li>
                        </ul>

                        <!-- Tab Content -->
                        <div class="tab-content" id="accessTabContent">
                            <!-- Summary Tab -->
                            <div class="tab-pane fade show active" id="summary" role="tabpanel">
                                <!-- Summary Filters -->
                                <form id="summaryFilter" class="row g-2 mb-3 access-filters">
                                    <div class="col-auto">
                                        <label class="form-label">From</label>
                                        <input type="date" name="date_from" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-auto">
                                        <label class="form-label">To</label>
                                        <input type="date" name="date_to" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-auto d-flex align-items-end">
                                        <button id="searchSummary" type="button" class="btn btn-primary btn-sm btn-icon me-2">
                                            <span id="summarySpinner" class="spinner-border spinner-border-sm d-none me-1"></span>
                                            <span class="material-symbols-outlined">search</span>
                                            <span class="ms-1">Search</span>
                                        </button>
                                        <button id="clearSummary" type="button" class="btn btn-secondary btn-sm btn-icon">
                                            <span class="material-symbols-outlined">clear_all</span>
                                            <span class="ms-1">Clear</span>
                                        </button>
                                    </div>
                                </form>

                                <!-- Summary Content -->
                                <div class="table-responsive custom-scrollbar">
                                    <table id="summaryTable" class="display compact" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Total Sessions</th>
                                                <th>Last IP</th>
                                                <th>Last Location</th>
                                                <th>Last Login</th>
                                            </tr>
                                        </thead>
                                        <tbody id="summaryTableBody">
                                            <!-- Content will be loaded here -->
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Summary Pagination -->
                                <div id="summaryPagination" class="d-flex justify-content-center mt-3">
                                    <!-- Pagination will be loaded here -->
                                </div>
                            </div>

                            <!-- All Logs Tab -->
                            <div class="tab-pane fade" id="all-logs" role="tabpanel">
                                <!-- All Logs Filters -->
                                <form id="logsFilter" class="row g-2 mb-3 access-filters">
                                    <div class="col-auto">
                                        <label class="form-label">From</label>
                                        <input type="date" name="date_from" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-auto">
                                        <label class="form-label">To</label>
                                        <input type="date" name="date_to" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-auto d-flex align-items-end">
                                        <button id="searchLogs" type="button" class="btn btn-primary btn-sm btn-icon me-2">
                                            <span id="logsSpinner" class="spinner-border spinner-border-sm d-none me-1"></span>
                                            <span class="material-symbols-outlined">search</span>
                                            <span class="ms-1">Search</span>
                                        </button>
                                        <button id="clearLogs" type="button" class="btn btn-secondary btn-sm btn-icon me-2">
                                            <span class="material-symbols-outlined">clear_all</span>
                                            <span class="ms-1">Clear</span>
                                        </button>
                                        <button id="exportLogs" type="button" class="btn btn-success btn-sm btn-icon">
                                            <span id="exportSpinner" class="spinner-border spinner-border-sm d-none me-1"></span>
                                            <span class="material-symbols-outlined">download</span>
                                            <span class="ms-1">Download Details</span>
                                        </button>
                                    </div>
                                </form>

                                <!-- Logs Table -->
                                <div class="table-responsive custom-scrollbar">
                                    <table id="logsTable" class="display compact" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>User</th>
                                                <th>Action</th>
                                                <th>IP Address</th>
                                                <th>Browser</th>
                                                <th>Platform</th>
                                                <th>Device</th>
                                                <th>Location</th>
                                                <th>ISP</th>
                                                <th>Time</th>
                                            </tr>
                                        </thead>
                                        <tbody id="logsTableBody">
                                            <!-- Content will be loaded here -->
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Logs Pagination -->
                                <div id="logsPagination" class="d-flex justify-content-center mt-3">
                                    <!-- Pagination will be loaded here -->
                                </div>
                            </div>
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
    $(document).ready(function() {
        'use strict';

        function escapeHtml(str) {
            return $('<div>').text(str).html();
        }

        function toggleSpinner(spinnerId, on) {
            if (on) {
                $('#' + spinnerId).removeClass('d-none');
            } else {
                $('#' + spinnerId).addClass('d-none');
            }
        }

        // Toast notification function using Toastr
        function showToast(message, type = 'error') {
            switch (type) {
                case 'success':
                    toastr.success(message);
                    break;
                case 'warning':
                    toastr.warning(message);
                    break;
                case 'info':
                    toastr.info(message);
                    break;
                case 'error':
                default:
                    toastr.error(message);
                    break;
            }
        }

        var summaryTable = null;
        var logsTable = null;

        function initSummaryTable() {
            if (summaryTable) summaryTable.destroy();
            summaryTable = $('#summaryTable').DataTable({
                data: [],
                columns: [{
                        data: 'user',
                        render: function(data, type, row) {
                            const username = (row.user?.username || row.user?.email || 'Unknown').toUpperCase();
                            return `<a href="#" class="view-user-details" data-user-id="${row.user_id}" style="text-decoration: none; color: inherit;">${username}</a>`;
                        }
                    },
                    {
                        data: 'total_sessions'
                    },
                    {
                        data: 'last_ip'
                    },
                    {
                        data: 'last_location'
                    },
                    {
                        data: 'last_login'
                    }
                ],
                pageLength: 25,
                lengthChange: true,
                lengthMenu: [
                    [10, 25, 100, 250],
                    [10, 25, 100, 250]
                ],
                autoWidth: false,
                responsive: true,
                ordering: true
            });
        }

        function initLogsTable() {
            if (logsTable) logsTable.destroy();
            logsTable = $('#logsTable').DataTable({
                data: [],
                columns: [{
                        data: 'id'
                    },
                    {
                        data: 'user',
                        render: function(data, type, row) {
                            return row.user?.username || row.user?.email || 'Unknown';
                        }
                    },
                    {
                        data: 'action',
                        render: function(data, type, row) {
                            const actionBadge = row.action === 'login' ? 'badge badge-light-success' : 'badge badge-light-danger';
                            return `<span class="${actionBadge}">${row.action.charAt(0).toUpperCase() + row.action.slice(1)}</span>`;
                        }
                    },
                    {
                        data: 'ip_address'
                    },
                    {
                        data: 'browser'
                    },
                    {
                        data: 'platform'
                    },
                    {
                        data: 'device'
                    },
                    {
                        data: 'location'
                    },
                    {
                        data: 'isp'
                    },
                    {
                        data: 'time'
                    }
                ],
                order: [
                    [0, 'desc']
                ], // Order by ID descending
                pageLength: 25,
                lengthChange: true,
                lengthMenu: [
                    [10, 25, 100, 250],
                    [10, 25, 100, 250]
                ],
                autoWidth: false,
                responsive: true,
                ordering: true
            });
        }

        function populateSummaryTable(rows) {
            if (!summaryTable) initSummaryTable();

            // Process data for display
            const processedRows = rows.map(function(item) {
                const lastLogin = item.last_login ? new Date(item.last_login).toLocaleString() : 'Never';
                const location = item.last_country ? `${item.last_city || 'Unknown'}, ${item.last_country}` : 'Unknown';

                return {
                    user_id: item.user_id,
                    user: item.user,
                    total_sessions: item.total_sessions,
                    last_ip: item.last_ip || 'Unknown',
                    last_location: location,
                    last_login: lastLogin
                };
            });

            summaryTable.clear();
            summaryTable.rows.add(processedRows).draw();
        }

        function populateLogsTable(rows) {
            if (!logsTable) initLogsTable();

            // Process data for display
            const processedRows = rows.map(function(log) {
                const location = log.country ? `${log.city || 'Unknown'}, ${log.country}` : 'Unknown';
                const time = log.action === 'login' ?
                    (log.login_at ? new Date(log.login_at).toLocaleString() : new Date(log.created_at).toLocaleString()) :
                    (log.logout_at ? new Date(log.logout_at).toLocaleString() : new Date(log.created_at).toLocaleString());

                return {
                    id: log.id,
                    user: log.user,
                    action: log.action,
                    ip_address: log.ip_address || 'Unknown',
                    browser: log.browser || 'Unknown',
                    platform: log.platform || 'Unknown',
                    device: log.device || 'Unknown',
                    location: location,
                    isp: log.isp || 'Unknown',
                    time: time
                };
            });

            logsTable.clear();
            logsTable.rows.add(processedRows).draw();
        }

        // Initialize tables
        initSummaryTable();
        initLogsTable();

        // Set default date to today
        const today = new Date().toISOString().split('T')[0];
        $('#logsFilter input[name="date_from"]').val(today);
        $('#logsFilter input[name="date_to"]').val(today);

        // Load initial summary
        loadSummary(); // Tab switching
        $('#accessTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
            if (e.target.id === 'all-logs-tab') {
                loadAllLogs();
            }
        });

        // Summary tab events
        $('#searchSummary').on('click', function() {
            loadSummary();
        });

        $('#clearSummary').on('click', function() {
            $('#summaryFilter')[0].reset();
            loadSummary();
        });

        // All logs tab events
        $('#searchLogs').on('click', function() {
            loadAllLogs();
        });

        $('#clearLogs').on('click', function() {
            $('#logsFilter')[0].reset();
            const today = new Date().toISOString().split('T')[0];
            $('#logsFilter input[name="date_from"]').val(today);
            $('#logsFilter input[name="date_to"]').val(today);
            loadAllLogs();
        });

        // Export functionality
        $('#exportLogs').on('click', function() {
            exportLogs();
        });

        // Refresh button
        $('#refreshBtn').on('click', function() {
            if ($('#summary').hasClass('show')) {
                loadSummary();
            } else {
                loadAllLogs();
            }
        });

        // Handle view user details clicks
        $(document).on('click', '.view-user-details', function(e) {
            e.preventDefault();
            const userId = $(this).data('user-id');
            console.log('User ID clicked:', userId); // Debug log
            if (!userId) {
                toastr.error('User ID not found');
                return;
            }

            // Encrypt user ID and navigate to user details page in new tab
            $.ajax({
                url: '{{ route("user.access.encrypt.id") }}',
                method: 'POST',
                data: {
                    user_id: userId,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.encrypted_id) {
                        const baseUrl = '{{ url("/user/access/details") }}';
                        const detailsUrl = `${baseUrl}/${response.encrypted_id}`;
                        console.log('Opening URL:', detailsUrl); // Debug log
                        window.open(detailsUrl, '_blank');
                    } else {
                        toastr.error('Failed to encrypt user ID');
                    }
                },
                error: function() {
                    toastr.error('Failed to encrypt user ID');
                }
            });
        });

        // Load summary data
        function loadSummary() {
            toggleSpinner('summarySpinner', true);

            const formData = new FormData($('#summaryFilter')[0]);

            $.ajax({
                url: '{{ route("user.access.summary") }}',
                method: 'GET',
                data: Object.fromEntries(formData),
                success: function(response) {
                    if (!response || !response.data) {
                        showToast('No summary data received', 'warning');
                        if (summaryTable) summaryTable.clear().draw();
                        return;
                    }
                    if (response.data.length === 0) {
                        if (summaryTable) summaryTable.clear().draw();
                        showToast('No user access data found', 'info');
                        return;
                    }
                    populateSummaryTable(response.data);
                },
                error: function(xhr) {
                    console.error('Error loading summary:', xhr);
                    const msg = xhr.responseJSON?.error || 'Failed to load summary data';
                    showToast(msg, 'error');
                    if (summaryTable) summaryTable.clear().draw();
                },
                complete: function() {
                    toggleSpinner('summarySpinner', false);
                }
            });
        }

        // Load all logs data
        function loadAllLogs() {
            toggleSpinner('logsSpinner', true);

            const formData = new FormData($('#logsFilter')[0]);

            $.ajax({
                url: '{{ route("user.access.all") }}',
                method: 'GET',
                data: Object.fromEntries(formData),
                success: function(response) {
                    if (!response || !response.data) {
                        showToast('No logs data received', 'warning');
                        if (logsTable) logsTable.clear().draw();
                        return;
                    }
                    if (response.data.length === 0) {
                        if (logsTable) logsTable.clear().draw();
                        showToast('No access logs found', 'info');
                        return;
                    }
                    populateLogsTable(response.data);
                },
                error: function(xhr) {
                    console.error('Error loading logs:', xhr);
                    const msg = xhr.responseJSON?.error || 'Failed to load logs';
                    showToast(msg, 'error');
                    if (logsTable) logsTable.clear().draw();
                },
                complete: function() {
                    toggleSpinner('logsSpinner', false);
                }
            });
        }

        // Export logs
        function exportLogs() {
            try {
                toggleSpinner('exportSpinner', true);

                const formData = new FormData($('#logsFilter')[0]);
                const params = new URLSearchParams(Object.fromEntries(formData));

                // Create a temporary link to download the file
                const link = document.createElement('a');
                link.href = '{{ route("user.access.export") }}?' + params.toString();
                link.download = 'user_access_logs.csv';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                showToast('Export started successfully', 'success');

                setTimeout(() => {
                    toggleSpinner('exportSpinner', false);
                }, 1000);
            } catch (error) {
                toggleSpinner('exportSpinner', false);
                showToast('Failed to export logs: ' + error.message, 'error');
            }
        }
    });
</script>
@endsection