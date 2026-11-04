@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center flex-wrap gap-2">
                        <h5 class="mb-0">Monthly Payroll Items</h5>
                        <div class="ms-auto d-flex align-items-center gap-2">
                            <label for="periodSelect" class="form-label mb-0">Period</label>
                            <select class="form-select form-select-sm" id="periodSelect" style="min-width:260px;">
                                @foreach($periods as $period)
                                <option value="{{ $period['id'] }}" {{ (int) $selected === (int) $period['id'] ? 'selected' : '' }}>{{ $period['label'] }}</option>
                                @endforeach
                            </select>
                            <a href="{{ route('payroll.processing') }}" class="btn btn-light btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined me-1" style="font-size:18px;">arrow_back</span> Payroll Processing
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="periodInfo" class="mb-3 text-muted">Loading...</div>

                        <ul class="nav nav-tabs" role="tablist">
                            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-check" type="button" role="tab">Check before running</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-items" type="button" role="tab">One-time items</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-recurring" type="button" role="tab" id="recurringTabButton">Recurring items per staff</button></li>
                        </ul>

                        <div class="tab-content pt-3">
                            <div class="tab-pane fade show active" id="tab-check" role="tabpanel">
                                <div id="checkSummary" class="row g-2 mb-3"></div>
                                <p class="text-muted small">This only reports. It never stops a run.</p>
                                <div id="checkSections"></div>
                            </div>

                            <div class="tab-pane fade" id="tab-items" role="tabpanel">
                                <p class="text-muted small">One-time items apply to this period only. They are added when the period is run, and stay here if the run is reversed and run again.</p>
                                <div id="itemForm" class="row g-2 align-items-end mb-3 d-none">
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">Item</label>
                                        <select class="form-select form-select-sm" id="itemElement"></select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">Staff</label>
                                        <select class="form-select form-select-sm" id="itemStaff"></select>
                                        <div class="form-check mt-1">
                                            <input class="form-check-input" type="checkbox" id="itemAll">
                                            <label class="form-check-label small" for="itemAll">Apply to all staff in the run</label>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small mb-1">Amount</label>
                                        <input type="number" class="form-control form-control-sm" id="itemAmount" min="0" step="0.01">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">Note (optional)</label>
                                        <input type="text" class="form-control form-control-sm" id="itemNote" maxlength="255">
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-primary btn-sm w-100" id="addItemBtn">Add</button>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle" id="itemsTable">
                                        <thead><tr><th>Staff</th><th>Item</th><th>Type</th><th style="width:160px;">Amount</th><th>Note</th><th style="width:150px;"></th></tr></thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="tab-recurring" role="tabpanel">
                                <p class="text-muted small">These are the recurring items on each contract in this run. A change here is saved to the contract, so it applies to this period and every one after it.</p>
                                <div id="recurringForm" class="row g-2 align-items-end mb-3 d-none">
                                    <div class="col-md-4">
                                        <label class="form-label small mb-1">Staff</label>
                                        <select class="form-select form-select-sm" id="recurringContract"></select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small mb-1">Item</label>
                                        <select class="form-select form-select-sm" id="recurringElement"></select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">Amount</label>
                                        <input type="number" class="form-control form-control-sm" id="recurringAmount" min="0" step="0.01">
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-primary btn-sm w-100" id="addRecurringBtn">Add</button>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle" id="recurringTable">
                                        <thead><tr><th>Staff</th><th>Item</th><th>Type</th><th style="width:160px;">Amount</th><th style="width:150px;"></th></tr></thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        const base = "{{ url('/ajax/payroll/monthly') }}";
        const csrf = $('meta[name="csrf-token"]').attr('content');
        let state = null;
        let recurring = null;

        function esc(value) {
            return $('<span>').text(value === null || value === undefined ? '' : value).html();
        }

        function fmt(n) {
            return Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function typeName(type) {
            return String(type) === '2' ? 'Allowance' : 'Deduction';
        }

        function api(method, url, data) {
            return $.ajax({ url: url, type: method, data: data || {}, dataType: 'json', headers: { 'X-CSRF-TOKEN': csrf } });
        }

        function fail(xhr) {
            let message = 'Something went wrong.';
            if (xhr.responseJSON && xhr.responseJSON.errors) {
                message = Object.values(xhr.responseJSON.errors).flat().join('<br>');
            } else if (xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message)) {
                message = esc(xhr.responseJSON.error || xhr.responseJSON.message);
            }
            toastr.error(message);
        }

        function periodId() {
            return $('#periodSelect').val();
        }

        // ── Load a period ───────────────────────────────────────────────
        function load() {
            if (!periodId()) {
                $('#periodInfo').text('There are no payroll periods yet. Add them in the Payroll Calendar.');
                return;
            }
            history.replaceState(null, '', '?period=' + periodId());
            $('#periodInfo').text('Loading...');
            recurring = null;
            $.getJSON(base + '/' + periodId()).done(function(res) {
                state = res;
                renderHeader();
                renderCheck();
                renderItems();
                if ($('#tab-recurring').hasClass('active')) {
                    loadRecurring();
                }
            }).fail(function(xhr) {
                $('#periodInfo').text('');
                fail(xhr);
            });
        }

        function editable() {
            return state && !state.locked;
        }

        function renderHeader() {
            let html = '<strong>' + esc(state.period.label) + '</strong>';
            if (state.run_status) {
                html += ' <span class="badge badge-light-primary ms-2">' + esc(state.run_status) + '</span>';
            }
            if (state.locked) {
                html += '<div class="alert alert-warning mt-2 mb-0">This period has already been processed, so its one-time items are locked. Reverse or discard the run on Payroll Processing to change them.</div>';
            }
            $('#periodInfo').removeClass('text-muted').html(html);
        }

        // ── Check before running ───────────────────────────────────────
        function renderCheck() {
            const c = state.check;
            $('#checkSummary').html(
                card('Staff in this run', c.summary.staff_in_run) +
                card('One-time items entered', c.summary.items) +
                card('One-time allowances', fmt(c.summary.one_time_allowances)) +
                card('One-time deductions', fmt(c.summary.one_time_deductions))
            );

            if (!c.sections.length) {
                $('#checkSections').html('<div class="alert alert-success">Nothing to flag for this period.</div>');
                return;
            }

            let html = '';
            c.sections.forEach(function(section) {
                const border = section.level === 'warning' ? 'border-warning' : 'border-info';
                const badge = section.level === 'warning' ? 'badge-light-warning' : 'badge-light-info';
                html += '<div class="card border ' + border + ' mb-3"><div class="card-body py-3">'
                    + '<div class="d-flex align-items-center mb-1"><h6 class="mb-0">' + esc(section.title) + '</h6>'
                    + '<span class="badge ' + badge + ' ms-2">' + section.count + '</span></div>'
                    + '<p class="text-muted small mb-2">' + esc(section.hint) + '</p>'
                    + '<table class="table table-sm mb-0"><tbody>';
                section.rows.forEach(function(row) {
                    html += '<tr><td>' + esc(row.name) + '</td><td class="text-muted">' + esc(row.detail) + '</td></tr>';
                });
                if (section.count > section.rows.length) {
                    html += '<tr><td colspan="2" class="text-muted">And ' + (section.count - section.rows.length) + ' more.</td></tr>';
                }
                html += '</tbody></table></div></div>';
            });
            $('#checkSections').html(html);
        }

        function card(label, value) {
            return '<div class="col-6 col-md-3"><div class="border rounded p-2"><div class="text-muted small">' + esc(label)
                + '</div><div class="fs-5 fw-semibold">' + esc(value) + '</div></div></div>';
        }

        // ── One-time items ─────────────────────────────────────────────
        function option(value, text) {
            return '<option value="' + esc(value) + '">' + esc(text) + '</option>';
        }

        function renderItems() {
            const canEdit = state.can_edit_items && editable();
            $('#itemForm').toggleClass('d-none', !canEdit);

            if (canEdit) {
                $('#itemElement').html(option('', 'Select item') + state.elements.map(function(e) {
                    return option(e.id, e.name + ' (' + typeName(e.type) + ')');
                }).join(''));
                $('#itemStaff').html(option('', 'Select staff') + state.staff.map(function(s) {
                    return option(s.id, s.name + (s.number ? ' - ' + s.number : ''));
                }).join(''));
                if (!state.elements.length) {
                    $('#itemElement').html(option('', 'No one-time items yet. Set an element to One-time under Payroll Elements.'));
                }
            }

            const $body = $('#itemsTable tbody').empty();
            if (!state.items.length) {
                $body.append('<tr><td colspan="6" class="text-muted">No one-time items for this period.</td></tr>');
                return;
            }
            state.items.forEach(function(item) {
                const disabled = canEdit ? '' : ' disabled';
                $body.append('<tr data-id="' + item.id + '">'
                    + '<td>' + esc(item.staff) + (item.staff_number ? ' <span class="text-muted">' + esc(item.staff_number) + '</span>' : '') + '</td>'
                    + '<td>' + esc(item.element) + '</td>'
                    + '<td>' + typeName(item.type) + '</td>'
                    + '<td><input type="number" class="form-control form-control-sm item-amount" min="0" step="0.01" value="' + esc(item.amount) + '"' + disabled + '></td>'
                    + '<td><input type="text" class="form-control form-control-sm item-note" maxlength="255" value="' + esc(item.note) + '"' + disabled + '></td>'
                    + '<td>' + (canEdit ? '<button type="button" class="btn btn-light btn-sm save-item">Save</button> <button type="button" class="btn btn-light btn-sm text-danger delete-item">Delete</button>' : '') + '</td>'
                    + '</tr>');
            });
        }

        $('#itemAll').on('change', function() {
            $('#itemStaff').prop('disabled', this.checked);
        });

        $('#addItemBtn').on('click', function() {
            api('POST', base + '/' + periodId() + '/items', {
                element_id: $('#itemElement').val(),
                staff_id: $('#itemStaff').val(),
                apply_to_all: $('#itemAll').is(':checked') ? 1 : 0,
                amount: $('#itemAmount').val(),
                note: $('#itemNote').val()
            }).done(function() {
                toastr.success('Item added');
                $('#itemAmount, #itemNote').val('');
                load();
            }).fail(fail);
        });

        $(document).on('click', '.save-item', function() {
            const $row = $(this).closest('tr');
            api('POST', base + '/items/' + $row.data('id'), {
                amount: $row.find('.item-amount').val(),
                note: $row.find('.item-note').val()
            }).done(function() {
                toastr.success('Saved');
                load();
            }).fail(fail);
        });

        $(document).on('click', '.delete-item', function() {
            if (!confirm('Remove this one-time item?')) return;
            api('DELETE', base + '/items/' + $(this).closest('tr').data('id')).done(function() {
                toastr.success('Removed');
                load();
            }).fail(fail);
        });

        // ── Recurring items per staff ──────────────────────────────────
        function loadRecurring() {
            $.getJSON(base + '/' + periodId() + '/recurring').done(function(res) {
                recurring = res;
                renderRecurring();
            }).fail(fail);
        }

        $('#recurringTabButton').on('shown.bs.tab', function() {
            if (!recurring) loadRecurring();
        });

        function renderRecurring() {
            const canEdit = state.can_edit_recurring;
            $('#recurringForm').toggleClass('d-none', !canEdit);

            if (canEdit) {
                $('#recurringContract').html(option('', 'Select staff') + recurring.contracts.map(function(c) {
                    return option(c.id, c.name + (c.number ? ' - ' + c.number : ''));
                }).join(''));
                $('#recurringElement').html(option('', 'Select item') + recurring.elements.map(function(e) {
                    return option(e.id, e.name + ' (' + typeName(e.type) + ')');
                }).join(''));
            }

            const $body = $('#recurringTable tbody').empty();
            if (!recurring.rows.length) {
                $body.append('<tr><td colspan="5" class="text-muted">No recurring items on the contracts in this run.</td></tr>');
                return;
            }
            recurring.rows.forEach(function(row) {
                const disabled = canEdit ? '' : ' disabled';
                $body.append('<tr data-id="' + row.row_id + '">'
                    + '<td>' + esc(row.staff) + (row.staff_number ? ' <span class="text-muted">' + esc(row.staff_number) + '</span>' : '') + '</td>'
                    + '<td>' + esc(row.element) + '</td>'
                    + '<td>' + typeName(row.type) + '</td>'
                    + '<td><input type="number" class="form-control form-control-sm recurring-amount" min="0" step="0.01" value="' + esc(row.amount) + '"' + disabled + '></td>'
                    + '<td>' + (canEdit ? '<button type="button" class="btn btn-light btn-sm save-recurring">Save</button> <button type="button" class="btn btn-light btn-sm text-danger remove-recurring">Remove</button>' : '') + '</td>'
                    + '</tr>');
            });
        }

        // Offer the amount the element's formula gives when the amount is still empty
        $('#recurringElement').on('change', function() {
            if ($('#recurringAmount').val() !== '') return;
            const el = (recurring.elements || []).find(function(e) { return String(e.id) === String($('#recurringElement').val()); });
            if (el && el.formula === 1 && el.default > 0) {
                $('#recurringAmount').val(el.default.toFixed(2));
            }
        });

        $('#addRecurringBtn').on('click', function() {
            api('POST', base + '/contracts/' + $('#recurringContract').val() + '/elements', {
                element_id: $('#recurringElement').val(),
                amount: $('#recurringAmount').val()
            }).done(function() {
                toastr.success('Added to the contract');
                $('#recurringAmount').val('');
                loadRecurring();
                load();
            }).fail(fail);
        });

        $(document).on('click', '.save-recurring', function() {
            const $row = $(this).closest('tr');
            api('POST', base + '/contract-elements/' + $row.data('id'), { amount: $row.find('.recurring-amount').val() }).done(function() {
                toastr.success('Saved to the contract');
                loadRecurring();
                load();
            }).fail(fail);
        });

        $(document).on('click', '.remove-recurring', function() {
            if (!confirm('Remove this item from the contract? It will stop in this period and every one after it.')) return;
            api('DELETE', base + '/contract-elements/' + $(this).closest('tr').data('id')).done(function() {
                toastr.success('Removed from the contract');
                loadRecurring();
                load();
            }).fail(fail);
        });

        $('#periodSelect').on('change', load);
        load();
    });
</script>
@endsection
