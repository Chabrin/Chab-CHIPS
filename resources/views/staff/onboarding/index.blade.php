@extends('layouts.main')
@section('content')
@php
    $tabs = ['in_progress' => 'In progress', 'finished' => 'Finished', 'closed' => 'Cancelled or failed'];
    $marks = ['complete' => ['check_circle', 'text-success', 'complete'], 'problem' => ['error', 'text-danger', 'has a problem'], 'pending' => ['radio_button_unchecked', 'text-muted', 'not done yet']];
@endphp
<style>
    .onboarding-stages { display: inline-flex; flex-wrap: nowrap; gap: 2px; align-items: center; }
    .onboarding-stages .material-symbols-outlined { font-size: 18px; }
    .onboarding-stages .stage-gap { width: 6px; }
</style>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h3 class="mb-0">Staff onboarding</h3>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-primary" href="{{ route('staff.create') }}">Add staff directly</a>
            <a class="btn btn-primary" href="{{ route('staff.onboarding.create') }}">Invite staff</a>
        </div>
    </div>
    <p class="text-muted">Everyone being onboarded is here, whether HR invited them to fill in their own form or added them directly on Add Staff.
        The stages are the tabs of Add Staff, then the contract, which is added once the staff member is saved.</p>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <ul class="nav nav-pills mb-3">
        @foreach($tabs as $key => $label)
        <li class="nav-item">
            <a class="nav-link {{ $show === $key ? 'active' : '' }}" href="{{ route('staff.onboarding.index', ['show' => $key]) }}">
                {{ $label }} <span class="badge {{ $show === $key ? 'bg-light text-dark' : 'bg-secondary' }}">{{ $counts[$key] }}</span>
            </a>
        </li>
        @endforeach
    </ul>

    <div class="table-responsive">
        <table class="table table-striped align-middle" id="onboardingTable">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Started as</th>
                    <th>Stages</th>
                    <th>Now at</th>
                    <th>{{ $show === 'in_progress' ? 'Days open' : ($show === 'finished' ? 'Finished' : 'Started') }}</th>
                    <th>Staff No.</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($onboardings as $row)
                <tr data-onboarding="{{ $row['key'] }}">
                    <td>
                        <div class="fw-semibold">{{ $row['name'] }}</div>
                        <div class="small text-muted">
                            {{ $row['status_label'] }}@if($row['email'] && $row['email'] !== $row['name']) · {{ $row['email'] }}@endif
                            @if($row['started_by']) · by {{ $row['started_by'] }}@endif
                        </div>
                    </td>
                    <td>{{ $row['source'] }}</td>
                    <td>
                        @if($row['group'] !== 'closed')
                        <span class="onboarding-stages">
                            @foreach($row['stages'] as $stage)
                                @if($stage['key'] === 'contract')<span class="stage-gap"></span>@endif
                                @php([$icon, $colour, $word] = $marks[$stage['status']] ?? $marks['pending'])
                                <span class="material-symbols-outlined {{ $colour }}" title="{{ $stage['label'] }}: {{ $word }}" aria-label="{{ $stage['label'] }}: {{ $word }}">{{ $icon }}</span>
                            @endforeach
                        </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>{{ $row['now_at'] }}</td>
                    <td>
                        @if($show === 'in_progress')
                            {{ $row['days_open'] }} {{ $row['days_open'] === 1 ? 'day' : 'days' }}
                            @if($row['expires_at'])<div class="small text-muted">link expires {{ $row['expires_at']->format('d M Y') }}</div>@endif
                        @elseif($show === 'finished')
                            {{ $row['finished_at'] ? $row['finished_at']->format('d M Y') : '—' }}
                        @else
                            {{ $row['started_at']->format('d M Y') }}
                        @endif
                    </td>
                    <td>{{ $row['staff_number'] ?: '—' }}</td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                        @foreach($row['actions'] as $action)
                            @if($action['method'] === 'delete')
                            <form method="POST" action="{{ $action['url'] }}" onsubmit="return confirm(@js($action['confirm'] ?? 'Are you sure?'))">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-{{ $action['style'] }}">{{ $action['label'] }}</button>
                            </form>
                            @else
                            <a class="btn btn-sm btn-{{ $action['style'] }}" href="{{ $action['url'] }}">{{ $action['label'] }}</a>
                            @endif
                        @endforeach
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">
                    @if($show === 'in_progress') Nobody is being onboarded right now.
                    @elseif($show === 'finished') Nothing has been finished yet.
                    @else Nothing has been cancelled or failed.
                    @endif
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $onboardings->links() }}
</div>
@endsection
