{{-- After a HELB loan beneficiary is added: set up the deduction and declare them to HELB. Set by Add Staff as session('helb_prompt'). --}}
@if(is_array(session('helb_prompt')))
<div class="alert alert-light-warning alert-dismissible fade show" role="alert">
    <strong>HELB loan beneficiary.</strong>
    Set up the HELB deduction for {{ session('helb_prompt')['name'] ?? 'this staff member' }} under
    @if(auth()->user()?->hasPermission('create_helb_loans'))<a href="{{ route('deductions.helb') }}">HELB Loans</a>@else HELB Loans (ask someone who manages deductions)@endif,
    and declare them to HELB on the HELB Employer Portal.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif
