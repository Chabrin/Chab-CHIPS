@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;" id="email-templates-page">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4 class="mb-0">Email Templates</h4>
                        <div class="ms-auto d-flex gap-2">
                            <select id="confirmationFilter" class="form-select form-select-sm" style="width:auto;min-width:170px;">
                                <option value="">All Templates</option>
                                <option value="1">Confirmed Only</option>
                                <option value="0">Unconfirmed Only</option>
                            </select>
                            <select id="typeFilter" class="form-select form-select-sm" style="width:auto;min-width:150px;">
                                <option value="">All Types</option>
                            </select>
                            <a href="{{ route('email-templates.confirm.view') }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined me-1">check_circle</span>
                                Confirm Templates
                            </a>

                        </div>
                    </div>
                    <div class="card-body">


                        <div class="table-responsive custom-scrollbar position-relative">
                            <div id="email-templates-loading" class="table-loading" style="position:absolute;inset:0;display:none;align-items:center;justify-content:center;background:#fff;z-index:5;">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                                    <div>Loading Templates...</div>
                                </div>
                            </div>
                            <table class="display" id="emailTemplatesTable">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Subject</th>
                                        <th>Status</th>
                                        <th>Added By</th>
                                        <th>Confirmed By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Email Template Modal -->
<div class="modal fade" id="emailTemplateModal" tabindex="-1" aria-labelledby="emailTemplateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="emailTemplateModalLabel">Edit Email Template</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="emailTemplateForm">
                    <input type="hidden" id="templateId" name="id">

                    <div class="row g-3 mb-2">
                        <div class="col-md-6">
                            <label for="name" class="form-label mb-1">Template Name *</label>
                            <input type="text" class="form-control form-control-sm" id="name" name="name" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="subject" class="form-label mb-1">Email Subject *</label>
                            <input type="text" class="form-control form-control-sm" id="subject" name="subject" required>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <small class="form-text text-muted">Use variables like: user_name, company_name, date, amount</small>
                    </div>

                    <div class="mb-3">
                        <label for="body" class="form-label">Email Body *</label>
                        <div id="body" name="body"></div>
                        <div class="invalid-feedback"></div>
                        <small class="form-text text-muted">Use the editor to create rich HTML content. Template variables like {!! json_encode('{{username}}') !!}, {!! json_encode('{{company_name}}') !!} will be replaced automatically.</small>
                    </div> <!-- Removed Template Type, Active/Confirmed toggles, and Notes per requirements -->
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm d-flex align-items-center" data-bs-dismiss="modal">
                    <span class="material-symbols-outlined me-1">close</span>
                    <span>Cancel</span>
                </button>
                <button type="button" id="saveTemplateBtn" class="btn btn-primary btn-sm d-flex align-items-center">
                    <span class="spinner-border spinner-border-sm me-2 d-none" id="saveSpinner" role="status" aria-hidden="true"></span>
                    <span class="material-symbols-outlined me-1" id="saveIcon">save</span>
                    <span>Save Template</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Email Template Modal (styled like Edit) -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="previewModalLabel">View Email Template</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-2">
                    <div class="col-md-6">
                        <label for="previewNameInput" class="form-label mb-1">Template Name</label>
                        <input type="text" id="previewNameInput" class="form-control form-control-sm" readonly>
                    </div>
                    <div class="col-md-6">
                        <label for="previewSubjectInput" class="form-label mb-1">Email Subject</label>
                        <input type="text" id="previewSubjectInput" class="form-control form-control-sm" readonly>
                    </div>
                </div>
                <div class="mb-2">
                    <label for="previewBody" class="form-label mb-1">Email Body</label>
                    <div id="previewBody" class="border p-3 bg-white rounded" style="min-height: 200px;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm d-flex align-items-center" data-bs-dismiss="modal">
                    <span class="material-symbols-outlined me-1">close</span>
                    <span>Close</span>
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('styles')

<!-- <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
<link rel="stylesheet" type="text/css" href="{{ asset('assets/css/vendors/summernote.css') }}"> -->

@endpush

@section('scripts')
<!-- <script src="{{ asset('assets/js/editor/summernote/summernote.js') }}"></script>
<script src="{{ asset('assets/js/editor/summernote/summernote.custom.js') }}"></script>
<script src="{{ asset('assets/js/editor/summernote/summernote-custom1.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script> -->



<script>
    let emailTemplatesTable;
    const canUpdate = "{{ $canUpdate ? 1 : 0 }}" === "1";

    $(document).ready(function() {
        showLoading();
        initializeEmailTemplatesTable();
        loadTemplateTypes();

        // Initialize Summernote for editing
        const placeholderText = 'Enter your email template content here. You can use template variables like ' + '{' + '{username}' + '}' + ', ' + '{' + '{company_name}' + '}' + ', ' + '{' + '{reset_url}' + '}' + ', etc.';

        $('#body').summernote({
            height: 300,
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'underline', 'clear']],
                ['fontname', ['fontname']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']],
                ['insert', ['link', 'picture', 'video']],
                ['view', ['fullscreen', 'codeview', 'help']]
            ],
            placeholder: placeholderText,
            tabsize: 2,
            focus: true
        });

        // Initialize Summernote for preview (read-only)
        $('#previewBody').summernote({
            height: 300,
            toolbar: false,
            airMode: false,
            dialogsInBody: true,
            callbacks: {
                onInit: function() {
                    $('#previewBody').next('.note-editor').find('.note-editable').attr('contenteditable', false);
                }
            }
        });

        // Filter event handlers
        $('#confirmationFilter, #typeFilter').on('change', function() {
            emailTemplatesTable.ajax.reload();
        });

        // Delegated handlers for view/edit with spinners
        $('#emailTemplatesTable').on('click', '.view-template', function() {
            const btn = $(this);
            const id = btn.data('id');
            btn.prop('disabled', true);
            btn.find('.view-icon').addClass('d-none');
            btn.find('.view-spinner').removeClass('d-none');

            $.get('{{ route("email_templates.show", ":id") }}'.replace(':id', id))
                .done(function(response) {
                    const template = response.template;
                    $('#previewNameInput').val(template.name);
                    $('#previewSubjectInput').val(template.subject);
                    // Set content in Summernote for preview
                    $('#previewBody').summernote('code', template.body);
                    $('#previewModal').modal('show');
                })
                .fail(function() {
                    showAlert('Error loading template details', 'danger');
                })
                .always(function() {
                    btn.prop('disabled', false);
                    btn.find('.view-icon').removeClass('d-none');
                    btn.find('.view-spinner').addClass('d-none');
                });
        });

        $('#emailTemplatesTable').on('click', '.edit-template', function() {
            const btn = $(this);
            const id = btn.data('id');
            btn.prop('disabled', true);
            btn.find('.edit-icon').addClass('d-none');
            btn.find('.edit-spinner').removeClass('d-none');

            $('#emailTemplateModalLabel').text('Edit Email Template');
            clearFormErrors();

            $.get('{{ route("email_templates.show", ":id") }}'.replace(':id', id))
                .done(function(response) {
                    const template = response.template;
                    $('#templateId').val(template.id);
                    $('#name').val(template.name);
                    $('#subject').val(template.subject);
                    // Set content in Summernote for editing
                    $('#body').summernote('code', template.body);
                    $('#emailTemplateModal').modal('show');
                })
                .fail(function() {
                    showAlert('Error loading template details', 'danger');
                })
                .always(function() {
                    btn.prop('disabled', false);
                    btn.find('.edit-icon').removeClass('d-none');
                    btn.find('.edit-spinner').addClass('d-none');
                });
        });

        // Save button handler with spinner
        $('#saveTemplateBtn').on('click', function() {
            saveEmailTemplate();
        });

        // Clear summernote when modal is hidden
        $('#emailTemplateModal').on('hidden.bs.modal', function() {
            $('#body').summernote('reset');
        });

        // Set focus on name field when modal is shown
        $('#emailTemplateModal').on('shown.bs.modal', function() {
            $('#name').focus();
        });
    });

    function initializeEmailTemplatesTable() {
        const columns = [{
            data: 'name'
        }, {
            data: 'subject',
            render: function(data, type, row) {
                return data.length > 50 ? data.substring(0, 50) + '...' : data;
            }
        }, {
            data: 'status_badge',
            orderable: false,
            searchable: false
        }, {
            data: 'added_by'
        }, {
            data: 'confirmed_by',
            render: function(data, type, row) {
                return data || '-';
            }
        }, {
            data: 'id',
            orderable: false,
            searchable: false,
            render: function(data, type, row) {
                let out = [];
                out.push('<button title="View" class="btn btn-primary btn-sm d-inline-flex align-items-center justify-content-center p-1 me-1 view-template" data-id="' + data + '">\
<span class="spinner-border spinner-border-sm d-none view-spinner" role="status" aria-hidden="true"></span>\
<span class="material-symbols-outlined view-icon" style="font-size:18px;line-height:1">visibility</span></button>');

                if (canUpdate) {
                    out.push('<button title="Edit" class="btn btn-primary btn-sm d-inline-flex align-items-center justify-content-center p-1 me-1 edit-template" data-id="' + data + '">\
<span class="spinner-border spinner-border-sm d-none edit-spinner" role="status" aria-hidden="true"></span>\
<span class="material-symbols-outlined edit-icon" style="font-size:18px;line-height:1">edit</span></button>');
                }

                return out.join('');
            }
        }];

        emailTemplatesTable = $('#emailTemplatesTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: '{{ route("email_templates.list") }}',
                type: 'GET',
                data: function(d) {
                    d.confirmed = $('#confirmationFilter').val();
                    d.type = $('#typeFilter').val();
                },
                dataSrc: function(json) {
                    hideLoading();
                    return json.templates || [];
                },
            },
            columns: columns,
            order: [
                [0, 'asc']
            ],
            pageLength: 25,
            responsive: true,
            language: {
                emptyTable: "No email templates found"
            }
        });
    }

    function showLoading() {
        $('#email-templates-loading').css('display', 'flex');
    }

    function hideLoading() {
        $('#email-templates-loading').hide();
    }

    function loadTemplateTypes() {
        $.get('{{ route("email_templates.types") }}')
            .done(function(response) {
                const typeFilter = $('#typeFilter');
                const currentValue = typeFilter.val();
                typeFilter.find('option:not(:first)').remove();
                response.types.forEach(type => {
                    typeFilter.append('<option value="' + type + '">' + type + '</option>');
                });
                typeFilter.val(currentValue);
            })
            .fail(function() {
                console.warn('Failed to load template types');
            });
    }



    // Legacy helpers not used directly by buttons anymore; kept for potential reuse
    function editTemplate(id) {}

    function viewTemplate(id) {}

    // Confirmation and delete actions are moved to a dedicated view (Confirm Templates)

    function saveEmailTemplate() {
        clearFormErrors();
        const $btn = $('#saveTemplateBtn');
        const $spinner = $('#saveSpinner');
        const $icon = $('#saveIcon');
        $btn.prop('disabled', true);
        $spinner.removeClass('d-none');
        $icon.addClass('d-none');

        const formData = {
            _token: '{{ csrf_token() }}',
            name: $('#name').val(),
            subject: $('#subject').val(),
            body: $('#body').summernote('code') // Get HTML from Summernote
        };

        const templateId = $('#templateId').val();
        if (!templateId) {
            showAlert('Creating new templates is disabled.', 'warning');
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
            $icon.removeClass('d-none');
            return;
        }
        const url = '{{ route("email_templates.update", ":id") }}'.replace(':id', templateId);

        formData._method = 'PUT';

        $.post(url, formData)
            .done(function(response) {
                showAlert(response.message, 'success');
                $('#emailTemplateModal').modal('hide');
                emailTemplatesTable.ajax.reload();
            })
            .fail(function(xhr) {
                if (xhr.status === 422) {
                    displayFormErrors(xhr.responseJSON.errors);
                } else {
                    const error = xhr.responseJSON?.error || 'An error occurred';
                    showAlert(error, 'danger');
                }
            })
            .always(function() {
                $btn.prop('disabled', false);
                $spinner.addClass('d-none');
                $icon.removeClass('d-none');
            });
    }

    function clearFormErrors() {
        $('.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').text('');
    }

    function displayFormErrors(errors) {
        Object.keys(errors).forEach(field => {
            const input = $('#' + field);
            if (input.length) {
                input.addClass('is-invalid');
                input.siblings('.invalid-feedback').text(errors[field][0]);
            }
        });
    }

    function showAlert(message, type) {
        const alertHtml = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
            message +
            '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';

        $('.container-fluid').first().prepend(alertHtml);

        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut(500, function() {
                $(this).remove();
            });
        }, 5000);
    }
</script>
<style>
    /* Improve preview formatting inside the view modal */
    #previewBody {
        font-size: 0.95rem;
        line-height: 1.5;
        color: #212529;
        word-wrap: break-word;
        white-space: pre-wrap;
        /* preserve line breaks from plain text */
    }

    #previewBody p {
        margin-bottom: .5rem;
    }

    #previewBody ul,
    #previewBody ol {
        padding-left: 1.25rem;
        margin-bottom: .5rem;
    }

    #previewBody h1,
    #previewBody h2,
    #previewBody h3,
    #previewBody h4,
    #previewBody h5,
    #previewBody h6 {
        margin-top: .75rem;
        margin-bottom: .5rem;
    }

    #previewBody table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: .75rem;
    }

    #previewBody th,
    #previewBody td {
        border: 1px solid #dee2e6;
        padding: .5rem;
    }

    #previewBody img {
        max-width: 100%;
        height: auto;
    }

    #previewBody a {
        color: #0d6efd;
        text-decoration: underline;
    }

    #previewBody blockquote {
        border-left: 3px solid #e9ecef;
        padding-left: .75rem;
        color: #6c757d;
    }

    #previewBody code,
    #previewBody pre {
        background: #f8f9fa;
        padding: .2rem .4rem;
        border-radius: .2rem;
    }

    #previewBody pre {
        padding: .5rem;
        overflow: auto;
    }
</style>
@endsection