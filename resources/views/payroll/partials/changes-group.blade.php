{{-- One group of the "what changed" list: a title with counts that folds open to the rows. Expects $group and $open. --}}
<details class="pc-group" @if ($open) open @endif>
    <summary>
        <span class="pc-group-title">{{ $group['title'] }}</span>
        <span class="pc-chip">{{ $group['count'] }}</span>
        @if (($group['high'] ?? 0) > 0)
            <span class="pc-chip pc-chip--high">{{ $group['high'] }} high impact</span>
        @endif
    </summary>
    <div class="pc-hint">{{ $group['hint'] }}</div>
    <ul class="pc-rows">
        @foreach ($group['rows'] as $row)
            <li class="pc-row {{ $row['high'] ? 'is-high' : '' }}">
                <div class="pc-line"><strong>{{ $row['who'] }}</strong> <span class="pc-what">{{ $row['what'] }}</span></div>
                <div class="pc-detail">{{ $row['detail'] }}</div>
                @if ($row['by'] || $row['at'])
                    <div class="pc-meta">
                        @if ($row['by']) by {{ $row['by'] }} @endif
                        @if ($row['by'] && $row['at']) &middot; @endif
                        @if ($row['at']) {{ $row['at'] }} @endif
                    </div>
                @endif
            </li>
        @endforeach
    </ul>
    @if (($group['more'] ?? 0) > 0)
        <div class="pc-more">and {{ $group['more'] }} more.</div>
    @endif
</details>
