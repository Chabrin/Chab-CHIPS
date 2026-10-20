@extends('layouts.main')

@section('styles')
<style>
#typesTable th { font-size: .74rem; white-space: nowrap; color: #6b7a90; }
#typesTable td { font-size: .82rem; vertical-align: middle; }
#typesTable td.code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; color: #5b6475; white-space: nowrap; }
#typesTable td.r { text-align: right; font-variant-numeric: tabular-nums; }
#typesTable tr.nc td { background: #fafbfd; }
#typesTable td.notes { min-width: 300px; }
.pattern { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .76rem; color: #8a93a4; }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <div>
                            <h5 class="mb-0">Landlord Entry Types</h5>
                            <small class="text-muted">Which kinds of landlord ledger entry count toward a landlord's balance.</small>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm ms-auto d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addModal">
                            <span class="material-symbols-outlined" style="font-size:18px;">add</span><span class="ms-1">Add entry type</span>
                        </button>
                    </div>
                    <div class="card-body pt-0">
                        <div class="alert alert-light border small mb-3">
                            A landlord's balance is credits minus debits over the entries whose type <strong>counts</strong>. Entries of a type that does not count
                            are still shown on the statement and in Client Ledgers, greyed and marked &ldquo;not counted&rdquo;. <strong>Changing a setting changes every
                            landlord's balance straight away.</strong> A pattern lets one entry code hold two kinds of entry: an entry whose description matches the
                            pattern (<span class="pattern">%</span> = any text) takes that row's setting, otherwise the code's plain row applies.
                            @if($unregistered > 0)
                            <br><strong>{{ number_format($unregistered) }}</strong> ledger {{ $unregistered === 1 ? 'entry has a code' : 'entries have codes' }} not in this list; they count until a row is added.
                            @endif
                        </div>

                        @if($errors->any())
                        <div class="alert alert-danger small">{{ $errors->first() }}</div>
                        @endif

                        <div class="table-responsive">
                            <table class="table table-sm table-hover" id="typesTable">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Name</th>
                                        <th>Kind</th>
                                        <th>Counts toward balance</th>
                                        <th class="text-end">Ledger entries</th>
                                        <th>Notes</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($types as $type)
                                    <tr class="{{ $type->counts_toward_balance ? '' : 'nc' }}">
                                        <td class="code">{{ $type->code === '' ? '(blank)' : $type->code }}</td>
                                        <td>
                                            {{ $type->name }}
                                            @if($type->description_pattern)
                                            <div class="pattern">description like {{ $type->description_pattern }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $kinds[$type->kind] ?? $type->kind }}</td>
                                        <td>
                                            @if($type->counts_toward_balance)
                                            <span class="badge bg-success">Counts</span>
                                            @else
                                            <span class="badge bg-secondary">Not counted</span>
                                            @endif
                                        </td>
                                        <td class="r">{{ number_format($type->ledger_rows) }}</td>
                                        <td class="text-muted small notes">{{ $type->notes }}</td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2 edit-btn"
                                                data-id="{{ $type->id }}"
                                                data-code="{{ $type->code === '' ? '(blank)' : $type->code }}"
                                                data-pattern="{{ $type->description_pattern }}"
                                                data-name="{{ $type->name }}"
                                                data-kind="{{ $type->kind }}"
                                                data-counts="{{ $type->counts_toward_balance ? 1 : 0 }}"
                                                data-notes="{{ $type->notes }}">Edit</button>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST" id="editForm" action="">
            @csrf
            @method('PUT')
            <div class="modal-header py-2">
                <h6 class="modal-title" id="editModalLabel">Edit entry type <span id="edit_code" class="text-muted"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="small text-muted mb-2" id="edit_pattern"></div>
                <div class="mb-2">
                    <label class="form-label mb-1" for="edit_name">Name</label>
                    <input type="text" class="form-control form-control-sm" id="edit_name" name="name" maxlength="100" required>
                </div>
                <div class="mb-2">
                    <label class="form-label mb-1" for="edit_kind">Kind</label>
                    <select class="form-select form-select-sm" id="edit_kind" name="kind">
                        @foreach($kinds as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-check form-switch mb-2">
                    <input type="hidden" name="counts_toward_balance" value="0">
                    <input class="form-check-input" type="checkbox" id="edit_counts" name="counts_toward_balance" value="1">
                    <label class="form-check-label" for="edit_counts">Counts toward the landlord's balance</label>
                </div>
                <div class="mb-1">
                    <label class="form-label mb-1" for="edit_notes">Notes</label>
                    <input type="text" class="form-control form-control-sm" id="edit_notes" name="notes" maxlength="255">
                </div>
                <div class="small text-warning mt-2">Saving changes every landlord's balance straight away.</div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Add -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST" action="{{ route('finance.landlord.entry.types.store') }}">
            @csrf
            <div class="modal-header py-2">
                <h6 class="modal-title" id="addModalLabel">Add entry type</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-2">
                    <div class="col-5">
                        <label class="form-label mb-1" for="add_code">Entry code</label>
                        <input type="text" class="form-control form-control-sm" id="add_code" name="code" maxlength="20" required value="{{ old('code') }}">
                    </div>
                    <div class="col-7">
                        <label class="form-label mb-1" for="add_pattern">Description pattern <span class="text-muted">(optional)</span></label>
                        <input type="text" class="form-control form-control-sm" id="add_pattern" name="description_pattern" maxlength="100" placeholder="%Opening Balance%" value="{{ old('description_pattern') }}">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label mb-1" for="add_name">Name</label>
                    <input type="text" class="form-control form-control-sm" id="add_name" name="name" maxlength="100" required value="{{ old('name') }}">
                </div>
                <div class="mb-2">
                    <label class="form-label mb-1" for="add_kind">Kind</label>
                    <select class="form-select form-select-sm" id="add_kind" name="kind">
                        @foreach($kinds as $value => $label)
                        <option value="{{ $value }}" @selected(old('kind', 'transaction') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-check form-switch mb-2">
                    <input type="hidden" name="counts_toward_balance" value="0">
                    <input class="form-check-input" type="checkbox" id="add_counts" name="counts_toward_balance" value="1" checked>
                    <label class="form-check-label" for="add_counts">Counts toward the landlord's balance</label>
                </div>
                <div class="mb-1">
                    <label class="form-label mb-1" for="add_notes">Notes</label>
                    <input type="text" class="form-control form-control-sm" id="add_notes" name="notes" maxlength="255" value="{{ old('notes') }}">
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Add</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function () {
    const updateUrl = '{{ url('/finance/landlord-entry-types') }}/';

    $('#typesTable').on('click', '.edit-btn', function () {
        const b = $(this);
        $('#editForm').attr('action', updateUrl + b.data('id'));
        $('#edit_code').text(String(b.data('code')));
        $('#edit_pattern').text(b.data('pattern') ? 'Only entries whose description is like ' + b.data('pattern') : '');
        $('#edit_name').val(b.attr('data-name'));
        $('#edit_kind').val(b.data('kind'));
        $('#edit_counts').prop('checked', String(b.data('counts')) === '1');
        $('#edit_notes').val(b.attr('data-notes') || '');
        $('#editModal').modal('show');
    });

    @if($errors->any() && old('code') !== null)
    $('#addModal').modal('show');
    @endif
});
</script>
@endsection
