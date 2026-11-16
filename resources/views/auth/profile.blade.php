@extends('layouts.main')

@section('styles')
<style>
    /* ── Shell & Topbar ──────────────────────────────── */
    .pf-page { padding: 18px 0 40px; }
    
    .pf-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 24px;
    }
    .pf-back {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        font-weight: 600;
        color: #4b5563;
        text-decoration: none;
        transition: color 0.15s ease;
    }
    .pf-back:hover { color: #1e3a8a; }
    .pf-back .material-symbols-outlined { font-size: 18px !important; }

    /* ── Two-Column Layout ───────────────────────────── */
    .pf-layout {
        display: grid;
        grid-template-columns: 290px 1fr;
        gap: 24px;
        align-items: start;
    }
    @media (max-width: 991px) {
        .pf-layout { grid-template-columns: 1fr; }
    }

    /* ── Identity Card (Left) ────────────────────────── */
    .pf-sidebar-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        overflow: hidden;
    }
    .pf-card-cover {
        height: 64px;
        background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
        position: relative;
    }
    .pf-card-body {
        padding: 0 20px 20px;
        text-align: center;
        position: relative;
    }
    .pf-avatar-wrap {
        width: 76px;
        height: 76px;
        border-radius: 50%;
        background: #eff6ff;
        border: 4px solid #ffffff;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.08);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: -38px auto 12px;
        color: #1e40af;
        font-size: 24px;
        font-weight: 700;
        letter-spacing: -0.5px;
    }
    .pf-user-name {
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 2px;
    }
    .pf-user-tag {
        font-size: 12px;
        font-weight: 500;
        color: #64748b;
        margin-bottom: 14px;
    }

    /* Status Pills */
    .pf-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 600;
        padding: 3px 10px;
        border-radius: 20px;
    }
    .pf-status-badge.active { background: #dcfce7; color: #15803d; }
    .pf-status-badge.inactive { background: #fee2e2; color: #b91c1c; }
    .pf-status-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
    }

    .pf-role-tags {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 6px;
        margin-top: 14px;
    }
    .pf-badge-role {
        font-size: 11px;
        font-weight: 600;
        color: #1e40af;
        background: #eff6ff;
        border: 1px solid #dbeafe;
        border-radius: 6px;
        padding: 2px 8px;
    }

    .pf-sidebar-meta {
        border-top: 1px solid #f1f5f9;
        margin-top: 18px;
        padding-top: 14px;
        display: flex;
        justify-content: space-around;
        text-align: center;
    }
    .pf-meta-item small {
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        color: #94a3b8;
        display: block;
    }
    .pf-meta-item span {
        font-size: 12px;
        font-weight: 600;
        color: #334155;
    }

    /* ── Right Content Panels & Tabs ─────────────────── */
    .pf-nav-tabs {
        display: flex;
        gap: 8px;
        border-bottom: 1px solid #e2e8f0;
        margin-bottom: 18px;
    }
    .pf-tab-btn {
        background: none;
        border: none;
        outline: none;
        padding: 8px 14px;
        font-size: 13px;
        font-weight: 600;
        color: #64748b;
        cursor: pointer;
        border-bottom: 2px solid transparent;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.15s;
    }
    .pf-tab-btn .material-symbols-outlined { font-size: 18px !important; }
    .pf-tab-btn.active {
        color: #2563eb;
        border-bottom-color: #2563eb;
    }

    .pf-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        margin-bottom: 18px;
        overflow: hidden;
    }
    .pf-card-header {
        padding: 14px 20px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .pf-card-title {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #475569;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin: 0;
    }
    .pf-card-title .material-symbols-outlined { font-size: 16px !important; color: #2563eb; }
    .pf-card-content { padding: 18px 20px; }

    /* Field Grid */
    .pf-grid-2 {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px 24px;
    }
    @media (max-width: 640px) {
        .pf-grid-2 { grid-template-columns: 1fr; }
    }

    .pf-field {
        display: flex;
        flex-direction: column;
        gap: 3px;
    }
    .pf-label {
        font-size: 11px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }
    .pf-value {
        font-size: 13px;
        font-weight: 600;
        color: #0f172a;
    }

    /* Badges & Tags */
    .pf-empty-badge {
        font-size: 11px;
        font-weight: 500;
        color: #94a3b8;
        background: #f1f5f9;
        padding: 1px 7px;
        border-radius: 4px;
        display: inline-block;
        width: fit-content;
    }
    .pf-pill {
        display: inline-block;
        font-size: 11px;
        font-weight: 600;
        background: #eff6ff;
        color: #1e40af;
        border: 1px solid #dbeafe;
        border-radius: 4px;
        padding: 2px 7px;
        margin: 2px 4px 2px 0;
    }

    /* Link Style */
    .pf-link {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: #2563eb;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
    }
    .pf-link:hover { text-decoration: underline; color: #1d4ed8; }
    .pf-link .material-symbols-outlined { font-size: 15px !important; }
</style>
@endsection

@section('content')
<div class="page-body" style="padding-top:16px;">
    <div class="container-fluid">
        <div class="pf-page">

            @if ($passwordChangeRequired)
                <div class="alert alert-warning" role="alert">
                    Your password has expired or a password change is required. Please change it below before continuing.
                </div>
            @endif

            {{-- Top Action Bar --}}
            <div class="pf-topbar">
                <a href="{{ route('dashboard') }}" class="pf-back">
                    <span class="material-symbols-outlined">arrow_back</span>
                    Back to Dashboard
                </a>
            </div>

            <div class="pf-layout">

                {{-- Left: User Profile Snapshot --}}
                <aside class="pf-sidebar-card">
                    <div class="pf-card-cover"></div>
                    <div class="pf-card-body">
                      @php
                        $nameParts = explode(' ', trim($user->name));
                        $initials = strtoupper(
                            substr($nameParts[0] ?? '', 0, 1) .
                            substr(end($nameParts), 0, 1)
                        );

                        $staff = method_exists($user, 'linkedStaff') ? $user->linkedStaff() : ($user->staff ?? null);
                        $avatarPath = null;
                        if ($staff && !empty($staff->photo)) {
                            $avatarPath = asset('storage/' . $staff->photo);
                        } elseif (!empty($user->photo)) {
                            $avatarPath = asset('storage/' . $user->photo);
                        }
                    @endphp

                    <div class="pf-avatar-wrap" @if($avatarPath) style="overflow: hidden; padding: 0;" @endif>
                        @if($avatarPath)
                            <img src="{{ $avatarPath }}" alt="{{ $user->name }}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        @else
                            {{ $initials }}
                        @endif
                    </div>

                        <div class="pf-user-name">{{ $user->name }}</div>
                        <div class="pf-user-tag">{{ '@' . $user->username }}</div>

                        <div>
                            @if($user->is_active)
                                <span class="pf-status-badge active">
                                    <span class="pf-status-dot"></span> Active
                                </span>
                            @else
                                <span class="pf-status-badge inactive">
                                    <span class="pf-status-dot"></span> Inactive
                                </span>
                            @endif
                        </div>

                        @if(isset($user->roles) && $user->roles->isNotEmpty())
                            <div class="pf-role-tags">
                                @foreach($user->roles as $role)
                                    <span class="pf-badge-role">{{ $role->display_name ?? str_replace('_', ' ', $role->name) }}</span>
                                @endforeach
                            </div>
                        @endif

                        <div class="pf-sidebar-meta">
                            <div class="pf-meta-item">
                                <small>User ID</small>
                                <span>#{{ $user->id }}</span>
                            </div>
                            <div class="pf-meta-item">
                                <small>Linked Staff</small>
                                <span>{{ $user->staffLink?->staff ? 'Yes' : 'None' }}</span>
                            </div>
                        </div>
                    </div>
                </aside>

                {{-- Right: Tabbed Details --}}
                <main>
                    <nav class="pf-nav-tabs">
                        <button type="button" class="pf-tab-btn {{ $showSecurity ? '' : 'active' }}" onclick="switchPfTab(event, 'tab-overview')">
                            <span class="material-symbols-outlined">badge</span>
                            Overview
                        </button>
                        <button type="button" class="pf-tab-btn {{ $showSecurity ? 'active' : '' }}" onclick="switchPfTab(event, 'tab-security')">
                            <span class="material-symbols-outlined">lock_reset</span>
                            Security &amp; Password
                        </button>
                    </nav>

                    {{-- Tab 1: Overview --}}
                    <div id="tab-overview" class="pf-tab-content" @if($showSecurity) style="display: none;" @endif>
                        
                        {{-- Contact & Identity --}}
                        <section class="pf-card">
                            <div class="pf-card-header">
                                <h3 class="pf-card-title">
                                    <span class="material-symbols-outlined">contact_mail</span>
                                    Contact &amp; Identification
                                </h3>
                            </div>
                            <div class="pf-card-content">
                                <div class="pf-grid-2">
                                    <div class="pf-field">
                                        <div class="pf-label">Email Address</div>
                                        <div class="pf-value">{{ $user->email ?? '—' }}</div>
                                    </div>
                                    <div class="pf-field">
                                        <div class="pf-label">National ID / Passport</div>
                                        <div class="pf-value">
                                            @if($user->employee?->national_id)
                                                {{ $user->employee->national_id }}
                                            @else
                                                <span class="pf-empty-badge">Not provided</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        {{-- Employment & Staff Record --}}
                        <section class="pf-card">
                            <div class="pf-card-header">
                                <h3 class="pf-card-title">
                                    <span class="material-symbols-outlined">work</span>
                                    Staff Record &amp; Terms
                                </h3>
                            </div>
                            <div class="pf-card-content">
                                <div class="pf-grid-2">
                                    <div class="pf-field">
                                        <div class="pf-label">Linked Staff Member</div>
                                        <div class="pf-value">
                                            @if($user->staffLink && $user->staffLink->staff)
                                                @php $ls = $user->staffLink->staff; @endphp
                                                <a href="{{ route('staff.show', Illuminate\Support\Facades\Crypt::encrypt($ls->id)) }}" class="pf-link">
                                                    {{ $ls->full_names }}
                                                    <span style="font-weight:400;color:#64748b;">({{ $ls->staff_number }})</span>
                                                    <span class="material-symbols-outlined">open_in_new</span>
                                                </a>
                                            @else
                                                <span class="pf-empty-badge">Unlinked</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="pf-field">
                                        <div class="pf-label">Account Start Date</div>
                                        <div class="pf-value">
                                            @if($user->start_date)
                                                {{ $user->start_date->format('d M Y') }}
                                            @else
                                                <span class="pf-empty-badge">Not set</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="pf-field">
                                        <div class="pf-label">Account Expiry / End Date</div>
                                        <div class="pf-value">
                                            @if($user->end_date)
                                                {{ $user->end_date->format('d M Y') }}
                                            @else
                                                <span class="pf-empty-badge">Indefinite</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        {{-- Access & Working Schedule --}}
                        <section class="pf-card">
                            <div class="pf-card-header">
                                <h3 class="pf-card-title">
                                    <span class="material-symbols-outlined">schedule</span>
                                    Access &amp; Schedule
                                </h3>
                            </div>
                            <div class="pf-card-content">
                                <div class="pf-grid-2">
                                    <div class="pf-field">
                                        <div class="pf-label">Working Hours</div>
                                        <div class="pf-value">
                                            @if($user->time_in || $user->time_out)
                                                {{ $user->time_in ?? '—' }} &nbsp;→&nbsp; {{ $user->time_out ?? '—' }}
                                            @else
                                                <span class="pf-empty-badge">Flexible / Not set</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="pf-field">
                                        <div class="pf-label">Allowed Login Days</div>
                                        <div class="pf-value">
                                            @if($user->login_days && count($user->login_days) > 0)
                                                @foreach($user->login_days as $day)
                                                    <span class="pf-pill">{{ ucfirst($day) }}</span>
                                                @endforeach
                                            @else
                                                <span class="pf-empty-badge">All days allowed</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="pf-field">
                                        <div class="pf-label">Last Active Session</div>
                                        <div class="pf-value">
                                            @if($user->last_login_at)
                                                {{ $user->last_login_at->format('d M Y, h:i A') }}
                                            @else
                                                <span class="pf-empty-badge">Never logged in</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        {{-- Assigned Companies --}}
                        @if($assignedCompanies && count($assignedCompanies) > 0)
                            <section class="pf-card">
                                <div class="pf-card-header">
                                    <h3 class="pf-card-title">
                                        <span class="material-symbols-outlined">corporate_fare</span>
                                        Assigned Companies
                                    </h3>
                                </div>
                                <div class="pf-card-content">
                                    @foreach($assignedCompanies as $companyName)
                                        <span class="pf-pill" style="background:#e0f2fe;color:#0369a1;border-color:#bae6fd;">
                                            {{ $companyName }}
                                        </span>
                                    @endforeach
                                </div>
                            </section>
                        @endif

                    </div>

                    {{-- Tab 2: Security --}}
                    <div id="tab-security" class="pf-tab-content" @if(!$showSecurity) style="display: none;" @endif>
                        <section class="pf-card">
                            <div class="pf-card-header">
                                <h3 class="pf-card-title">
                                    <span class="material-symbols-outlined">password</span>
                                    Change Password
                                </h3>
                            </div>
                            <div class="pf-card-content">
                                @if ($staffPasswordPolicyApplies)
                                    <p class="text-muted">Staff passwords expire every {{ $passwordExpiryDays }} {{ $passwordExpiryDays === 1 ? 'day' : 'days' }}. Choose a password you have never used before.</p>
                                @endif
                                @include('partials.password-change-form', ['context' => 'profile', 'reloadAfterPasswordChange' => $passwordChangeRequired])
                            </div>
                        </section>
                    </div>

                </main>
            </div>
        </div>
    </div>
</div>

<script>
    function switchPfTab(event, tabId) {
        event.preventDefault();
        
        // Hide all tabs
        document.querySelectorAll('.pf-tab-content').forEach(tab => {
            tab.style.display = 'none';
        });
        
        // Reset button states
        document.querySelectorAll('.pf-tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Activate current tab
        document.getElementById(tabId).style.display = 'block';
        event.currentTarget.classList.add('active');
    }
</script>
@endsection
