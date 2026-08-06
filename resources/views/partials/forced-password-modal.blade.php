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
