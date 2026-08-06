<div class="modal fade" id="forcePasswordChangeModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="forcePasswordChangeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="forcePasswordChangeModalLabel">Set a New Password</h5>
            </div>
            <div class="modal-body">
                <p class="text-muted small">An administrator has reset your password. You must set a new password before continuing.</p>
                @include('partials.password-change-form', ['context' => 'forced'])
            </div>
            <div class="modal-footer justify-content-start">
                <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                    @csrf
                    <a href="#" onclick="event.preventDefault(); this.closest('form').submit();" class="d-flex align-items-center text-decoration-none text-muted small">
                        <span class="material-symbols-outlined" style="font-size:16px;">logout</span>
                        <span class="ms-1">Log out instead</span>
                    </a>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var modalEl = document.getElementById('forcePasswordChangeModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            var modal = new bootstrap.Modal(modalEl, {
                backdrop: 'static',
                keyboard: false
            });
            modal.show();
        }
    })();
</script>
