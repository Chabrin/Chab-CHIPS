{{-- Hero banner + tab bar shared by every Communications page. Order, names and icons match the sidebar sub-menus (CommunicationsMenuSeeder). Needs $active (compose|batches|logs|metrics|settings), $title, $subtitle. --}}
@php
    $tabs = [
        'compose' => ['Compose', 'edit_square', route('communications.compose')],
        'batches' => ['Sent', 'outbox', route('communications.index')],
        'logs' => ['Logs', 'receipt_long', route('communications.logs')],
        'metrics' => ['Metrics', 'monitoring', route('communications.metrics')],
    ];
    // The credentials page is only offered to people who hold it
    if (app(\App\Services\Comms\CommSettings::class)->canManage()) {
        $tabs['settings'] = ['Settings', 'settings', route('communications.settings')];
    }
@endphp
<div class="cm-hero">
    <div class="cm-hero-top">
        <div>
            <h1>{{ $title }}</h1>
            <p>{{ $subtitle }}</p>
        </div>
        @if(($active ?? '') !== 'compose')
        <div class="cm-hero-actions">
            <a href="{{ route('communications.compose') }}" class="cm-btn cm-btn-ghost"><span class="material-symbols-outlined">add</span>New message</a>
        </div>
        @endif
    </div>
    <div class="cm-tabs">
        @foreach($tabs as $key => [$label, $icon, $url])
        <a href="{{ $url }}" class="cm-tab {{ ($active ?? '') === $key ? 'active' : '' }}"><span class="material-symbols-outlined">{{ $icon }}</span>{{ $label }}</a>
        @endforeach
    </div>
</div>
