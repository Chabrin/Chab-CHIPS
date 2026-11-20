{{-- What happened to the "set your password" email after a staff member's system login was made. Set by Add Staff as session('account_setup'). --}}
@php($setup = session('account_setup'))
@if(is_array($setup))
    @if(!empty($setup['sent']))
    <div class="alert alert-light-success alert-dismissible fade show" role="alert">
        <strong>System login created.</strong> A link to choose a password was emailed to {{ $setup['email'] ?? 'the staff member' }}.
        It works once and expires in {{ \App\Services\PasswordSetupLink::VALID_HOURS }} hours. Nobody else sees the password.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @else
    <div class="alert alert-light-warning alert-dismissible fade show" role="alert">
        <strong>System login created, but the email could not be sent</strong> to {{ $setup['email'] ?? 'the staff member' }}.
        Open System Users and use <strong>Resend link</strong> once the email address is confirmed.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif
@endif
