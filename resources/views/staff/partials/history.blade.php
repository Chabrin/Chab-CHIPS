{{-- The rows of a History tab, newest first. $history = ['rows' => [...], 'total' => n, 'shown' => n], $subject = 'staff member' or 'contract'. --}}
@if(empty($history['rows']))
    <p class="text-muted mb-0">Nothing has been changed on this {{ $subject }} since changes began to be recorded.</p>
@else
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-2" data-history-table>
            <thead class="table-light">
                <tr>
                    <th style="white-space:nowrap;">When</th>
                    <th>Who</th>
                    <th>What</th>
                    <th>Was</th>
                    <th>Now</th>
                </tr>
            </thead>
            <tbody>
                @foreach($history['rows'] as $row)
                <tr>
                    <td style="white-space:nowrap;">{{ $row['at']?->format('d M Y, H:i') ?? '' }}</td>
                    <td>{{ $row['by'] }}</td>
                    <td>
                        <span class="badge bg-light text-dark border me-1">{{ $row['area'] }}</span>{{ $row['what'] }}
                    </td>
                    <td class="text-muted">{{ $row['old'] ?? '' }}</td>
                    <td>{{ $row['new'] ?? '' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="small text-muted mb-0">
        Newest first. This list is kept by the system and cannot be edited or deleted.
        @if($history['total'] > $history['shown'])
            Showing the latest {{ $history['shown'] }} of {{ $history['total'] }} changes.
        @endif
    </p>
@endif
