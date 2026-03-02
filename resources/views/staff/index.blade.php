@extends('layouts.main')
@section('content')

@php
$canReadStaff = auth()->user()->hasPermission('read_staff');
$canUpdateStaff = auth()->user()->hasPermission('update_staff');
@endphp
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Staff Management</h5>
                        <div class="ms-auto d-flex gap-2">
                            @permission('create_staff')
                            <a href="{{ route('staff.create') }}" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Add Staff</span>
                            </a>
                            @endpermission
                        </div>
                    </div>
                    <div class="card-body">
                        @if(session('success'))
                        <div class="alert alert-light-success alert-dismissible fade show" role="alert">
                            <strong>Success!</strong> {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif
                        @if(session('error'))
                        <div class="alert alert-light-danger alert-dismissible fade show" role="alert">
                            <strong>Error!</strong> {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        @endif

                        <!-- Preloader -->
                        <div id="staff-preloader" class="text-center my-5">
                            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3 text-muted">Loading employees...</p>
                        </div>

                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="staff-table" style="visibility: hidden;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Staff No</th>
                                        <th>Full Names</th>
                                        <th>Position</th>
                                        <th>Gender</th>
                                        <th>Mobile Number</th>
                                        <th>National ID</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($staff as $index => $member)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $member->staff_number ?? '-' }}</td>
                                        <td>
                                            @if($canReadStaff)
                                            <a class="staff-link" href="{{ route('staff.show', $member->encrypted_id) }}">
                                                {{ $member->full_names }}
                                            </a>
                                            @else
                                            {{ $member->full_names }}
                                            @endif
                                        </td>
                                        <td>{{ $member->position->position_name ?? '-' }}</td>
                                        <td>{{ $member->gender ?? '-' }}</td>
                                        <td>{{ $member->mobile_number ?? '-' }}</td>

                                        <td>{{ $member->national_id ?? '-' }}</td>
                                        <td>
                                            <div class="d-flex flex-row gap-1">
                                                @if($canReadStaff)
                                                <a class="staff-link" href="{{ route('staff.show', $member->encrypted_id) }}" title="View">
                                                    <span class="material-symbols-outlined view-icon" style="font-size:18px;">visibility</span>
                                                </a>
                                                <div class="spinner-border spinner-border-sm text-primary row-spinner" role="status" style="display: none;">
                                                    <span class="visually-hidden">Loading...</span>
                                                </div>
                                                @endif

                                                @if($canUpdateStaff)
                                                <a href="{{ route('staff.edit', $member->encrypted_id) }}" title="Edit">
                                                    <span class="material-symbols-outlined" style="font-size:18px;">edit_document</span>
                                                </a>
                                                @endif
                                            </div>
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


<script src="{{ asset('assets/js/jquery.min.js') }}"></script>
<script>
    $(document).ready(function() {
        $('#staff-table').DataTable({
            paging: true,
            info: true,
            lengthChange: true,
            searching: true,
            ordering: true,
            order: [
                [2, 'asc']
            ], // Sort by full names
            initComplete: function() {
                // Hide preloader and show table
                $('#staff-preloader').hide();
                $('#staff-table').css('visibility', 'visible');
            }
        });

        // Handle row-level spinner on staff link click
        $(document).on('click', '.staff-link', function(e) {
            e.preventDefault();
            var row = $(this).closest('tr');
            var url = $(this).attr('href');

            // Hide view icon and show spinner
            row.find('.view-icon').hide();
            row.find('.row-spinner').show();

            // Disable all links in the row
            row.find('a').css('pointer-events', 'none').css('opacity', '0.6');

            // Navigate to the details page
            window.location.href = url;
        });
    });
</script>
@endsection