@extends('layouts.main')

@section('styles')
<style>
/* ── Shell ───────────────────────────────────────── */
.pf-page { padding: 20px 0; }

/* ── Back link ───────────────────────────────────── */
.pf-back {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    font-weight: 600;
    color: #6b7280;
    text-decoration: none;
    margin-bottom: 20px;
    transition: color 0.15s;
}
.pf-back:hover { color: #0a0a0a; }
.pf-back .material-symbols-outlined { font-size: 16px !important; }

/* ── Two-column grid ─────────────────────────────── */
.pf-grid {
    display: grid;
    grid-template-columns: 260px 1fr;
    gap: 20px;
    align-items: start;
}
@media (max-width: 767px) { .pf-grid { grid-template-columns: 1fr; } }

/* ── Identity card (left) ────────────────────────── */
.pf-identity-card {
    background: #fff;
    border: 1px solid #d9ebf7;
    border-radius: 10px;
    padding: 24px 20px;
    text-align: center;
    position: relative;
    overflow: hidden;
}
.pf-identity-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, #5b9bd5, #b7d7f0);
    border-radius: 10px 10px 0 0;
}

/* Avatar circle with initials */
.pf-avatar {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: #b7d7f0;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 8px auto 14px;
    font-size: 26px;
    font-weight: 700;
    color: #0a0a0a;
    letter-spacing: -1px;
    border: 3px solid #d9ebf7;
}

.pf-name {
    font-size: 15px;
    font-weight: 700;
    color: #0a0a0a;
    margin-bottom: 4px;
    line-height: 1.3;
}
.pf-username {
    font-size: 11px;
    color: #9ca3af;
    margin-bottom: 14px;
    font-weight: 500;
}

/* Status pill */
.pf-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 600;
    padding: 3px 12px;
    border-radius: 20px;
    margin-bottom: 16px;
}
.pf-status.active   { background: #dcfce7; color: #16a34a; }
.pf-status.inactive { background: #fee2e2; color: #dc2626; }
.pf-status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

/* Role badges */
.pf-roles {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 5px;
    margin-bottom: 8px;
}
.pf-role-badge {
    font-size: 10px;
    font-weight: 600;
    color: #0a0a0a;
    background: #d9ebf7;
    border: 1px solid #b7d7f0;
    border-radius: 20px;
    padding: 3px 10px;
    text-transform: capitalize;
}

/* ── Info card (right) ───────────────────────────── */
.pf-info-card {
    background: #fff;
    border: 1px solid #d9ebf7;
    border-radius: 10px;
    overflow: hidden;
}

.pf-section {
    padding: 16px 20px;
    border-bottom: 1px solid #f0f7fd;
}
.pf-section:last-child { border-bottom: none; }

.pf-section-title {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #9ca3af;
    margin-bottom: 12px;
}

/* Info rows */
.pf-rows {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px 20px;
}
@media (max-width: 575px) { .pf-rows { grid-template-columns: 1fr; } }

.pf-row {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}
.pf-row-icon {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    background: #f0f7fd;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 1px;
}
.pf-row-icon .material-symbols-outlined {
    font-size: 14px !important;
    color: #5b9bd5 !important;
    font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
}
.pf-row-body { flex: 1; min-width: 0; }
.pf-row-label {
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #9ca3af;
    margin-bottom: 1px;
}
.pf-row-value {
    font-size: 12px;
    font-weight: 600;
    color: #0a0a0a;
    word-break: break-all;
}
.pf-row-value.muted { color: #9ca3af; font-weight: 400; font-style: italic; }

/* Day pill */
.pf-day-pill {
    display: inline-block;
    font-size: 10px;
    font-weight: 600;
    background: #d9ebf7;
    color: #0a0a0a;
    border-radius: 4px;
    padding: 1px 7px;
    margin: 1px 2px 1px 0;
}

/* Company pill */
.pf-company-pill {
    display: inline-block;
    font-size: 10px;
    font-weight: 600;
    background: #b7d7f0;
    color: #0a0a0a;
    border-radius: 4px;
    padding: 2px 8px;
    margin: 1px 2px 1px 0;
}

/* Staff link */
.pf-staff-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: #5b9bd5;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
}
.pf-staff-link:hover { text-decoration: underline; color: #3a7fba; }
.pf-staff-link .material-symbols-outlined { font-size: 13px !important; }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:16px;">
    <div class="container-fluid">
        <div class="pf-page">

            <a href="{{ route('dashboard') }}" class="pf-back">
                <span class="material-symbols-outlined">arrow_back</span>
                Back to Dashboard
            </a>

            <div class="pf-grid">

                {{-- ── Left: Identity ── --}}
                <div class="pf-identity-card">
                    @php
                        $nameParts = explode(' ', trim($user->name));
                        $initials  = strtoupper(
                            substr($nameParts[0] ?? '', 0, 1) .
                            substr(end($nameParts), 0, 1)
                        );
                    @endphp
                    <div class="pf-avatar">{{ $initials }}</div>

                    <div class="pf-name">{{ $user->name }}</div>
                    <div class="pf-username">{{ '@' . $user->username }}</div>

                    <div>
                        @if($user->is_active)
                        <span class="pf-status active">
                            <span class="pf-status-dot"></span> Active
                        </span>
                        @else
                        <span class="pf-status inactive">
                            <span class="pf-status-dot"></span> Inactive
                        </span>
                        @endif
                    </div>

                    @if(isset($user->roles) && $user->roles->isNotEmpty())
                    <div class="pf-roles">
                        @foreach($user->roles as $role)
                        <span class="pf-role-badge">{{ $role->display_name ?? str_replace('_', ' ', $role->name) }}</span>
                        @endforeach
                    </div>
                    @endif
                </div>

                {{-- ── Right: Info ── --}}
                <div class="pf-info-card">

                    {{-- Contact --}}
                    <div class="pf-section">
                        <div class="pf-section-title">Contact &amp; Identity</div>
                        <div class="pf-rows">

                            <div class="pf-row">
                                <div class="pf-row-icon"><span class="material-symbols-outlined">email</span></div>
                                <div class="pf-row-body">
                                    <div class="pf-row-label">Email Address</div>
                                    <div class="pf-row-value">{{ $user->email ?? '—' }}</div>
                                </div>
                            </div>

                            <div class="pf-row">
                                <div class="pf-row-icon"><span class="material-symbols-outlined">perm_identity</span></div>
                                <div class="pf-row-body">
                                    <div class="pf-row-label">National ID</div>
                                    <div class="pf-row-value {{ $user->employee?->national_id ? '' : 'muted' }}">
                                        {{ $user->employee?->national_id ?? 'Not provided' }}
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    {{-- Staff --}}
                    <div class="pf-section">
                        <div class="pf-section-title">Staff Record</div>
                        <div class="pf-rows">

                            <div class="pf-row">
                                <div class="pf-row-icon"><span class="material-symbols-outlined">badge</span></div>
                                <div class="pf-row-body">
                                    <div class="pf-row-label">Linked Staff</div>
                                    <div class="pf-row-value">
                                        @if($user->staffLink && $user->staffLink->staff)
                                            @php $ls = $user->staffLink->staff; @endphp
                                            <a href="{{ route('staff.show', Illuminate\Support\Facades\Crypt::encrypt($ls->id)) }}" class="pf-staff-link">
                                                {{ $ls->full_names }}
                                                <span class="text-muted" style="font-weight:400;">&nbsp;{{ $ls->staff_number }}</span>
                                                <span class="material-symbols-outlined">open_in_new</span>
                                            </a>
                                        @else
                                            <span class="muted">Not linked</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="pf-row">
                                <div class="pf-row-icon"><span class="material-symbols-outlined">calendar_today</span></div>
                                <div class="pf-row-body">
                                    <div class="pf-row-label">Account Start Date</div>
                                    <div class="pf-row-value {{ $user->start_date ? '' : 'muted' }}">
                                        {{ $user->start_date ? $user->start_date->format('d M Y') : 'Not set' }}
                                    </div>
                                </div>
                            </div>

                            <div class="pf-row">
                                <div class="pf-row-icon"><span class="material-symbols-outlined">event_busy</span></div>
                                <div class="pf-row-body">
                                    <div class="pf-row-label">Account End Date</div>
                                    <div class="pf-row-value {{ $user->end_date ? '' : 'muted' }}">
                                        {{ $user->end_date ? $user->end_date->format('d M Y') : 'Not set' }}
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    {{-- Schedule --}}
                    <div class="pf-section">
                        <div class="pf-section-title">Schedule</div>
                        <div class="pf-rows">

                            <div class="pf-row">
                                <div class="pf-row-icon"><span class="material-symbols-outlined">schedule</span></div>
                                <div class="pf-row-body">
                                    <div class="pf-row-label">Working Hours</div>
                                    <div class="pf-row-value">
                                        @if($user->time_in || $user->time_out)
                                            {{ $user->time_in ?? '—' }} &nbsp;→&nbsp; {{ $user->time_out ?? '—' }}
                                        @else
                                            <span class="muted">Not set</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="pf-row">
                                <div class="pf-row-icon"><span class="material-symbols-outlined">date_range</span></div>
                                <div class="pf-row-body">
                                    <div class="pf-row-label">Login Days</div>
                                    <div class="pf-row-value">
                                        @if($user->login_days && count($user->login_days) > 0)
                                            @foreach($user->login_days as $day)
                                            <span class="pf-day-pill">{{ ucfirst($day) }}</span>
                                            @endforeach
                                        @else
                                            <span class="muted" style="font-size:11px;font-style:italic;font-weight:400;color:#9ca3af;">Not restricted</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    {{-- Security --}}
                    <div class="pf-section">
                        <div class="pf-section-title">Security</div>
                        <div class="pf-rows">

                            <div class="pf-row">
                                <div class="pf-row-icon"><span class="material-symbols-outlined">login</span></div>
                                <div class="pf-row-body">
                                    <div class="pf-row-label">Last Login</div>
                                    <div class="pf-row-value {{ $user->last_login_at ? '' : 'muted' }}">
                                        {{ $user->last_login_at ? $user->last_login_at->format('d M Y, h:i A') : 'Never' }}
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    {{-- Change Password --}}
                    <div class="pf-section">
                        <div class="pf-section-title">Change Password</div>
                        @include('partials.password-change-form', ['context' => 'profile'])
                    </div>

                    {{-- Companies --}}
                    @if($assignedCompanies && count($assignedCompanies) > 0)
                    <div class="pf-section">
                        <div class="pf-section-title">Assigned Companies</div>
                        <div class="pf-row">
                            <div class="pf-row-icon"><span class="material-symbols-outlined">business</span></div>
                            <div class="pf-row-body" style="padding-top:4px;">
                                @foreach($assignedCompanies as $companyName)
                                <span class="pf-company-pill">{{ $companyName }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif

                </div>
                {{-- end right --}}

            </div>
        </div>
    </div>
</div>
@endsection
