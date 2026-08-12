@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">System Controls</h4>
                        <div class="ms-auto d-flex gap-2">
                            <button class="btn btn-primary btn-sm d-flex align-items-center" id="refreshTabs">
                                <span class="material-symbols-outlined">refresh</span>
                                <span class="ms-1">Refresh</span>
                            </button>
                            @permission('create_cb_system_configs')
                            <button class="btn btn-success btn-sm d-flex align-items-center" id="globalAddControlBtn" data-bs-toggle="modal" data-bs-target="#controlModal">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Setting</span>
                            </button>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        @if($categories->count())
                        <ul class="nav nav-tabs mb-3" id="controlsTab" role="tablist">
                            @foreach($categories as $i => $cat)
                            <li class="nav-item" role="presentation">
                                <button class="nav-link {{ $i==0 ? 'active':'' }}" id="cat-{{ $cat->id }}-tab" data-bs-toggle="tab" data-bs-target="#cat-{{ $cat->id }}" type="button" role="tab" aria-controls="cat-{{ $cat->id }}" aria-selected="{{ $i==0 ? 'true':'false' }}">
                                    {{ $cat->display_name }}
                                </button>
                            </li>
                            @endforeach
                        </ul>

                        <div class="tab-content" id="controlsTabContent">
                            @foreach($categories as $i => $cat)
                            <div class="tab-pane fade {{ $i==0 ? 'show active':'' }}" id="cat-{{ $cat->id }}" role="tabpanel" aria-labelledby="cat-{{ $cat->id }}-tab">
                                <div class="mb-2 d-flex justify-content-between align-items-center">
                                    <div class="text-muted">Category: <strong>{{ $cat->display_name }}</strong></div>
                                    <div>
                                        @permission('update_cb_system_configs')
                                        <button class="btn btn-sm btn-primary save-controls" data-category-id="{{ $cat->id }}">
                                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                            <span class="ms-1">Save Changes</span>
                                        </button>
                                        @endpermission
                                    </div>
                                </div>
                                <div class="controls-list" data-category-id="{{ $cat->id }}">
                                    <div class="row">
                                        <div class="col-12">Loading controls...</div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @else
                        <div class="alert alert-info">No setting categories found. Add categories first.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Control Modal -->
<div class="modal fade" id="controlModal" tabindex="-1" aria-labelledby="controlModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="controlModalLabel">Add System Control</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="controlForm">
                    <input type="hidden" name="id" id="controlId">
                    <div class="row">
                        <div class="col-lg-6 mb-3">
                            <label class="form-label">Category <span style="color:red">*</span></label>
                            <select class="form-control" name="category_id" id="controlCategoryId" required>
                                <option value="">Select category</option>
                                @foreach($categories as $c)
                                <option value="{{ $c->id }}">{{ $c->display_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-6 mb-3">
                            <label class="form-label">Display Name <span style="color:red">*</span></label>
                            <input type="text" class="form-control" name="display_name" id="controlDisplayName" required>
                        </div>
                        <div class="col-lg-6 mb-3">
                            <label class="form-label">Setting Key <span style="color:red">*</span></label>
                            <input type="text" class="form-control" name="setting" id="controlSetting" required>
                        </div>
                        <div class="col-lg-6 mb-3">
                            <label class="form-label">Toggleable</label>
                            <div class="form-check mt-2">
                                <input type="checkbox" class="form-check-input" id="controlToggle" name="toggle_boolean" value="1">
                                <label class="form-check-label" for="controlToggle">Show as switch in view</label>
                            </div>
                            <div id="toggleValueWrapper" class="mt-2 d-none">
                                <label class="form-label small">Default State</label>
                                <div class="form-check form-switch mt-1">
                                    <input class="form-check-input" type="checkbox" id="controlValueToggle">
                                    <label class="form-check-label small" for="controlValueToggle">On / Off</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 mb-3" id="controlValueWrapper">
                            <label class="form-label">Value</label>
                            <textarea class="form-control" name="value" id="controlValue" rows="3"></textarea>
                        </div>
                        <!-- hidden input used to submit value when toggleable is checked; name set dynamically -->
                        <input type="hidden" id="controlValueHidden">
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary d-flex align-items-center" data-bs-dismiss="modal">
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            <span class="ms-1">Cancel</span>
                        </button>
                        @permission('create_cb_system_configs')
                        <button type="submit" class="btn btn-success d-flex align-items-center" id="saveControlBtn">
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            <span class="ms-1">Save</span>
                        </button>
                        @endpermission
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

<script>
    $(function() {
        function loadControlsForCategory(categoryId, $container) {
            $.ajax({
                url: "{{ url('/system-controls/list') }}",
                type: 'GET',
                data: {
                    category_id: categoryId
                },
                success: function(res) {
                    var controls = res.controls || [];
                    var html = '';
                    if (!controls.length) html = '<div class="alert alert-secondary">No controls in this category.</div>';
                    controls.forEach(function(c) {
                        // render each control as editable input or toggle
                        html += '<div class="row mb-2 control-row" data-id="' + c.id + '">' +
                            '<div class="col-5"><strong>' + $('<div>').text(c.display_name).html() + '</strong><div class="text-muted small">' + $('<div>').text(c.setting).html() + '</div></div>' +
                            '<div class="col-4">';
                        if (c.toggle_boolean && Number(c.toggle_boolean) === 1) {
                            var checked = (c.value === '1' || c.value === 'true') ? 'checked' : '';
                            html += '<div class="form-check form-switch"><input class="form-check-input control-toggle" type="checkbox" ' + checked + ' data-id="' + c.id + '"></div>';
                        } else {
                            // show editable input for values
                            html += '<input type="text" class="form-control control-value" data-id="' + c.id + '" value="' + (c.value ? $('<div>').text(c.value).html() : '') + '">';
                        }
                        html += '</div>' +
                            '<div class="col-3 text-end small text-muted">';
                        html += '</div></div>';
                    });
                    $container.html(html);
                },
                error: function() {
                    $container.html('<div class="alert alert-danger">Error loading controls</div>');
                }
            });
        }

        // initial load for active tab
        $('.tab-pane.show.active .controls-list').each(function() {
            var cat = $(this).data('category-id');
            loadControlsForCategory(cat, $(this));
        });

        $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
            var target = $(e.target).data('bsTarget') || $(e.target).attr('data-bs-target');
            var id = $(target).find('.controls-list').data('category-id');
            loadControlsForCategory(id, $(target).find('.controls-list'));
        });

        $('#refreshTabs').click(function() {
            $('.controls-list').each(function() {
                loadControlsForCategory($(this).data('category-id'), $(this));
            });
        });

        // per-tab add-control removed; global add button opens modal
        $('#globalAddControlBtn').on('click', function() {
            $('#controlId').val('');
            $('#controlForm')[0].reset();
            // reset toggle UI
            $('#toggleValueWrapper').addClass('d-none');
            $('#controlValueWrapper').removeClass('d-none');
            $('#controlValueHidden').removeAttr('name').val('');
            $('#controlModal').modal('show');
        });

        // toggle checkbox change: switch value input between textarea and small select
        $('#controlToggle').on('change', function() {
            if ($(this).is(':checked')) {
                // show toggle select, hide textarea
                $('#toggleValueWrapper').removeClass('d-none');
                $('#controlValueWrapper').addClass('d-none');
                // set hidden input to the selected toggle value
                $('#controlValueHidden').attr('name', 'value').val($('#controlValueToggle').val());
            } else {
                $('#toggleValueWrapper').addClass('d-none');
                $('#controlValueWrapper').removeClass('d-none');
                $('#controlValueHidden').removeAttr('name').val('');
            }
        });

        // when toggle switch changes, update hidden value if toggleable
        $('#controlValueToggle').on('change', function() {
            if ($('#controlToggle').is(':checked')) {
                var v = $(this).is(':checked') ? '1' : '0';
                $('#controlValueHidden').val(v);
            }
        });


        // save control (modal)
        $('#controlForm').on('submit', function(e) {
            e.preventDefault();
            var $btn = $('#saveControlBtn');
            var $spinner = $btn.find('.spinner-border');
            $btn.prop('disabled', true);
            $spinner.removeClass('d-none');
            // ensure correct value is submitted: if toggleable, take selection; otherwise take textarea
            if ($('#controlToggle').is(':checked')) {
                // set hidden input named 'value' to selected toggle state (1/0)
                var v = $('#controlValueToggle').is(':checked') ? '1' : '0';
                if ($('#controlValueHidden').length) {
                    $('#controlValueHidden').attr('name', 'value').val(v);
                }
                // ensure toggle_boolean remains 1
            } else {
                // remove name from hidden to avoid conflict
                $('#controlValueHidden').removeAttr('name');
            }
            var data = $(this).serialize();
            // checkbox value handling
            if (!$('#controlToggle').is(':checked')) data += '&toggle_boolean=0';
            data += '&_token={{ csrf_token() }}';
            $.ajax({
                url: "{{ route('cb_system_configs.store') }}",
                type: 'POST',
                data: data,
                success: function(res) {
                    $spinner.addClass('d-none');
                    $btn.prop('disabled', false);
                    if (res.success) {
                        toastr.success('Saved');
                        $('#controlModal').modal('hide');
                        $('#refreshTabs').click();
                    } else toastr.error(res.error || 'Error');
                },
                error: function(xhr) {
                    $spinner.addClass('d-none');
                    $btn.prop('disabled', false);
                    var msg = 'Error';
                    if (xhr.responseJSON && xhr.responseJSON.errors) msg = Object.values(xhr.responseJSON.errors).join('\n');
                    toastr.error(msg);
                }
            });
        });

        // save controls per tab (for this simple implementation we only handle toggles client-side)
        $(document).on('click', '.save-controls', function() {
            var $btn = $(this);
            var $spinner = $btn.find('.spinner-border');
            $btn.prop('disabled', true);
            $spinner.removeClass('d-none');
            var cat = $btn.data('category-id');
            var $list = $('.controls-list[data-category-id="' + cat + '"]');
            var payload = [];
            $list.find('.control-row').each(function() {
                var id = $(this).data('id');
                var toggleEl = $(this).find('.control-toggle');
                var valueEl = $(this).find('.control-value');
                var toggle = null;
                var value = null;
                if (toggleEl.length) toggle = toggleEl.is(':checked') ? 1 : 0;
                if (valueEl.length) value = valueEl.val();
                payload.push({
                    id: id,
                    toggle: toggle,
                    value: value
                });
            });
            $.ajax({
                url: "{{ route('cb_system_configs.store') }}",
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    bulk: JSON.stringify(payload),
                    category_id: cat
                },
                success: function(res) {
                    $spinner.addClass('d-none');
                    $btn.prop('disabled', false);
                    if (res.success) toastr.success('Saved');
                    else toastr.error(res.error || 'Error');
                },
                error: function() {
                    $spinner.addClass('d-none');
                    $btn.prop('disabled', false);
                    toastr.error('Save error');
                }
            });
        });
    });
</script>