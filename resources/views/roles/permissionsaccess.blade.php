@extends('layouts.main')
@section('content')
<style>
    .btn-primary {
        background-color: #3598db;
        border-color: #3598db;
        color: white;
    }

    .btn-primary:hover {
        background-color: #4a6e71;
        border-color: #4a6e71;
    }

    .condensed-header {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 8px 12px;
        margin-bottom: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .condensed-accordion .accordion-item {
        border: 1px solid #e9ecef;
        border-radius: 4px;
        margin-bottom: 12px;
        background: white;
    }

    .condensed-accordion .accordion-header {
        display: flex;
        align-items: center;
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        border-radius: 4px 4px 0 0;
    }

    .condensed-accordion .accordion-button {
        padding: 6px 10px;
        background-color: transparent;
        box-shadow: none;
    }

    .condensed-accordion .accordion-button:not(.collapsed) {
        background-color: transparent;
        color: #495057;
        box-shadow: none;
    }

    .condensed-accordion .accordion-button:focus {
        box-shadow: none;
        border-color: transparent;
    }

    .condensed-accordion .accordion-button::after {
        display: none;
    }

    .accordion-arrow {
        margin-left: auto;
        color: #6c757d;
        font-size: 20px;
        transition: transform 0.2s ease-in-out;
    }

    .accordion-button:not(.collapsed) .accordion-arrow {
        transform: rotate(180deg);
    }

    .condensed-accordion .accordion-body {
        padding: 8px 10px;
    }

    .condensed-form-check {
        display: flex;
        align-items: center;
        margin-bottom: 4px;
        padding: 2px 0;
    }

    .condensed-form-check .form-check-input {
        margin: 0 6px 0 0;
        flex-shrink: 0;
    }

    .condensed-form-check .form-check-input:checked {
        background-color: #3598db;
        border-color: #3598db;
    }

    .condensed-form-check .form-check-input:focus {
        border-color: #3598db;
        box-shadow: 0 0 0 0.25rem rgba(59, 92, 94, 0.25);
    }

    .condensed-form-check .form-check-label {
        font-size: 0.8rem;
        line-height: 1.2;
        cursor: pointer;
        margin: 0;
    }

    .module-title {
        font-size: 0.85rem;
        font-weight: 600;
        color: #495057;
        margin: 0;
    }

    .module-select-all-wrap {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        margin-bottom: 8px;
        padding-bottom: 6px;
        border-bottom: 1px solid #eee;
    }

    .module-select-all-wrap .form-check-input {
        margin: 0 6px 0 0;
    }

    .module-select-all-wrap label {
        font-size: 0.75rem;
        font-weight: 600;
        color: #6c757d;
        cursor: pointer;
        margin: 0;
    }

    .module-icon {
        color: #6c757d;
        font-size: 16px;
    }

    .page-body {
        padding: 4px;
    }

    .main-card .card-body {
        padding: 12px;
    }
</style>

<div class="page-body">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <!-- Condensed Header Bar -->
                    <div class="condensed-header d-flex align-items-center">
                        <h6 class="mb-0 me-auto">Permissions for: <strong>{{ $role->name }}</strong></h6>
                        <div class="d-flex gap-2">
                            <button id="select-all-permissions-btn" class="btn btn-outline-primary btn-sm d-flex align-items-center" type="button">
                                <span class="material-symbols-outlined" style="font-size:16px;">done_all</span>
                                <span class="ms-1">Select All</span>
                            </button>
                            <button id="unselect-all-permissions-btn" class="btn btn-outline-primary btn-sm d-flex align-items-center" type="button">
                                <span class="material-symbols-outlined" style="font-size:16px;">remove_done</span>
                                <span class="ms-1">Unselect All</span>
                            </button>
                            <button id="save-permissions-btn" class="btn btn-primary btn-sm d-flex align-items-center" type="button">
                                <span class="spinner-border spinner-border-sm d-none me-1" id="save-perm-spinner" role="status"></span>
                                <span class="material-symbols-outlined">
                                    progress_activity
                                </span>
                                <span class="ms-1">Save Changes</span>
                            </button>
                            <a href="{{ url()->previous() }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back_ios_new</span>
                                <span class="ms-1">Back</span>
                            </a>
                        </div>
                    </div>

                    <div class="card-body main-card">
                        @include('components.error_reports')
                        <form id="permissions-access-form">
                            <div class="accordion condensed-accordion" id="permissionsAccordion">
                                @php $firstRendered = true; @endphp
                                @foreach($modules as $module)
                                @php
                                $modulePerms = $permissions->where('module_id', $module->id);
                                @endphp
                                @if($modulePerms->count())
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button {{ $firstRendered ? '' : 'collapsed' }}" type="button"
                                                data-bs-toggle="collapse" data-bs-target="#moduleCollapse{{ $module->id }}"
                                                aria-expanded="{{ $firstRendered ? 'true' : 'false' }}" aria-controls="moduleCollapse{{ $module->id }}">
                                            @if(!empty($module->icon))
                                            <span class="material-symbols-outlined module-icon me-2">{!! $module->icon !!}</span>
                                            @endif
                                            <span class="module-title">{{ $module->module_name }}</span>
                                            <span class="material-symbols-outlined accordion-arrow">expand_more</span>
                                        </button>
                                    </h2>
                                    <div id="moduleCollapse{{ $module->id }}" class="accordion-collapse collapse {{ $firstRendered ? 'show' : '' }}" data-bs-parent="#permissionsAccordion">
                                        <div class="accordion-body">
                                            <div class="module-select-all-wrap">
                                                <input class="form-check-input module-select-all" type="checkbox" data-module-id="{{ $module->id }}" id="module_select_all_{{ $module->id }}">
                                                <label for="module_select_all_{{ $module->id }}">Select All</label>
                                            </div>
                                            <div class="row g-1">
                                                @foreach($modulePerms as $perm)
                                                <div class="col-lg-3 col-md-4 col-sm-6">
                                                    <div class="condensed-form-check">
                                                        <input class="form-check-input perm-checkbox" type="checkbox" name="permissions[]" value="{{ $perm->id }}" id="perm_{{ $perm->id }}" data-module-id="{{ $module->id }}" {{ in_array($perm->id, $assigned) ? 'checked' : '' }}>
                                                        <label class="form-check-label" for="perm_{{ $perm->id }}">{{ $perm->name }}</label>
                                                    </div>
                                                </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @php $firstRendered = false; @endphp
                                @endif
                                @endforeach
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
    $(function() {
        function refreshModuleSelectAll(moduleId) {
            var $moduleChecks = $('.perm-checkbox[data-module-id="' + moduleId + '"]');
            var total = $moduleChecks.length;
            var checkedCount = $moduleChecks.filter(':checked').length;
            var $selectAll = $('.module-select-all[data-module-id="' + moduleId + '"]');

            $selectAll.prop('checked', total > 0 && checkedCount === total);
            $selectAll.prop('indeterminate', checkedCount > 0 && checkedCount < total);
        }

        // Set initial state of each module's "Select All" checkbox based on preselected permissions
        $('.module-select-all').each(function() {
            refreshModuleSelectAll($(this).data('module-id'));
        });

        $('#select-all-permissions-btn').on('click', function() {
            $('.perm-checkbox').prop('checked', true);
            $('.module-select-all').prop('checked', true).prop('indeterminate', false);
        });

        $('#unselect-all-permissions-btn').on('click', function() {
            $('.perm-checkbox').prop('checked', false);
            $('.module-select-all').prop('checked', false).prop('indeterminate', false);
        });

        $('.module-select-all').on('change', function() {
            var moduleId = $(this).data('module-id');
            var checked = $(this).is(':checked');
            $(this).prop('indeterminate', false);
            $('.perm-checkbox[data-module-id="' + moduleId + '"]').prop('checked', checked);
        });

        $(document).on('change', '.perm-checkbox', function() {
            refreshModuleSelectAll($(this).data('module-id'));
        });

        $('#save-permissions-btn').on('click', function() {
            var $btn = $(this);
            var $spinner = $('#save-perm-spinner');
            var $icon = $btn.find('.material-symbols-outlined');

            $btn.prop('disabled', true);
            $spinner.removeClass('d-none');
            $icon.hide();

            $.ajax({
                url: "{{ route('roles.permissionsaccess.save', ['id' => Crypt::encrypt($role->id)]) }}",
                type: 'POST',
                data: $('#permissions-access-form').serialize(),
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                success: function(response) {
                    toastr.success('Permissions updated successfully!');
                },
                error: function(xhr) {
                    toastr.error('Error updating permissions.');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $spinner.addClass('d-none');
                    $icon.show();
                }
            });
        });
    });
</script>

@endsection