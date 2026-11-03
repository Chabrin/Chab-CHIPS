{{--
    Staff signature: upload an image or draw it on screen.
    Posts signature_file (upload) or signature_data (drawn PNG data URL); an upload wins if both are given.
    Optional: $currentSignatureUrl (shows the saved signature), $currentSignatureNote, $allowSignatureRemoval.
--}}
@php
    $currentSignatureUrl = $currentSignatureUrl ?? null;
    $allowSignatureRemoval = $allowSignatureRemoval ?? false;
@endphp
<div class="staff-signature" style="border:1px solid #d5dbe1;border-radius:8px;padding:14px;background:#fafbfc">
    @if($currentSignatureUrl)
        <div style="margin-bottom:10px">
            <small style="display:block;color:#5c6b7a;margin-bottom:4px">{{ $currentSignatureNote ?? 'Current signature' }}</small>
            <img src="{{ $currentSignatureUrl }}" alt="Current signature" style="max-height:80px;max-width:100%;background:#fff;border:1px solid #e3e7eb;border-radius:4px;padding:4px">
            @if($allowSignatureRemoval)
                <label style="display:block;margin-top:6px;font-weight:normal"><input type="checkbox" name="remove_signature" value="1"> Remove this signature</label>
            @endif
        </div>
    @endif
    <div style="display:flex;gap:16px;margin-bottom:10px;flex-wrap:wrap">
        <label style="font-weight:normal;margin:0"><input type="radio" name="signature_mode" value="draw" checked data-signature-mode> Draw signature</label>
        <label style="font-weight:normal;margin:0"><input type="radio" name="signature_mode" value="upload" data-signature-mode> Upload image</label>
    </div>
    <div data-signature-draw>
        <canvas data-signature-pad style="width:100%;max-width:460px;height:160px;background:#fff;border:1px dashed #9aa7b4;border-radius:6px;touch-action:none;display:block"></canvas>
        <div style="margin-top:6px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <button type="button" data-signature-clear style="padding:4px 12px;background:#fff;color:#243447;border:1px solid #b8c2cc;border-radius:4px">Clear</button>
            <small style="color:#5c6b7a">Sign inside the box with your mouse or finger.</small>
        </div>
        <input type="hidden" name="signature_data" value="" data-signature-data>
    </div>
    <div data-signature-upload hidden>
        <input type="file" name="signature_file" accept=".png,.jpg,.jpeg,image/png,image/jpeg" class="form-control" data-no-duplicate-check>
        <small style="color:#5c6b7a">PNG or JPG, max 1 MB. A dark signature on a white or transparent background works best.</small>
    </div>
    @error('signature_file')<small class="error text-danger" style="display:block">{{ $message }}</small>@enderror
    @error('signature_data')<small class="error text-danger" style="display:block">{{ $message }}</small>@enderror
</div>
<script>
(function () {
    var root = document.currentScript.previousElementSibling;
    var canvas = root.querySelector('[data-signature-pad]');
    var data = root.querySelector('[data-signature-data]');
    var fileInput = root.querySelector('input[name="signature_file"]');
    var ctx = canvas.getContext('2d');
    var drawing = false, drawn = false;

    // Match the bitmap to the displayed size so strokes land under the pointer
    function resize() {
        var ratio = window.devicePixelRatio || 1;
        var rect = canvas.getBoundingClientRect();
        if (!rect.width) return;
        canvas.width = rect.width * ratio;
        canvas.height = rect.height * ratio;
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        ctx.lineWidth = 2.2; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#111';
        drawn = false; data.value = '';
    }
    function point(e) {
        var rect = canvas.getBoundingClientRect();
        return { x: e.clientX - rect.left, y: e.clientY - rect.top };
    }
    canvas.addEventListener('pointerdown', function (e) {
        if (!canvas.width) resize();
        drawing = true; canvas.setPointerCapture(e.pointerId);
        var p = point(e); ctx.beginPath(); ctx.moveTo(p.x, p.y);
    });
    canvas.addEventListener('pointermove', function (e) {
        if (!drawing) return;
        var p = point(e); ctx.lineTo(p.x, p.y); ctx.stroke(); drawn = true;
    });
    function finish() {
        if (!drawing) return;
        drawing = false;
        if (drawn) data.value = canvas.toDataURL('image/png');
    }
    canvas.addEventListener('pointerup', finish);
    canvas.addEventListener('pointercancel', finish);
    root.querySelector('[data-signature-clear]').addEventListener('click', function () {
        ctx.clearRect(0, 0, canvas.width, canvas.height); drawn = false; data.value = '';
    });
    root.querySelectorAll('[data-signature-mode]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            var upload = radio.value === 'upload' && radio.checked;
            root.querySelector('[data-signature-upload]').hidden = !upload;
            root.querySelector('[data-signature-draw]').hidden = upload;
            // Only the visible method is submitted
            if (upload) { data.value = ''; data.disabled = true; fileInput.disabled = false; }
            else { fileInput.value = ''; fileInput.disabled = true; data.disabled = false; if (drawn) data.value = canvas.toDataURL('image/png'); }
        });
    });
    fileInput.disabled = true;
    // The pad may start inside a hidden tab with no size; size it when it first becomes visible
    if (window.ResizeObserver) new ResizeObserver(function () { if (canvas.getBoundingClientRect().width && !drawn) resize(); }).observe(canvas);
    else window.addEventListener('load', resize);
})();
</script>
