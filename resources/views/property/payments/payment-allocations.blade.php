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

    .filter-section {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <!-- Payment Allocations Card -->
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Payment to Invoice Matching</h5>
                    </div>
                    <div class="card-body">
                        <!-- Info Alert -->
                        <div class="info-alert">
                            <div class="d-flex align-items-start">
                                <span class="material-symbols-outlined me-2" style="color: #2196F3;">info</span>
                                <div>
                                    <strong>Automatic Payment Allocation</strong>
                                    <p class="mb-0 mt-1">This process automatically matches all unallocated payments to their corresponding invoices across all properties in the system. The system processes payments in chronological order and allocates them to outstanding invoices.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Action Button -->
                        @permission('create_invoices')
                        <button type="button" class="btn btn-primary" id="match-payments-btn">
                            <span class="material-symbols-outlined align-middle" style="font-size: 18px;">sync</span>
                            Match Payments to Invoices
                        </button>
                        @endpermission
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
        // Match payments button
        $('#match-payments-btn').on('click', function() {
            // Confirm before processing
            Swal.fire({
                icon: 'question',
                title: '<span style="font-family:Roboto,Arial,sans-serif;font-weight:600;font-size:1.1rem;letter-spacing:0.5px;display:flex;align-items:center;"><span class="material-symbols-outlined" style="color:#2196F3;font-size:1.5em;margin-right:6px;vertical-align:middle;">help</span>Confirm Allocation</span>',
                html: '<span style="font-family:Roboto,Arial,sans-serif;font-size:1rem;">Are you sure you want to process all unallocated payments?<br><br>This will automatically match unallocated payments to their corresponding invoices.</span>',
                showCancelButton: true,
                confirmButtonText: '<span style="display:flex;align-items:center;"><span class="material-symbols-outlined" style="font-size:1.2em;margin-right:4px;vertical-align:middle;">check_circle</span>Yes, Process</span>',
                cancelButtonText: '<span style="display:flex;align-items:center;"><span class="material-symbols-outlined" style="font-size:1.2em;margin-right:4px;vertical-align:middle;">cancel</span>Cancel</span>',
                customClass: {
                    popup: 'swal2-bluish',
                    confirmButton: 'swal2-bluish-btn',
                    cancelButton: 'btn btn-secondary'
                },
                buttonsStyling: false,
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    processPaymentAllocations();
                }
            });
        });

        function processPaymentAllocations() {
            // Show processing alert
            Swal.fire({
                title: '<span style="font-family:Roboto,Arial,sans-serif;font-weight:600;">Processing Payment Allocations</span>',
                html: '<span style="font-family:Roboto,Arial,sans-serif;">Please wait while we match payments to invoices...</span>',
                allowOutsideClick: false,
                allowEscapeKey: false,
                allowEnterKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Build request data
            let requestData = {
                _token: '{{ csrf_token() }}'
            };

            // Submit the request
            $.ajax({
                url: '{{ route("payments.process") }}',
                method: 'POST',
                data: requestData,
                success: function(response) {
                    if (response.success) {
                        // Build detailed result message
                        let errorHtml = '';
                        if (response.results.errors && response.results.errors.length > 0) {
                            errorHtml = '<div class="mt-3" style="font-size: 0.9rem; text-align: left; max-height: 200px; overflow-y: auto; border: 1px solid #ffc107; padding: 10px; border-radius: 4px; background-color: #fff9e6;">';
                            errorHtml += '<strong style="color: #ff9800;">⚠ Errors Encountered:</strong><br>';
                            response.results.errors.forEach(function(error) {
                                errorHtml += `<small>• Payment ${error.payment_ref || error.payment_id}: ${error.error}</small><br>`;
                            });
                            errorHtml += '</div>';
                        }

                        Swal.fire({
                            icon: response.results.errors && response.results.errors.length > 0 ? 'warning' : 'success',
                            title: '<span style="font-family:Roboto,Arial,sans-serif;font-weight:600;font-size:1.1rem;letter-spacing:0.5px;display:flex;align-items:center;"><span class="material-symbols-outlined" style="color:#2196F3;font-size:1.5em;margin-right:6px;vertical-align:middle;">check_circle</span>Processing Complete!</span>',
                            html: `<span style="font-family:Roboto,Arial,sans-serif;font-size:1rem;">
                                <div style="text-align: left;">
                                    <div style="margin-bottom: 10px;"><strong>📊 Allocation Summary:</strong></div>
                                    <div style="display: flex; align-items: center; margin-bottom: 5px;">
                                        <span class="material-symbols-outlined" style="color:#2196F3;font-size:1.2em;margin-right:4px;">payments</span>
                                        <span><strong>Payments Processed:</strong> ${response.results.payments_processed}</span>
                                    </div>
                                    <div style="display: flex; align-items: center; margin-bottom: 5px;">
                                        <span class="material-symbols-outlined" style="color:#2196F3;font-size:1.2em;margin-right:4px;">link</span>
                                        <span><strong>Allocations Created:</strong> ${response.results.allocations_created}</span>
                                    </div>
                                    <div style="display: flex; align-items: center; margin-bottom: 5px;">
                                        <span class="material-symbols-outlined" style="color:#4caf50;font-size:1.2em;margin-right:4px;">task_alt</span>
                                        <span><strong>Invoices Fully Cleared:</strong> ${response.results.invoices_cleared}</span>
                                    </div>
                                    <div style="display: flex; align-items: center; margin-bottom: 5px;">
                                        <span class="material-symbols-outlined" style="color:#ff9800;font-size:1.2em;margin-right:4px;">schedule</span>
                                        <span><strong>Invoices Partially Cleared:</strong> ${response.results.invoices_partial}</span>
                                    </div>
                                    <div style="display: flex; align-items: center; margin-bottom: 5px;">
                                        <span class="material-symbols-outlined" style="color:#9c27b0;font-size:1.2em;margin-right:4px;">cached</span>
                                        <span><strong>Returns Updated:</strong> ${response.results.returns_updated}</span>
                                    </div>
                                    ${response.results.errors && response.results.errors.length > 0 ? `
                                    <div style="display: flex; align-items: center; margin-bottom: 5px;">
                                        <span class="material-symbols-outlined" style="color:#f44336;font-size:1.2em;margin-right:4px;">error</span>
                                        <span><strong>Errors:</strong> ${response.results.errors.length}</span>
                                    </div>
                                    ` : ''}
                                </div>
                                ${errorHtml}
                            </span>`,
                            allowOutsideClick: false,
                            confirmButtonText: '<span style="display:flex;align-items:center;"><span class="material-symbols-outlined" style="font-size:1.2em;margin-right:4px;vertical-align:middle;">done</span>OK</span>',
                            customClass: {
                                popup: 'swal2-bluish',
                                confirmButton: 'swal2-bluish-btn'
                            },
                            buttonsStyling: false,
                            width: '600px'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: '<span style="font-family:Roboto,Arial,sans-serif;font-weight:600;">Processing Failed</span>',
                            html: `<span style="font-family:Roboto,Arial,sans-serif;">${response.message || 'Failed to process payment allocations'}</span>`,
                            allowOutsideClick: false,
                            confirmButtonText: '<span style="display:flex;align-items:center;"><span class="material-symbols-outlined" style="font-size:1.2em;margin-right:4px;vertical-align:middle;">done</span>OK</span>',
                            customClass: {
                                popup: 'swal2-bluish',
                                confirmButton: 'swal2-bluish-btn'
                            },
                            buttonsStyling: false
                        });
                    }
                },
                error: function(xhr) {
                    let errorMessage = 'Failed to process payment allocations. Please try again.';

                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }

                    Swal.fire({
                        icon: 'error',
                        title: '<span style="font-family:Roboto,Arial,sans-serif;font-weight:600;">Error</span>',
                        html: `<span style="font-family:Roboto,Arial,sans-serif;">${errorMessage}</span>`,
                        allowOutsideClick: false,
                        confirmButtonText: '<span style="display:flex;align-items:center;"><span class="material-symbols-outlined" style="font-size:1.2em;margin-right:4px;vertical-align:middle;">done</span>OK</span>',
                        customClass: {
                            popup: 'swal2-bluish',
                            confirmButton: 'swal2-bluish-btn'
                        },
                        buttonsStyling: false
                    });
                }
            });
        }
    });
</script>
@endsection