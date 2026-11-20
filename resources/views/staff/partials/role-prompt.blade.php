{{-- After a staff member's position changed: offer the role of the new position for their login. Set by Edit Staff as session('role_prompt'). --}}
@if(is_array(session('role_prompt')) && auth()->user()?->hasPermission('update_users'))
<div class="alert alert-light-warning alert-dismissible fade show" role="alert">
    <form method="POST" action="{{ session('role_prompt')['url'] }}" class="d-flex flex-wrap align-items-center gap-2">
        @csrf
        <span><strong>Position changed.</strong> Should {{ session('role_prompt')['name'] }}'s system access change to <strong>{{ session('role_prompt')['role_name'] }}</strong> too?</span>
        <button type="submit" class="btn btn-sm btn-primary">Yes, change access</button>
        <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="alert">No, leave it</button>
    </form>
</div>
@endif
