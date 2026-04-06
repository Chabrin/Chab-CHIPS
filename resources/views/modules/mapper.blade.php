@extends('layouts.main')
@section('content')
<style>
    .mapper-container {
        display: flex;
        gap: 24px;
        align-items: flex-start;
    }

    /* Left panel: unassigned modules */
    .mapper-pool {
        width: 300px;
        flex-shrink: 0;
        position: sticky;
        top: 80px;
    }

    .mapper-pool-card {
        border: 2px dashed #d0d7de;
        border-radius: 10px;
        background: #f8fafc;
        min-height: 200px;
    }

    .mapper-pool-card .card-header {
        background: #f0f4f8;
        border-bottom: 1px solid #e2e8f0;
        border-radius: 10px 10px 0 0;
        padding: 12px 16px;
    }

    .mapper-pool-card .card-header h6 {
        margin: 0;
        font-weight: 600;
        font-size: 14px;
        color: #475569;
    }

    .mapper-pool-body {
        padding: 10px;
        min-height: 120px;
    }

    /* Right panel: super module groups */
    .mapper-groups {
        flex: 1;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 18px;
    }

    .super-module-card {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #fff;
        transition: box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .super-module-card.drag-over {
        border-color: #3598db;
        box-shadow: 0 0 0 3px rgba(53, 152, 219, 0.15);
    }

    .super-module-card-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px 16px;
        background: linear-gradient(135deg, #f5fafe, #edf5fc);
        border-bottom: 1px solid #e2e8f0;
        border-radius: 10px 10px 0 0;
    }

    .super-module-card-header .material-symbols-outlined {
        color: #3598db;
        font-size: 24px;
    }

    .super-module-card-header h6 {
        margin: 0;
        font-weight: 600;
        font-size: 14px;
        color: #2c3e50;
    }

    .super-module-card-header .badge {
        margin-left: auto;
        background: #3598db;
        font-size: 11px;
        padding: 3px 8px;
        border-radius: 12px;
    }

    .super-module-card-body {
        padding: 8px;
        min-height: 60px;
    }

    .super-module-card-body .empty-msg {
        text-align: center;
        color: #94a3b8;
        font-size: 12px;
        padding: 16px 0;
        font-style: italic;
    }

    /* Module chip */
    .module-chip {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        margin: 4px 0;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        cursor: grab;
        transition: all 0.15s ease;
        font-size: 13px;
        color: #334155;
    }

    .module-chip:hover {
        border-color: #3598db;
        background: #f5fafe;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
    }

    .module-chip:active {
        cursor: grabbing;
    }

    .module-chip.dragging {
        opacity: 0.5;
        transform: scale(0.97);
    }

    .module-chip .material-symbols-outlined {
        font-size: 18px;
        color: #3598db;
    }

    .module-chip .chip-name {
        flex: 1;
    }

    .module-chip .chip-drag-handle {
        color: #cbd5e1;
        font-size: 16px;
        cursor: grab;
    }

    .module-chip .chip-remove {
        color: #ef4444;
        font-size: 18px;
        cursor: pointer;
        opacity: 0;
        transition: opacity 0.15s;
    }

    .super-module-card-body .module-chip .chip-remove {
        opacity: 1;
    }

    .module-chip:hover .chip-remove {
        opacity: 1;
    }

    /* Select dropdown on each chip */
    .module-chip .chip-select {
        width: 160px;
        font-size: 12px;
        padding: 2px 6px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        color: #475569;
        background: #f8fafc;
        cursor: pointer;
    }

    .module-chip .chip-select:focus {
        border-color: #3598db;
        outline: none;
        box-shadow: 0 0 0 2px rgba(53, 152, 219, 0.15);
    }

    /* Save bar */
    .mapper-save-bar {
        position: sticky;
        bottom: 0;
        background: #fff;
        border-top: 1px solid #e2e8f0;
        padding: 14px 20px;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 12px;
        z-index: 10;
        box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.04);
    }

    .mapper-save-bar .changes-indicator {
        margin-right: auto;
        font-size: 13px;
        color: #f59e0b;
        font-weight: 500;
        display: none;
    }

    .mapper-save-bar .changes-indicator.visible {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    /* Filter input */
    .mapper-filter {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 6px 12px;
        font-size: 13px;
        width: 100%;
        margin-bottom: 10px;
    }

    .mapper-filter:focus {
        border-color: #3598db;
        outline: none;
        box-shadow: 0 0 0 2px rgba(53, 152, 219, 0.15);
    }

    /* Stats row */
    .mapper-stats {
        display: flex;
        gap: 16px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }

    .mapper-stat {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 12px 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 1;
        min-width: 160px;
    }

    .mapper-stat .material-symbols-outlined {
        font-size: 28px;
        color: #3598db;
    }

    .mapper-stat-value {
        font-size: 22px;
        font-weight: 700;
        color: #2c3e50;
        line-height: 1;
    }

    .mapper-stat-label {
        font-size: 12px;
        color: #94a3b8;
    }
</style>

<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        {{-- Header --}}
        <div class="card mb-3">
            <div class="card-header card-no-border d-flex align-items-center">
                <h4 class="mb-0">
                    <span class="material-symbols-outlined" style="vertical-align:middle; color:#3598db;">account_tree</span>
                    Super Module Mapper
                </h4>
                <div class="ms-auto d-flex gap-2">
                    <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center">
                        <span class="material-symbols-outlined">close</span>
                        <span class="ms-1">Close</span>
                    </a>
                </div>
            </div>
        </div>

        {{-- Stats --}}
        <div class="mapper-stats">
            <div class="mapper-stat">
                <span class="material-symbols-outlined">widgets</span>
                <div>
                    <div class="mapper-stat-value" id="statTotal">{{ $modules->count() }}</div>
                    <div class="mapper-stat-label">Total Modules</div>
                </div>
            </div>
            <div class="mapper-stat">
                <span class="material-symbols-outlined">check_circle</span>
                <div>
                    <div class="mapper-stat-value" id="statMapped">{{ $modules->whereNotNull('super_module_id')->count() }}</div>
                    <div class="mapper-stat-label">Mapped</div>
                </div>
            </div>
            <div class="mapper-stat">
                <span class="material-symbols-outlined">pending</span>
                <div>
                    <div class="mapper-stat-value" id="statUnmapped">{{ $modules->whereNull('super_module_id')->count() }}</div>
                    <div class="mapper-stat-label">Unmapped</div>
                </div>
            </div>
            <div class="mapper-stat">
                <span class="material-symbols-outlined">category</span>
                <div>
                    <div class="mapper-stat-value">{{ $superModules->count() }}</div>
                    <div class="mapper-stat-label">Super Modules</div>
                </div>
            </div>
        </div>

        @include('components.error_reports')

        {{-- Main Layout: table-based select approach --}}
        <div class="card">
            <div class="card-body p-0">
                {{-- Module list as cards with select dropdowns --}}
                <div class="p-3">
                    <input type="text" class="mapper-filter" id="moduleFilter" placeholder="Search modules...">
                </div>

                <div class="mapper-groups p-3 pt-0">
                    {{-- Unmapped modules card --}}
                    <div class="super-module-card" style="border-style: dashed;">
                        <div class="super-module-card-header" style="background: #fef9f0;">
                            <span class="material-symbols-outlined" style="color: #f59e0b;">help_outline</span>
                            <h6>Unmapped Modules</h6>
                            <span class="badge" id="badgeUnmapped" style="background:#f59e0b;">{{ $modules->whereNull('super_module_id')->count() }}</span>
                        </div>
                        <div class="super-module-card-body" id="unmappedContainer">
                            @foreach($modules->whereNull('super_module_id') as $mod)
                            <div class="module-chip" data-module-id="{{ $mod->id }}" data-module-name="{{ strtolower($mod->module_name) }}">
                                @if($mod->icon)
                                <span class="material-symbols-outlined">{!! $mod->icon !!}</span>
                                @else
                                <span class="material-symbols-outlined">extension</span>
                                @endif
                                <span class="chip-name">{{ $mod->module_name }}</span>
                                <select class="chip-select module-mapper-select" data-module-id="{{ $mod->id }}">
                                    <option value="">-- Assign --</option>
                                    @foreach($superModules as $sm)
                                    <option value="{{ $sm->id }}">{{ $sm->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endforeach
                            @if($modules->whereNull('super_module_id')->count() === 0)
                            <div class="empty-msg" id="unmappedEmpty">All modules are mapped!</div>
                            @endif
                        </div>
                    </div>

                    {{-- Super module group cards --}}
                    @foreach($superModules as $sm)
                    <div class="super-module-card" data-super-module-id="{{ $sm->id }}">
                        <div class="super-module-card-header">
                            @if($sm->icon)
                            <span class="material-symbols-outlined">{!! $sm->icon !!}</span>
                            @endif
                            <h6>{{ $sm->name }}</h6>
                            <span class="badge badge-count" data-sm-id="{{ $sm->id }}">{{ $modules->where('super_module_id', $sm->id)->count() }}</span>
                        </div>
                        <div class="super-module-card-body" data-sm-id="{{ $sm->id }}">
                            @forelse($modules->where('super_module_id', $sm->id) as $mod)
                            <div class="module-chip" data-module-id="{{ $mod->id }}" data-module-name="{{ strtolower($mod->module_name) }}">
                                @if($mod->icon)
                                <span class="material-symbols-outlined">{!! $mod->icon !!}</span>
                                @else
                                <span class="material-symbols-outlined">extension</span>
                                @endif
                                <span class="chip-name">{{ $mod->module_name }}</span>
                                <select class="chip-select module-mapper-select" data-module-id="{{ $mod->id }}">
                                    <option value="">-- Unassign --</option>
                                    @foreach($superModules as $smOpt)
                                    <option value="{{ $smOpt->id }}" {{ $sm->id == $smOpt->id ? 'selected' : '' }}>{{ $smOpt->name }}</option>
                                    @endforeach
                                </select>
                                <span class="material-symbols-outlined chip-remove" title="Unassign" data-module-id="{{ $mod->id }}">close</span>
                            </div>
                            @empty
                            <div class="empty-msg">Drop modules here</div>
                            @endforelse
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- Save bar --}}
                <div class="mapper-save-bar">
                    <div class="changes-indicator" id="changesIndicator">
                        <span class="material-symbols-outlined" style="font-size:18px;">info</span>
                        <span id="changesCount">0</span> unsaved change(s)
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm d-flex align-items-center" id="resetBtn" style="display:none;">
                        <span class="material-symbols-outlined">undo</span>
                        <span class="ms-1">Reset</span>
                    </button>
                    <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" id="saveBtn">
                        <span class="material-symbols-outlined">save</span>
                        <span class="ms-1">Save Mappings</span>
                        <span id="saveSpinner" class="spinner-border spinner-border-sm ms-2" style="display:none;"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    $(function() {
        var originalMappings = {};
        var currentMappings = {};

        // Initialize mappings from current select values
        $('.module-mapper-select').each(function() {
            var moduleId = $(this).data('module-id');
            var val = $(this).val() || null;
            originalMappings[moduleId] = val;
            currentMappings[moduleId] = val;
        });

        function getChangedCount() {
            var count = 0;
            for (var id in currentMappings) {
                if (currentMappings[id] !== originalMappings[id]) count++;
            }
            return count;
        }

        function updateUI() {
            var changed = getChangedCount();
            if (changed > 0) {
                $('#changesIndicator').addClass('visible');
                $('#changesCount').text(changed);
                $('#resetBtn').show();
            } else {
                $('#changesIndicator').removeClass('visible');
                $('#resetBtn').hide();
            }

            // Update stats
            var mapped = 0,
                unmapped = 0;
            for (var id in currentMappings) {
                if (currentMappings[id]) mapped++;
                else unmapped++;
            }
            $('#statMapped').text(mapped);
            $('#statUnmapped').text(unmapped);
            $('#badgeUnmapped').text(unmapped);

            // Update super module badges
            var smCounts = {};
            for (var id in currentMappings) {
                var smId = currentMappings[id];
                if (smId) {
                    smCounts[smId] = (smCounts[smId] || 0) + 1;
                }
            }
            $('.badge-count').each(function() {
                var smId = $(this).data('sm-id');
                $(this).text(smCounts[smId] || 0);
            });
        }

        // Handle select change
        $(document).on('change', '.module-mapper-select', function() {
            var moduleId = $(this).data('module-id');
            var newVal = $(this).val() || null;
            currentMappings[moduleId] = newVal;

            // Move the chip to the right container
            var $chip = $(this).closest('.module-chip');

            if (newVal) {
                var $targetBody = $('.super-module-card-body[data-sm-id="' + newVal + '"]');
                $targetBody.find('.empty-msg').remove();
                // Show the remove button
                if ($chip.find('.chip-remove').length === 0) {
                    $chip.append('<span class="material-symbols-outlined chip-remove" title="Unassign" data-module-id="' + moduleId + '">close</span>');
                }
                $chip.detach().appendTo($targetBody);
            } else {
                // Move to unmapped
                $chip.find('.chip-remove').remove();
                var $unmapped = $('#unmappedContainer');
                $unmapped.find('.empty-msg').remove();
                $chip.detach().appendTo($unmapped);
            }

            // Show empty messages where needed
            $('.super-module-card-body').each(function() {
                if ($(this).find('.module-chip').length === 0 && $(this).find('.empty-msg').length === 0) {
                    $(this).append('<div class="empty-msg">Drop modules here</div>');
                }
            });
            if ($('#unmappedContainer').find('.module-chip').length === 0 && $('#unmappedContainer').find('.empty-msg').length === 0) {
                $('#unmappedContainer').append('<div class="empty-msg" id="unmappedEmpty">All modules are mapped!</div>');
            }

            updateUI();
        });

        // Handle remove button (unassign)
        $(document).on('click', '.chip-remove', function(e) {
            e.stopPropagation();
            var moduleId = $(this).data('module-id');
            var $chip = $(this).closest('.module-chip');
            var $select = $chip.find('.module-mapper-select');
            $select.val('').trigger('change');
        });

        // Reset
        $('#resetBtn').on('click', function() {
            for (var id in originalMappings) {
                currentMappings[id] = originalMappings[id];
            }
            // Reload page to reset visual state cleanly
            location.reload();
        });

        // Save
        $('#saveBtn').on('click', function() {
            var $btn = $(this);
            var $spinner = $('#saveSpinner');
            $btn.prop('disabled', true);
            $spinner.show();

            var mappings = [];
            for (var id in currentMappings) {
                mappings.push({
                    module_id: parseInt(id),
                    super_module_id: currentMappings[id] ? parseInt(currentMappings[id]) : null
                });
            }

            $.ajax({
                url: "{{ route('modules.mapper.save') }}",
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    mappings: mappings
                }),
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message || 'Mappings saved!');
                        // Update original to current
                        for (var id in currentMappings) {
                            originalMappings[id] = currentMappings[id];
                        }
                        updateUI();
                    } else {
                        toastr.error(response.error || 'Failed to save.');
                    }
                    $btn.prop('disabled', false);
                    $spinner.hide();
                },
                error: function(xhr) {
                    toastr.error('Failed to save mappings.');
                    $btn.prop('disabled', false);
                    $spinner.hide();
                }
            });
        });

        // Filter
        $('#moduleFilter').on('input keyup', function() {
            var term = ($(this).val() || '').toLowerCase().trim();
            $('.module-chip').each(function() {
                var name = $(this).data('module-name') || '';
                $(this).toggle(term === '' || name.indexOf(term) !== -1);
            });
        });

        updateUI();
    });
</script>
@endsection