@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Lease Contract Templates</h5>
                        <div class="ms-auto">
                            <a href="{{ route('property.lease-settings.contract-templates.create') }}" class="btn btn-primary btn-sm">
                                <i class="fa fa-plus me-1"></i> New Template
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-light-info mb-3">
                            <i class="fa fa-info-circle me-1"></i>
                            Contract templates define the content of the PDF sent to tenants for digital signing.
                            Use placeholders like <code>@{{tenant_name}}</code>, <code>@{{property_name}}</code>, etc. — these are replaced with actual lease data when the PDF is generated.
                            The <strong>default</strong> template is used automatically when a new lease is created.
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" id="templatesTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Template Name</th>
                                        <th>Description</th>
                                        <th>Default</th>
                                        <th>Created By</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($templates as $index => $template)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $template->template_name }}</td>
                                        <td>{{ $template->description ?? '—' }}</td>
                                        <td>
                                            @if($template->is_default)
                                                <span class="badge badge-light-success">Default</span>
                                            @else
                                                <button class="btn btn-sm btn-outline-secondary set-default-btn" data-id="{{ $template->id }}">
                                                    Set Default
                                                </button>
                                            @endif
                                        </td>
                                        <td>{{ $template->createdBy->name ?? '—' }}</td>
                                        <td>{{ $template->created_at ? $template->created_at->format('d M Y') : '—' }}</td>
                                        <td>
                                            <a href="{{ route('property.lease-settings.contract-templates.edit', $template->id) }}" class="btn btn-sm btn-outline-primary" title="Edit">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                            @if(!$template->is_default)
                                            <button class="btn btn-sm btn-outline-danger delete-btn ms-1" data-id="{{ $template->id }}" title="Delete">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            No contract templates yet. <a href="{{ route('property.lease-settings.contract-templates.create') }}">Create one</a>.
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Set default
    $(document).on('click', '.set-default-btn', function() {
        const id = $(this).data('id');
        const btn = $(this);
        btn.prop('disabled', true).text('Saving...');

        $.ajax({
            url: '/property/lease-settings/contract-templates/' + id + '/set-default',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function(res) {
                toastr.success(res.message);
                setTimeout(() => location.reload(), 800);
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to set default.');
                btn.prop('disabled', false).text('Set Default');
            }
        });
    });

    // Delete
    $(document).on('click', '.delete-btn', function() {
        const id = $(this).data('id');
        if (!confirm('Are you sure you want to delete this template? This cannot be undone.')) return;

        $.ajax({
            url: '/property/lease-settings/contract-templates/' + id,
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function(res) {
                toastr.success(res.message);
                setTimeout(() => location.reload(), 800);
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to delete template.');
            }
        });
    });
});
</script>
@endsection
