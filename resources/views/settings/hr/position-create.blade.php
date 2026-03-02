@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border">
                        <h4 class="mb-0">Create New Position</h4>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('settings.hr.positions.store') }}" method="POST">
                            @csrf

                            <div class="row mb-3">
                                <div class="col-md-6 mb-3">
                                    <label for="position_name" class="form-label">Position Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('position_name') is-invalid @enderror"
                                        id="position_name" name="position_name" value="{{ old('position_name') }}" required>
                                    @error('position_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="basic_salary" class="form-label">Basic Salary</label>
                                    <input type="number" step="0.01" class="form-control @error('basic_salary') is-invalid @enderror"
                                        id="basic_salary" name="basic_salary" value="{{ old('basic_salary') }}">
                                    @error('basic_salary')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="reports_to_id" class="form-label">Reports To</label>
                                    <select class="form-select @error('reports_to_id') is-invalid @enderror" id="reports_to_id" name="reports_to_id">
                                        <option value="">Select Position</option>
                                        @foreach($positions as $pos)
                                        <option value="{{ $pos->id }}" {{ old('reports_to_id') == $pos->id ? 'selected' : '' }}>
                                            {{ $pos->position_name }}
                                        </option>
                                        @endforeach
                                    </select>
                                    @error('reports_to_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="salary_account_id" class="form-label">Salary Account</label>
                                    <select class="form-select @error('salary_account_id') is-invalid @enderror" id="salary_account_id" name="salary_account_id">
                                        <option value="">Select Account</option>
                                        @foreach($accounts as $account)
                                        <option value="{{ $account->id }}" {{ old('salary_account_id') == $account->id ? 'selected' : '' }}>
                                            {{ $account->full_account }}
                                        </option>
                                        @endforeach
                                    </select>
                                    @error('salary_account_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ route('settings.hr.positions') }}" class="btn btn-light d-flex align-items-center">
                                    <span class="material-symbols-outlined">close</span>
                                    <span class="ms-1">Cancel</span>
                                </a>
                                @permission('create_staff_positions')
                                <button type="submit" class="btn btn-primary d-flex align-items-center">
                                    <span class="material-symbols-outlined">check_circle</span>
                                    <span class="ms-1">Save Position</span>
                                </button>
                                @endpermission
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection