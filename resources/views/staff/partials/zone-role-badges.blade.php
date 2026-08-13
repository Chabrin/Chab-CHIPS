@if($member->zone)
    <span class="badge badge-light-info">Field Officer &middot; {{ $member->zone->zone_name }}</span>
@endif
@foreach($member->zonesManaged as $zone)
    <span class="badge badge-light-success">Manager &middot; {{ $zone->zone_name }}</span>
@endforeach
@foreach($member->zonesSupervised as $zone)
    <span class="badge badge-light-warning">Supervisor &middot; {{ $zone->zone_name }}</span>
@endforeach
@if(!$member->zone && $member->zonesManaged->isEmpty() && $member->zonesSupervised->isEmpty())
    <span class="text-muted">-</span>
@endif
