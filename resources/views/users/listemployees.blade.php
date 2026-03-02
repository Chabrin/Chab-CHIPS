@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Employee Listing</h4>
                    </div>


                    <div class="card-body">
                        @include('components.error_reports')
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="basic-1">
                                <thead>
                                    <tr>
                                        <th>Employee No</th>
                                        <th>First Name</th>
                                        <th>Surname</th>
                                        <th>Email Address</th>
                                        <th>Status</th>
                                        <th>National ID</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($employees as $emp)
                                    <tr>
                                        <td>{{ $emp->employee_no }}</td>
                                        <td>{{ $emp->first_name }}</td>
                                        <td>{{ $emp->surname }}</td>
                                        <td>{{ $emp->email_address }}</td>
                                        <td>
                                            @if($emp->confirmed)
                                            <span class="badge alert-light-success">Confirmed</span>
                                            @else
                                            <span class="badge alert-light-danger">Pending</span>
                                            @endif
                                        </td>
                                        <td>{{ $emp->national_id }}</td>
                                        <td>
                                            <ul class="action">
                                                <li class="view">
                                                    <a href="{{ url('/employee/view/'.$emp->encrypted_id) }}"><span class="material-symbols-outlined">eye_tracking</span></a>
                                                </li>
                                                <li class="edit">
                                                    <a href="{{ url('/employee/edit/'.$emp->encrypted_id) }}"><span class="material-symbols-outlined">person_edit</span></a>
                                                </li>
                                            </ul>
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
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        // initialize DataTables on the existing HTML table (no AJAX)
        let table = $('#basic-1').DataTable({
            paging: false,
            info: false,
            lengthChange: false
        });
    });
</script>
@endpush