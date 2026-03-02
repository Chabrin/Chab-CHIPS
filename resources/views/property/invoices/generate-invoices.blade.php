@extends('layouts.main')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/chabrin.css') }}">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<style>
    .info-alert {
        background-color: #e3f2fd;
        border-left: 4px solid #2196F3;
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 20px;
    }

    .month-select {
        font-size: 1.1rem;
        padding: 10px;
    }

    .history-item {
        padding: 8px 0;
        border-bottom: 1px solid #e9ecef;
    }

    .history-item:last-child {
        border-bottom: none;
    }

    .history-month {
        font-weight: 600;
        color: #333;
        font-size: 0.875rem;
    }

    .history-details {
        font-size: 0.75rem;
        color: #6c757d;
        margin-top: 2px;
    }

    .invoice-count-badge {
        display: inline-block;
        background-color: #2196F3;
        color: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
    }

    /* Custom styles for SweetAlert2 bluish theme */
    .swal2-bluish {
        border-radius: 12px !important;
        font-family: 'Roboto', Arial, sans-serif !important;
    }

    .swal2-bluish-btn {
        background: #2196F3 !important;
        color: #fff !important;
        border: 1px solid #2196F3 !important;
        border-radius: 8px !important;
        font-weight: 600 !important;
        font-family: 'Roboto', Arial, sans-serif !important;
        font-size: 1rem !important;
        padding: 0.5em 1.5em !important;
        display: flex !important;
        align-items: center !important;
        gap: 4px !important;
    }

    .swal2-bluish-btn:hover {
        background: #1769aa !important;
        border-color: #1769aa !important;
    }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <!-- Generate Invoices Card -->
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Generate Invoices</h5>
                    </div>
                    <div class="card-body">
                        <!-- Info Alert -->
                        <div class="info-alert">
                            <div class="d-flex align-items-start">
                                <span class="material-symbols-outlined me-2" style="color: #2196F3;">info</span>
                                <div>
                                    <strong>Bulk Invoice Generation</strong>
                                    <p class="mb-0 mt-1">Select a billing month to generate invoices for all active leases across all properties in your system.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Form -->
                        <form id="generateInvoicesForm" data-current-year="{{ date('Y') }}" data-current-month="{{ date('n') }}">
                            <div class="row">
                                <div class="col-md-9">
                                    <div class="mb-3">
                                        <label for="billing_month" class="form-label">
                                            <span class="material-symbols-outlined align-middle" style="font-size: 18px;">event</span>
                                            Billing Month <span class="text-danger">*</span>
                                        </label>
                                        <input type="month" class="form-control month-select" id="billing_month" name="billing_month" required>
                                        <small class="text-muted">Select the month for invoice generation (last 3 months available)</small>
                                    </div>

                                    <!-- Generation Settings -->
                                    <div class="mb-3">
                                        <h6 class="mb-2">
                                            <span class="material-symbols-outlined align-middle" style="font-size: 18px;">info</span>
                                            Generation Settings
                                        </h6>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="radio" checked disabled>
                                            <label class="form-check-label">
                                                <strong>All Properties</strong>
                                            </label>
                                        </div>
                                        <small class="text-muted d-block">
                                            <span class="material-symbols-outlined align-middle" style="font-size: 16px;">check_circle</span>
                                            Only active leases will be included
                                        </small>
                                    </div>

                                    <!-- Action Button -->
                                    @permission('create_invoices')
                                    <button type="submit" class="btn btn-primary" id="generate-btn">
                                        <span class="material-symbols-outlined align-middle" style="font-size: 18px;">autorenew</span>
                                        Generate Invoices
                                    </button>
                                    @endpermission
                                </div>

                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <span class="material-symbols-outlined align-middle" style="font-size: 16px;">history</span>
                                            Recent Generations
                                        </label>
                                        <div id="generated-history" style="max-height: 300px; overflow-y: auto;">
                                            <div class="text-muted" style="font-size: 0.75rem;">Loading...</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(function() {
        const currentYear = parseInt($('#generateInvoicesForm').data('current-year'));
        const currentMonth = parseInt($('#generateInvoicesForm').data('current-month'));
        let generatedMonthsData = [];

        // Month names
        const monthNames = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];

        // Calculate min month (3 months back)
        let minYear = currentYear;
        let minMonth = currentMonth - 3;
        if (minMonth <= 0) {
            minMonth = 12 + minMonth;
            minYear = currentYear - 1;
        }

        // Set month restrictions
        const minDate = `${minYear}-${String(minMonth).padStart(2, '0')}`;
        const maxDate = `${currentYear}-${String(currentMonth).padStart(2, '0')}`;

        $('#billing_month').attr('min', minDate);
        $('#billing_month').attr('max', maxDate);
        $('#billing_month').val(maxDate);

        // Load generated months with details
        function loadGeneratedMonths() {
            $.ajax({
                url: '{{ route("ajax.invoices.generated.months") }}',
                method: 'GET',
                data: {
                    year: currentYear
                },
                success: function(response) {
                    if (response.success) {
                        // Fetch full details from tracking table
                        fetchGenerationHistory();
                    }
                },
                error: function(xhr) {
                    console.error('Error loading generated months:', xhr);
                    $('#generated-history').html('<div class="text-danger" style="font-size: 0.75rem;">Failed to load</div>');
                }
            });
        }

        // Fetch generation history with details
        function fetchGenerationHistory() {
            $.ajax({
                url: '/ajax/invoices/generation-history',
                method: 'GET',
                success: function(response) {
                    if (response.success) {
                        generatedMonthsData = response.history || [];
                        displayGenerationHistory();
                    }
                },
                error: function() {
                    // Fallback: just show simple list
                    displayGenerationHistory();
                }
            });
        }

        // Display generation history
        function displayGenerationHistory() {
            const container = $('#generated-history');

            if (generatedMonthsData.length === 0) {
                container.html('<div class="text-muted" style="font-size: 0.75rem;">No generations yet</div>');
                return;
            }

            let html = '';
            generatedMonthsData.slice(0, 6).forEach(function(item) {
                const date = new Date(item.month_year + '-01');
                const monthName = monthNames[date.getMonth()];
                const year = date.getFullYear();

                html += `
                    <div class="history-item">
                        <div class="history-month">${monthName} ${year}</div>
                        <div class="history-details">
                            ${item.generated_by_name || 'System'} • <span class="invoice-count-badge">${item.invoice_count} invoices</span>
                        </div>
                    </div>
                `;
            });

            container.html(html);
        }

        // Submit form
        $('#generateInvoicesForm').on('submit', function(e) {
            e.preventDefault();

            const billingMonth = $('#billing_month').val();

            if (!billingMonth) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Validation Error',
                    text: 'Please select a billing month',
                    allowOutsideClick: false
                });
                return;
            }

            // Check if already generated
            const alreadyGenerated = generatedMonthsData.some(item => item.month_year === billingMonth);
            if (alreadyGenerated) {
                Swal.fire({
                    icon: 'error',
                    title: '<span style="font-family:Roboto,Arial,sans-serif;font-weight:600;font-size:1.1rem;letter-spacing:0.5px;display:flex;align-items:center;"><span class="material-symbols-outlined" style="color:#2196F3;font-size:1.5em;margin-right:6px;vertical-align:middle;">info</span>Already Generated</span>',
                    html: '<span style="font-family:Roboto,Arial,sans-serif;font-size:1rem;">Invoices for this month have already been generated.</span>',
                    confirmButtonText: '<span style="display:flex;align-items:center;"><span class="material-symbols-outlined" style="font-size:1.2em;margin-right:4px;vertical-align:middle;">done</span>OK</span>',
                    customClass: {
                        popup: 'swal2-bluish',
                        confirmButton: 'swal2-bluish-btn'
                    },
                    buttonsStyling: false,
                    allowOutsideClick: false
                });
                return;
            }

            // Show processing alert
            Swal.fire({
                title: 'Generating Invoices',
                html: 'Please wait while we generate invoices for all active leases...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                allowEnterKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Submit the request
            $.ajax({
                url: '{{ route("invoices.generate.process") }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    billing_month: billingMonth
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '<span style="font-family:Roboto,Arial,sans-serif;font-weight:600;font-size:1.1rem;letter-spacing:0.5px;display:flex;align-items:center;"><span class="material-symbols-outlined" style="color:#2196F3;font-size:1.5em;margin-right:6px;vertical-align:middle;">check_circle</span>Success!</span>',
                            html: `<span style=\"font-family:Roboto,Arial,sans-serif;font-size:1rem;\"><strong>${response.message}</strong></span><br><br><span style=\"font-family:Roboto,Arial,sans-serif;font-size:1rem;display:flex;align-items:center;\"><span class=\"material-symbols-outlined\" style=\"color:#2196F3;font-size:1.2em;margin-right:4px;vertical-align:middle;\">receipt_long</span>Total invoices generated: <b>${response.count}</b></span>`,
                            allowOutsideClick: false,
                            confirmButtonText: '<span style="display:flex;align-items:center;"><span class="material-symbols-outlined" style="font-size:1.2em;margin-right:4px;vertical-align:middle;">done</span>OK</span>',
                            customClass: {
                                popup: 'swal2-bluish',
                                confirmButton: 'swal2-bluish-btn'
                            },
                            buttonsStyling: false
                        }).then(() => {
                            // Reload generated months
                            loadGeneratedMonths();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Generation Failed',
                            text: response.message || 'Failed to generate invoices',
                            allowOutsideClick: false
                        });
                    }
                },
                error: function(xhr) {
                    let errorMessage = 'Failed to generate invoices. Please try again.';

                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }

                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: errorMessage,
                        allowOutsideClick: false
                    });
                }
            });
        });

        // Initialize
        loadGeneratedMonths();
    });
</script>
@endsection