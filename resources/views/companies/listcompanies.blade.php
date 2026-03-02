@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">Company Listing</h4>
                        <div class="ms-auto d-flex gap-2">
                            @permission('create_companies')
                            <a href="{{ route('addcompany') }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">add_business</span>
                                <span class="ms-1">Add Company</span>
                            </a>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        @include('components.error_reports')
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="basic-1">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Company Code</th>
                                        <th>Company Name</th>
                                        <th>Email</th>
                                        <th>Website</th>
                                        <th>Contact</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($companies as $company)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $company->company_code }}</td>
                                        <td>{{ $company->company_name }}</td>
                                        <td>{{ $company->email }}</td>
                                        <td>{{ $company->website }}</td>
                                        <td>{{ $company->contact_name }}</td>
                                        <td>
                                            <ul class="action">
                                                @permission('read_companies')
                                                <li class="view">
                                                    <a href="{{ route('viewcompany', Crypt::encrypt($company->id)) }}"><span class="material-symbols-outlined">open_in_browser</span></a>
                                                </li>
                                                @endpermission
                                                @permission('update_companies')
                                                <li class="edit">
                                                    <a href="{{ route('editcompany', Crypt::encrypt($company->id)) }}">
                                                        <span class="material-symbols-outlined">
                                                            edit_square
                                                        </span>
                                                    </a>
                                                </li>
                                                @endpermission
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