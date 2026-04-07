@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h4 class="mb-0">My Profile</h4>
                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">arrow_back</span>
                                <span class="ms-1">Back to Dashboard</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">

                            <div class="col-lg-4">
                                <div class="d-flex align-items-start gap-2">
                                    <span class="material-symbols-outlined" style="font-size:28px;">person</span>
                                    <div>
                                        <div class="small text-muted">Name</div>
                                        <div class="fw-bold">
                                            @if($user->employee)
                                            {{ $user->employee->first_name }} {{ $user->employee->last_name }}
                                            @else
                                            {{ $user->username }}
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="d-flex align-items-start gap-2">
                                    <span class="material-symbols-outlined" style="font-size:28px;">account_circle</span>
                                    <div>
                                        <div class="small text-muted">Username</div>
                                        <div class="fw-bold">
                                            <span class="badge bg-primary text-white rounded-pill px-3 py-2">{{ $user->username }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="d-flex align-items-start gap-2">
                                    <span class="material-symbols-outlined" style="font-size:28px;">email</span>
                                    <div>
                                        <div class="small text-muted">Email</div>
                                        <div class="fw-bold">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="d-flex align-items-start gap-2">
                                    <span class="material-symbols-outlined" style="font-size:28px;">work</span>
                                    <div>
                                        <div class="small text-muted">Role</div>
                                        <div class="fw-bold">
                                            @if(isset($user->roles) && $user->roles->isNotEmpty())
                                            @foreach($user->roles as $role)
                                            <span class="badge bg-primary text-white">{{ $role->display_name ?? $role->name }}</span>
                                            @endforeach
                                            @else
                                            <span class="badge bg-secondary text-white">No Role Assigned</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="d-flex align-items-start gap-2">
                                    <span class="material-symbols-outlined" style="font-size:28px;">perm_identity</span>
                                    <div>
                                        <div class="small text-muted">National ID</div>
                                        <div class="fw-bold">
                                            @if($user->employee && $user->employee->national_id)
                                            {{ $user->employee->national_id }}
                                            @else
                                            Not provided
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="d-flex align-items-start gap-2">
                                    <span class="material-symbols-outlined" style="font-size:28px;">calendar_today</span>
                                    <div>
                                        <div class="small text-muted">Start Date</div>
                                        <div class="fw-bold">{{ $user->start_date ? $user->start_date->format('M d, Y') : 'Not set' }}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="d-flex align-items-start gap-2">
                                    <span class="material-symbols-outlined" style="font-size:28px;">calendar_today</span>
                                    <div>
                                        <div class="small text-muted">End Date</div>
                                        <div class="fw-bold">{{ $user->end_date ? $user->end_date->format('M d, Y') : 'Not set' }}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="d-flex align-items-start gap-2">
                                    <span class="material-symbols-outlined" style="font-size:28px;">schedule</span>
                                    <div>
                                        <div class="small text-muted">Time In</div>
                                        <div class="fw-bold">{{ $user->time_in ?? 'Not set' }}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="d-flex align-items-start gap-2">
                                    <span class="material-symbols-outlined" style="font-size:28px;">schedule</span>
                                    <div>
                                        <div class="small text-muted">Time Out</div>
                                        <div class="fw-bold">{{ $user->time_out ?? 'Not set' }}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="d-flex align-items-start gap-2">
                                    <span class="material-symbols-outlined" style="font-size:28px;">event</span>
                                    <div>
                                        <div class="small text-muted">Login Days</div>
                                        <div class="fw-bold">
                                            @if($user->login_days && count($user->login_days) > 0)
                                            @foreach($user->login_days as $day)
                                            <span class="badge bg-info text-white">{{ ucfirst($day) }}</span>
                                            @endforeach
                                            @else
                                            Not restricted
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @if($user->last_login_at)
                            <div class="col-lg-4">
                                <div class="d-flex align-items-start gap-2">
                                    <span class="material-symbols-outlined" style="font-size:28px;">login</span>
                                    <div>
                                        <div class="small text-muted">Last Login</div>
                                        <div class="fw-bold">{{ $user->last_login_at->format('M d, Y h:i A') }}</div>
                                    </div>
                                </div>
                            </div>
                            @endif

                            <div class="col-lg-4">
                                <div class="d-flex align-items-start gap-2">
                                    <span class="material-symbols-outlined" style="font-size:28px;">verified_user</span>
                                    <div>
                                        <div class="small text-muted">Account Status</div>
                                        <div class="fw-bold">
                                            @if($user->is_active)
                                            <span class="badge bg-success">Active</span>
                                            @else
                                            <span class="badge bg-danger">Inactive</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="d-flex align-items-start gap-2">
                                    <span class="material-symbols-outlined" style="font-size:28px;">badge</span>
                                    <div>
                                        <div class="small text-muted">Linked Staff Record</div>
                                        <div class="fw-bold">
                                            @if($user->staffLink && $user->staffLink->staff)
                                            @php $linkedStaff = $user->staffLink->staff; @endphp
                                            <a href="{{ route('staff.show', Illuminate\Support\Facades\Crypt::encrypt($linkedStaff->id)) }}"
                                               class="d-flex align-items-center gap-1 text-decoration-none">
                                                <span>{{ $linkedStaff->full_names }}</span>
                                                <span class="text-muted small">({{ $linkedStaff->staff_number }})</span>
                                                <span class="material-symbols-outlined" style="font-size:16px;">open_in_new</span>
                                            </a>
                                            @else
                                            <span class="text-muted">Not linked</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @if($user->role_companies && count($user->role_companies) > 0)
                            <div class="col-12">
                                <div class="mt-2">
                                    <div class="small text-muted mb-1">Assigned Companies</div>
                                    <div class="d-flex gap-1 flex-wrap">
                                        @foreach($user->role_companies as $companyId)
                                        <span class="badge bg-primary text-white">{{ $assignedCompanies[$companyId] ?? 'Company ID: ' . $companyId }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            @endif

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection