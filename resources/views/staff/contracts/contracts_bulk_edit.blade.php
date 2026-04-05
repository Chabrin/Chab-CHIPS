@extends('layouts.main')
@section('content')

<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Contracts — Bulk Edit</h5>
                        <div class="ms-auto d-flex gap-2 align-items-center">
                            <span id="dirty-counter" class="text-muted small" style="display:none;">
                                <span id="dirty-count">0</span> unsaved change(s)
                            </span>
                            <button id="btn-save-all" class="btn btn-success btn-sm d-flex align-items-center" disabled>
                                <span id="save-spinner" class="spinner-border spinner-border-sm me-1" style="display:none;" role="status"></span>
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">save</span>
                                Save Changes
                            </button>
                            <a href="{{ route('contract.index') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">

                        <!-- Load error banner (shown if JS render fails for some rows) -->
                        <div id="load-error-banner" class="alert alert-warning alert-dismissible m-3" style="display:none;">
                            <strong>Warning:</strong> Some rows could not be rendered due to invalid data. They are highlighted in red below.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>

                        <!-- Loading state -->
                        <div id="table-preloader" class="text-center py-5">
                            <div class="spinner-border text-primary" style="width:3rem;height:3rem;" role="status"></div>
                            <p class="mt-3 text-muted">Loading contracts...</p>
                        </div>

                        <!-- Save results summary -->
                        <div id="save-results" class="alert m-3" style="display:none;"></div>

                        <!-- Table wrapper with scroll -->
                        <div id="table-wrapper" style="display:none; overflow-x:auto; overflow-y:auto; max-height:70vh;">
                            <table class="table table-bordered table-hover mb-0" id="bulk-edit-table" style="min-width:1100px;">
                                <thead class="table-light" style="position:sticky;top:0;z-index:10;">
                                    <tr>
                                        <th style="min-width:180px;">Staff</th>
                                        <th style="min-width:120px;">Date Start</th>
                                        <th style="min-width:120px;">Date End</th>
                                        <th style="min-width:120px;">Basic Salary</th>
                                        <th style="min-width:200px;">Position</th>
                                        <th style="min-width:150px;">Status</th>
                                        <th style="min-width:80px;">Elements</th>
                                        <th style="min-width:60px;">State</th>
                                    </tr>
                                </thead>
                                <tbody id="bulk-edit-body">
                                </tbody>
                            </table>
                        </div>

                        <div id="empty-state" class="text-center py-5 text-muted" style="display:none;">
                            <span class="material-symbols-outlined" style="font-size:48px;">inbox</span>
                            <p class="mt-2">No contracts found.</p>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Elements Edit Modal -->
<div class="modal fade" id="elementsModal" tabindex="-1" aria-labelledby="elementsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="elementsModalLabel">Edit Contract Elements</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3" id="elements-modal-staff-name" style="font-weight:600;"></p>
                <div id="elements-repeater"></div>
                <button type="button" class="btn btn-primary btn-sm d-flex align-items-center mt-2" id="btn-add-element">
                    <span class="material-symbols-outlined">add</span> Add Element
                </button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="btn-save-elements">
                    <span id="elements-spinner" class="spinner-border spinner-border-sm me-1" style="display:none;" role="status"></span>
                    Save Elements
                </button>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    // ── Passed from PHP ──────────────────────────────────────────────────────
    var positions = @json($positions);
    var contractStatuses = @json($contractStatuses);
    var csrfToken = '{{ csrf_token() }}';
    var bulkUpdateUrl = '{{ route("contract.bulk.update") }}';
    var elementsUpdateUrlTemplate = '{{ url("/hr/contracts") }}/:id/elements';
    var payrollElementsUrl = '{{ url("/ajax/payroll/elements/list/simpler") }}';

    // ── State ────────────────────────────────────────────────────────────────
    var dirty = {};          // { contractId: { id, date_start, date_end, basic_salary, position_id, current_status_id } }
    var allContracts = [];   // raw AJAX response
    var payrollElements = []; // cached for elements modal
    var currentModalContractId = null;
    var badRowCount = 0;

    // ── Helpers ──────────────────────────────────────────────────────────────
    function isValidDate(val) {
        return val && !isNaN(Date.parse(val));
    }

    function buildPositionOptions(selectedId) {
        var html = '<option value="">-- select position --</option>';
        positions.forEach(function(p) {
            html += '<option value="' + p.id + '"' + (p.id == selectedId ? ' selected' : '') + '>'
                  + escHtml(p.position_name) + '</option>';
        });
        return html;
    }

    function buildStatusOptions(selectedId) {
        var html = '<option value="">-- select status --</option>';
        Object.entries(contractStatuses).forEach(function([id, label]) {
            html += '<option value="' + id + '"' + (id == selectedId ? ' selected' : '') + '>'
                  + escHtml(label) + '</option>';
        });
        return html;
    }

    function escHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function updateDirtyCounter() {
        var count = Object.keys(dirty).length;
        $('#dirty-count').text(count);
        if (count > 0) {
            $('#dirty-counter').show();
            $('#btn-save-all').prop('disabled', false);
        } else {
            $('#dirty-counter').hide();
            $('#btn-save-all').prop('disabled', true);
        }
    }

    function markDirty(contractId) {
        var row = $('#row-' + contractId);
        dirty[contractId] = {
            id:                parseInt(contractId),
            date_start:        row.find('.inp-date-start').val(),
            date_end:          row.find('.inp-date-end').val(),
            basic_salary:      row.find('.inp-salary').val(),
            position_id:       row.find('.sel-position').val(),
            current_status_id: row.find('.sel-status').val(),
        };
        row.css('border-left', '4px solid #f0ad4e');
        clearRowState(contractId);
        updateDirtyCounter();
    }

    function clearRowState(contractId) {
        var row = $('#row-' + contractId);
        row.find('.row-state').html('');
    }

    function setRowClientError(contractId, msg) {
        var row = $('#row-' + contractId);
        row.css('border-left', '4px solid #dc3545');
        row.find('.row-state').html(
            '<span class="text-danger" title="' + escHtml(msg) + '">&#10007;</span>'
        );
    }

    function setRowOk(contractId) {
        var row = $('#row-' + contractId);
        row.css('border-left', '4px solid #28a745');
        row.find('.row-state').html('<span class="text-success">&#10003;</span>');
    }

    function setRowError(contractId, msg) {
        var row = $('#row-' + contractId);
        row.css('border-left', '4px solid #dc3545');
        row.find('.row-state').html(
            '<span class="text-danger" title="' + escHtml(msg) + '">&#10007; <small>' + escHtml(msg) + '</small></span>'
        );
    }

    // ── Render table ─────────────────────────────────────────────────────────
    function renderTable(contracts) {
        var tbody = $('#bulk-edit-body');
        tbody.empty();
        badRowCount = 0;

        if (!contracts || contracts.length === 0) {
            $('#table-preloader').hide();
            $('#empty-state').show();
            return;
        }

        contracts.forEach(function(contract) {
            try {
                var id = contract.id;
                var staffCell, staffWarning = false;

                if (!contract.employee) {
                    staffCell = '<span style="color:#e67e22;font-weight:600;">UNLINKED (staff_id: ' + escHtml(contract.staff_id) + ')</span>';
                    staffWarning = true;
                } else {
                    staffCell = '<span>' + escHtml(contract.employee.name) + '</span>'
                              + '<br><small class="text-muted">' + escHtml(contract.staff_number || '') + '</small>';
                }

                // Date values — guard against invalid legacy values
                var dateStart = isValidDate(contract.date_start) ? contract.date_start : '';
                var dateEnd   = isValidDate(contract.date_end)   ? contract.date_end   : '';

                var salary    = (contract.basic_salary !== null && contract.basic_salary !== undefined)
                                ? contract.basic_salary : '';

                var positionId = contract.position ? contract.position.id : '';
                var statusId   = contract.current_status_id || '';

                var rowStyle = staffWarning ? 'background:#fff8e1;' : '';

                var row = '<tr id="row-' + id + '" style="' + rowStyle + '">'
                    + '<td>' + staffCell + '</td>'
                    + '<td>'
                    +   '<input type="date" class="form-control form-control-sm inp-date-start" value="' + escHtml(dateStart) + '"'
                    +   (dateStart ? '' : ' placeholder="(empty)"') + '>'
                    + '</td>'
                    + '<td>'
                    +   '<input type="date" class="form-control form-control-sm inp-date-end" value="' + escHtml(dateEnd) + '"'
                    +   (dateEnd ? '' : ' placeholder="(empty)"') + '>'
                    + '</td>'
                    + '<td>'
                    +   '<input type="number" class="form-control form-control-sm inp-salary" value="' + escHtml(salary) + '" min="0" step="0.01">'
                    + '</td>'
                    + '<td>'
                    +   '<select class="form-select form-select-sm sel-position"' + (!positionId ? ' style="border-color:#e67e22;"' : '') + '>'
                    +   buildPositionOptions(positionId)
                    +   '</select>'
                    + '</td>'
                    + '<td>'
                    +   '<select class="form-select form-select-sm sel-status">'
                    +   buildStatusOptions(statusId)
                    +   '</select>'
                    + '</td>'
                    + '<td class="text-center">'
                    +   '<button type="button" class="btn btn-link p-0 btn-edit-elements" title="Edit Elements"'
                    +   ' data-id="' + id + '"'
                    +   ' data-name="' + escHtml(contract.employee ? contract.employee.name : 'UNLINKED') + '"'
                    +   ' data-elements=\'' + JSON.stringify(contract.elements || []).replace(/'/g, "&#39;") + '\'>'
                    +   '<span class="material-symbols-outlined" style="font-size:20px;">list_alt</span>'
                    +   '</button>'
                    + '</td>'
                    + '<td class="row-state text-center"></td>'
                    + '</tr>';

                tbody.append(row);

                // Wire change events
                var $row = $('#row-' + id);
                $row.find('input, select').on('change input', function() { markDirty(id); });

            } catch (err) {
                badRowCount++;
                var fallbackId = (contract && contract.id) ? contract.id : '?';
                tbody.append(
                    '<tr style="background:#fdecea;">'
                    + '<td colspan="8" class="text-danger">'
                    + '<strong>&#9888; Invalid data — Contract ID: ' + escHtml(String(fallbackId)) + '</strong>'
                    + '<small class="ms-2 text-muted">' + escHtml(err.message) + '</small>'
                    + '</td>'
                    + '</tr>'
                );
            }
        });

        if (badRowCount > 0) {
            $('#load-error-banner').show();
        }

        $('#table-preloader').hide();
        $('#table-wrapper').show();
    }

    // ── Load contracts ────────────────────────────────────────────────────────
    $.ajax({
        url: '{{ url("/ajax/hr/contracts/list") }}',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            allContracts = response.contracts || [];
            renderTable(allContracts);
        },
        error: function() {
            $('#table-preloader').hide();
            $('#load-error-banner').html(
                '<strong>Error:</strong> Failed to load contracts. Please refresh the page.'
            ).show();
        }
    });

    // ── Save all dirty rows ───────────────────────────────────────────────────
    $('#btn-save-all').on('click', function() {
        $('#save-results').hide();

        // Client-side validation
        var validPayload = [];
        var hasClientErrors = false;

        Object.keys(dirty).forEach(function(id) {
            var d = dirty[id];
            var dateStart = d.date_start;
            var dateEnd   = d.date_end;

            // Validate date order only if both are set
            if (dateStart && dateEnd && new Date(dateStart) > new Date(dateEnd)) {
                setRowClientError(id, 'Date start must be before date end');
                hasClientErrors = true;
                return;
            }

            // Validate salary
            if (d.basic_salary !== '' && parseFloat(d.basic_salary) < 0) {
                setRowClientError(id, 'Basic salary cannot be negative');
                hasClientErrors = true;
                return;
            }

            validPayload.push(d);
        });

        if (validPayload.length === 0) {
            if (!hasClientErrors) {
                return; // nothing to send
            }
            $('#save-results')
                .removeClass('alert-success alert-danger').addClass('alert-warning')
                .html('<strong>Fix the highlighted rows before saving.</strong>')
                .show();
            return;
        }

        $('#save-spinner').show();
        $('#btn-save-all').prop('disabled', true);

        $.ajax({
            type: 'PUT',
            url: bulkUpdateUrl,
            data: JSON.stringify({ contracts: validPayload }),
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            success: function(response) {
                var results = response.results || [];
                results.forEach(function(r) {
                    if (r.ok) {
                        delete dirty[r.id];
                        setRowOk(r.id);
                        // Remove dirty highlight
                        $('#row-' + r.id).css('border-left', '4px solid #28a745');
                    } else {
                        setRowError(r.id, r.error || 'Unknown error');
                    }
                });

                updateDirtyCounter();

                var msg = (response.updated || 0) + ' contract(s) saved.';
                if (response.failed > 0) {
                    msg += ' ' + response.failed + ' failed (see highlighted rows).';
                }
                $('#save-results')
                    .removeClass('alert-warning alert-danger')
                    .addClass(response.failed > 0 ? 'alert-warning' : 'alert-success')
                    .html(msg)
                    .show();
            },
            error: function(xhr) {
                var msg = 'Save failed.';
                if (xhr.status === 403) msg = 'Access denied.';
                $('#save-results')
                    .removeClass('alert-success alert-warning').addClass('alert-danger')
                    .html('<strong>Error:</strong> ' + msg)
                    .show();
            },
            complete: function() {
                $('#save-spinner').hide();
                $('#btn-save-all').prop('disabled', Object.keys(dirty).length === 0);
            }
        });
    });

    // ── Elements modal ────────────────────────────────────────────────────────
    // Load payroll elements once
    $.ajax({
        url: payrollElementsUrl,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            payrollElements = response.elements || [];
        }
    });

    function buildElementOptions(selectedId) {
        var html = '<option value="">-- select element --</option>';
        payrollElements.forEach(function(el) {
            html += '<option value="' + el.id + '"' + (el.id == selectedId ? ' selected' : '') + '>'
                  + escHtml(el.element_name) + '</option>';
        });
        return html;
    }

    function buildElementRow(index, element) {
        var entryType = element ? (element.entry_type || '') : '';
        return '<div class="row element-row mb-2" data-index="' + index + '">'
            + '<div class="col-5">'
            +   '<label class="form-label small">Element</label>'
            +   '<select class="form-select form-select-sm el-element-id">' + buildElementOptions(element ? element.element_name_id : '') + '</select>'
            + '</div>'
            + '<div class="col-3">'
            +   '<label class="form-label small">Entry Type</label>'
            +   '<select class="form-select form-select-sm el-entry-type">'
            +   '<option value="2"' + (entryType == 2 ? ' selected' : '') + '>Allowance</option>'
            +   '<option value="1"' + (entryType == 1 ? ' selected' : '') + '>Deduction</option>'
            +   '</select>'
            + '</div>'
            + '<div class="col-3">'
            +   '<label class="form-label small">Amount</label>'
            +   '<input type="number" class="form-control form-control-sm el-amount" value="' + (element ? escHtml(element.amount) : '') + '" min="0" step="0.01">'
            + '</div>'
            + '<div class="col-1 d-flex align-items-end">'
            +   '<button type="button" class="btn btn-sm btn-light btn-remove-element" title="Remove">'
            +   '<span class="material-symbols-outlined" style="font-size:16px;">remove</span></button>'
            + '</div>'
            + '</div>';
    }

    $(document).on('click', '.btn-edit-elements', function() {
        currentModalContractId = $(this).data('id');
        var name = $(this).data('name');
        var elements = [];
        try {
            elements = JSON.parse($(this).attr('data-elements') || '[]');
        } catch(e) { elements = []; }

        $('#elements-modal-staff-name').text(name);
        var $repeater = $('#elements-repeater');
        $repeater.empty();

        if (elements.length === 0) {
            $repeater.append(buildElementRow(0, null));
        } else {
            elements.forEach(function(el, i) {
                // Map element_name_id from the contract elements data
                var elObj = {
                    element_name_id: el.element_name ? null : null, // will fallback to name match below
                    amount: el.amount,
                    entry_type: el.entry_type,
                };
                // Try to find element_id by matching element_name against payrollElements
                var found = payrollElements.find(function(p) {
                    return p.element_name === el.element_name;
                });
                if (found) elObj.element_name_id = found.id;
                $repeater.append(buildElementRow(i, elObj));
            });
        }

        var modal = new bootstrap.Modal(document.getElementById('elementsModal'));
        modal.show();
    });

    $(document).on('click', '#btn-add-element', function() {
        var count = $('#elements-repeater .element-row').length;
        $('#elements-repeater').append(buildElementRow(count, null));
    });

    $(document).on('click', '.btn-remove-element', function() {
        $(this).closest('.element-row').remove();
    });

    $('#btn-save-elements').on('click', function() {
        if (!currentModalContractId) return;

        var elements = [];
        $('#elements-repeater .element-row').each(function() {
            var elementId  = $(this).find('.el-element-id').val();
            var entryType  = $(this).find('.el-entry-type').val();
            var amount     = $(this).find('.el-amount').val();
            if (elementId) {
                elements.push({
                    element_id:    elementId,
                    entry_type_id: entryType,
                    amount:        amount || 0,
                });
            }
        });

        var url = elementsUpdateUrlTemplate.replace(':id', currentModalContractId);
        $('#elements-spinner').show();
        $('#btn-save-elements').prop('disabled', true);

        $.ajax({
            type: 'PUT',
            url: url,
            data: JSON.stringify({ elements: elements }),
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            success: function(response) {
                if (response.success) {
                    bootstrap.Modal.getInstance(document.getElementById('elementsModal')).hide();
                    // Update the stored data-elements on the button so re-open shows fresh data
                    // Rebuild display element names for data-elements attribute
                    var updatedElements = elements.map(function(el) {
                        var found = payrollElements.find(function(p) { return p.id == el.element_id; });
                        return {
                            element_name: found ? found.element_name : '',
                            element_name_id: el.element_id,
                            entry_type: el.entry_type_id,
                            amount: el.amount,
                        };
                    });
                    $('[data-id="' + currentModalContractId + '"].btn-edit-elements')
                        .attr('data-elements', JSON.stringify(updatedElements));
                } else {
                    alert('Failed to save elements: ' + (response.error || 'Unknown error'));
                }
            },
            error: function() {
                alert('Failed to save elements. Please try again.');
            },
            complete: function() {
                $('#elements-spinner').hide();
                $('#btn-save-elements').prop('disabled', false);
            }
        });
    });
</script>
@endsection
