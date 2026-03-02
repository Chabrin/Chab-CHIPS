@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Edit Employee</h4>
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('listemployees') }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back_ios_new</span>
                                <span class="ms-1">Close</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        @include('components.error_reports')
                        @if(isset($employee))
                        <form method="POST" action="{{ route('updateemployee', $employee->employee_no) }}">
                            @csrf
                            <div class="row">
                                <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                                    <label for="employee_no">Employee No <span style="color:red">*</span></label>
                                    <input type="text" name="employee_no" class="form-control" value="{{ old('employee_no', $employee->employee_no) }}" required readonly>
                                </div>
                                <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                                    <label for="first_name">First Name <span style="color:red">*</span></label>
                                    <input type="text" name="first_name" class="form-control" value="{{ old('first_name', $employee->first_name) }}" required>
                                </div>
                                <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                                    <label for="surname">Surname <span style="color:red">*</span></label>
                                    <input type="text" name="surname" class="form-control" value="{{ old('surname', $employee->surname) }}" required>
                                </div>
                                <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                                    <label for="other_name">Other Name <span style="color:red">*</span></label>
                                    <input type="text" name="other_name" class="form-control" value="{{ old('other_name', $employee->other_name) }}" required>
                                </div>
                                <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                                    <label for="national_id">National ID <span style="color:red">*</span></label>
                                    <input type="text" name="national_id" class="form-control" value="{{ old('national_id', $employee->national_id) }}" required>
                                </div>
                                <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                                    <label for="email_address">Email Address <span style="color:red">*</span></label>
                                    <input type="email" name="email_address" class="form-control" value="{{ old('email_address', $employee->email_address) }}" required>
                                </div>
                                <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                                    <label for="phone_no">Phone No <span style="color:red">*</span></label>
                                    <input type="text" name="phone_no" class="form-control" value="{{ old('phone_no', $employee->phone_no) }}" required>
                                </div>
                                <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                                    <label for="dob">Date of Birth <span style="color:red">*</span></label>
                                    <input type="date" name="dob" class="form-control" value="{{ old('dob', $employee->dob) }}" required>
                                </div>
                                <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                                    <label for="date_employed">Date Employed <span style="color:red">*</span></label>
                                    <input type="date" name="date_employed" class="form-control" value="{{ old('date_employed', $employee->date_employed) }}" required>
                                </div>
                                <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                                    <label for="department_id">Department <span style="color:red">*</span></label>
                                    <select name="department_id" class="form-control" required>
                                        <option value="">Select Department</option>
                                        @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}" {{ old('department_id', $employee->dept_id) == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                                    <label for="gender_id">Gender <span style="color:red">*</span></label>
                                    <select name="gender_id" class="form-control" required>
                                        <option value="">Select Gender</option>
                                        @foreach($genders as $gender)
                                        <option value="{{ $gender->id }}" {{ old('gender_id', $employee->gender_id) == $gender->id ? 'selected' : '' }}>{{ $gender->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                                    <label for="marital_status_id">Marital Status <span style="color:red">*</span></label>
                                    <select name="marital_status_id" class="form-control" required>
                                        <option value="">Select Marital Status</option>
                                        @foreach($marital_statuses as $status)
                                        <option value="{{ $status->id }}" {{ old('marital_status_id', $employee->marital_status) == $status->id ? 'selected' : '' }}>{{ $status->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                                    <label for="post_address">Post Address</label>
                                    <input type="text" name="post_address" class="form-control" value="{{ old('post_address', $employee->post_address) }}">
                                </div>
                                <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                                    <label for="physical_address">Physical Address</label>
                                    <input type="text" name="physical_address" class="form-control" value="{{ old('physical_address', $employee->physical_address) }}">
                                </div>
                                <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                                    <label for="terms">Employment Terms <span style="color:red">*</span></label>
                                    <select name="terms" class="form-control" required>
                                        <option value="">Select Terms</option>
                                        @foreach($terms as $term)
                                        <option value="{{ $term->id }}" {{ old('terms', $employee->terms) == $term->id ? 'selected' : '' }}>{{ $term->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary d-flex align-items-center">
                                <span class="material-symbols-outlined">
                                    update
                                </span>
                                Update Employee</button>
                        </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection