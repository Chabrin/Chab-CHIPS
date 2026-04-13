@extends('layouts.main')

@section('styles')
<link href="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-bs5.min.css" rel="stylesheet">
@endsection

@section('content')
<style>
    .lct-wrapper { display:flex; flex-direction:column; height:calc(100vh - 70px); background:#e8eaed; overflow:hidden; }
    .lct-toolbar { background:#fff; border-bottom:1px solid #dee2e6; padding:8px 16px; display:flex; align-items:center; gap:10px; flex-wrap:wrap; flex-shrink:0; box-shadow:0 1px 4px rgba(0,0,0,.08); z-index:50; }
    .lct-toolbar .doc-title { font-size:15px; font-weight:600; border:none; outline:none; border-bottom:2px solid transparent; background:transparent; padding:2px 6px; min-width:220px; flex:1; max-width:340px; transition:border-color .2s; }
    .lct-toolbar .doc-title:focus { border-bottom-color:#0d6efd; }
    .lct-toolbar .doc-desc { font-size:13px; border:none; outline:none; border-bottom:2px solid transparent; background:transparent; padding:2px 6px; flex:2; min-width:180px; color:#555; transition:border-color .2s; }
    .lct-toolbar .doc-desc:focus { border-bottom-color:#6c757d; }
    .lct-chips { background:#fffde7; border-bottom:1px solid #ffe082; padding:6px 16px; display:flex; align-items:center; gap:5px; flex-wrap:wrap; flex-shrink:0; }
    .ph-chip { background:#fff8e1; border:1px solid #ffc107; border-radius:20px; padding:2px 11px; font-size:11.5px; font-family:monospace; cursor:pointer; color:#795548; transition:background .15s,color .15s; user-select:none; white-space:nowrap; }
    .ph-chip:hover { background:#ffc107; color:#212529; }
    .lct-split { flex:1; display:grid; grid-template-columns:1fr; overflow:hidden; transition:grid-template-columns .3s ease; }
    .lct-split.preview-open { grid-template-columns:1fr 1fr; }
    .lct-editor-panel { overflow-y:auto; background:#e8eaed; display:flex; flex-direction:column; }
    .lct-editor-panel .note-editor.note-frame { border:none !important; box-shadow:none !important; background:#e8eaed; }
    .lct-editor-panel .note-toolbar { background:#f8f9fa !important; border-bottom:1px solid #dee2e6 !important; border-top:none !important; padding:4px 10px !important; position:sticky; top:0; z-index:10; }
    .lct-editor-panel .note-editing-area { background:#e8eaed; padding:24px 60px; position:relative; }
    .lct-editor-panel .note-editable { box-shadow:0 2px 16px rgba(0,0,0,.18); max-width:210mm; min-height:297mm; margin:0 auto; padding:25mm 20mm !important; font-family:'Times New Roman',Times,serif !important; font-size:12pt !important; line-height:1.7 !important; color:#212121; background:#fff !important; }
    .pg-break { position:absolute; left:0; right:0; height:24px; background:#e8eaed; pointer-events:none; z-index:5; border-top:2px dashed #bdbdbd; border-bottom:2px dashed #bdbdbd; display:flex; align-items:center; justify-content:center; }
    .pg-break::after { content:attr(data-page); font-size:10px; color:#9e9e9e; font-family:sans-serif; letter-spacing:1px; text-transform:uppercase; }
    .lct-preview-panel { display:none; overflow-y:auto; background:#f5f5f5; border-left:2px solid #dee2e6; flex-direction:column; }
    .lct-preview-panel.open { display:flex; }
    .lct-preview-header { background:#e3f2fd; border-bottom:1px solid #90caf9; padding:6px 16px; font-size:12px; color:#1565c0; flex-shrink:0; }
    .lct-preview-body { padding:24px 60px; flex:1; overflow-y:auto; }
    .a4-preview { background:#fff; box-shadow:0 2px 16px rgba(0,0,0,.18); max-width:210mm; min-height:297mm; margin:0 auto; padding:25mm 20mm; font-family:'Times New Roman',Times,serif; font-size:12pt; line-height:1.7; color:#212121; }
    .a4-preview .sample-val { background:#e3f2fd; color:#1565c0; border-radius:3px; padding:0 3px; font-weight:600; }
</style>

<form method="POST" action="{{ route('property.lease-settings.contract-templates.update', $template->id) }}" id="lctForm">
    @csrf
    @method('PUT')
    <input type="hidden" name="html_body" id="html_body_hidden">

    <div class="lct-wrapper">

        <div class="lct-toolbar">
            <a href="{{ route('property.lease-settings.contract-templates.index') }}" class="btn btn-sm btn-outline-secondary" title="Back">
                <i class="fa fa-arrow-left"></i>
            </a>
            <input type="text" name="template_name" id="doc_title"
                   class="doc-title @error('template_name') border-danger @enderror"
                   value="{{ old('template_name', $template->template_name) }}" placeholder="Template Name *" required autocomplete="off">
            <input type="text" name="description" id="doc_desc"
                   class="doc-desc @error('description') border-danger @enderror"
                   value="{{ old('description', $template->description) }}" placeholder="Description (optional)" autocomplete="off">
            <div class="form-check form-switch mb-0 ms-1">
                <input class="form-check-input" type="checkbox" name="is_default" id="is_default"
                       value="1" {{ old('is_default', $template->is_default) ? 'checked' : '' }}>
                <label class="form-check-label small fw-semibold text-nowrap" for="is_default">Set as Default</label>
            </div>
            <div class="d-flex gap-2 ms-auto">
                <button type="button" class="btn btn-sm btn-outline-info" id="togglePreviewBtn">
                    <i class="fa fa-columns me-1"></i><span id="previewBtnText">Show Preview</span>
                </button>
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="fa fa-save me-1"></i> Save Changes
                </button>
            </div>
        </div>

        <div class="lct-chips">
            <span class="small me-1" style="color:#795548;white-space:nowrap;"><i class="fa fa-tags"></i> Insert:</span>
            @php
            $contractPlaceholders = [
                '{{tenant_name}}'     => 'Tenant Name',
                '{{property_name}}'   => 'Property',
                '{{unit_code}}'       => 'Unit Code',
                '{{ref_number}}'      => 'Lease Ref',
                '{{date_start}}'      => 'Start Date',
                '{{date_end}}'        => 'End Date',
                '{{years}}'           => 'Duration (Yrs)',
                '{{escalation_rate}}' => 'Escalation %',
                '{{rent_amount}}'     => 'Rent Amount',
                '{{signer_name}}'     => 'Signer Name',
                '{{signed_date}}'     => 'Signed Date',
            ];
            @endphp
            @foreach($contractPlaceholders as $ph => $label)
                <span class="ph-chip" data-ph="{{ $ph }}" title="{{ $ph }}">{{ $ph }}</span>
            @endforeach
        </div>

        <div class="lct-split" id="lctSplit">
            <div class="lct-editor-panel" id="editorPanel">
                <textarea id="html_body_editor" style="display:none;">{{ old('html_body', $template->html_body) }}</textarea>
            </div>
            <div class="lct-preview-panel" id="previewPanel">
                <div class="lct-preview-header">
                    <i class="fa fa-eye me-1"></i>
                    <strong>Live Preview</strong> — placeholders replaced with
                    <span style="background:#e3f2fd;color:#1565c0;border-radius:3px;padding:0 4px;font-weight:600;">sample data</span>
                </div>
                <div class="lct-preview-body">
                    <div class="a4-preview" id="previewContent"></div>
                </div>
            </div>
        </div>

    </div>
</form>

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-bs5.min.js"></script>
<script>
$(document).ready(function () {
    @verbatim
    const sampleData = {
        '{{tenant_name}}'     : 'John Doe',
        '{{property_name}}'   : 'Sunrise Apartments',
        '{{unit_code}}'       : 'A-101',
        '{{ref_number}}'      : 'A-101-L00001',
        '{{date_start}}'      : '1 May 2026',
        '{{date_end}}'        : '30 April 2027',
        '{{years}}'           : '1',
        '{{escalation_rate}}' : '5',
        '{{rent_amount}}'     : 'KES 25,000',
        '{{signer_name}}'     : '_____________________',
        '{{signed_date}}'     : '12 April 2026',
    };
    @endverbatim

    function cleanPaste(html) {
        var $tmp = $('<div>').html(html);
        $tmp.find('script, style, meta, link').remove();
        $tmp.find('*').removeAttr('style class id lang');
        $tmp.find('font').each(function() { $(this).replaceWith($(this).contents()); });
        $tmp.find('span').each(function() {
            if (!this.attributes.length) $(this).replaceWith($(this).contents());
        });
        $tmp.find('div').each(function() { $(this).replaceWith($('<p>').html($(this).html())); });
        return $tmp.html();
    }

    function updatePageBreaks() {
        var $area = $('.note-editing-area');
        var $editable = $area.find('.note-editable');
        if (!$area.length || !$editable.length) return;
        var PAGE_PX = 297 * (96 / 25.4);
        var offsetTop = ($editable.position() || {}).top || 24;
        var contentH  = $editable.outerHeight(true) || 0;
        $area.find('.pg-break').remove();
        var page = 1;
        var pos = offsetTop + PAGE_PX;
        while (pos < offsetTop + contentH + PAGE_PX && page <= 30) {
            $('<div class="pg-break"></div>')
                .attr('data-page', 'Page ' + (page + 1))
                .css('top', pos + 'px')
                .appendTo($area);
            page++;
            pos += PAGE_PX;
        }
    }

    $('#html_body_editor').summernote({
        iconType: 'awesome',
        toolbar: [
            ['style',    ['style']],
            ['font',     ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
            ['fontname', ['fontname']],
            ['fontsize', ['fontsize']],
            ['color',    ['color']],
            ['para',     ['ul', 'ol', 'paragraph']],
            ['table',    ['table']],
            ['insert',   ['link', 'hr']],
            ['view',     ['fullscreen', 'codeview']],
        ],
        fontNames: ['Arial', 'Arial Black', 'Courier New', 'Helvetica', 'Impact', 'Tahoma', 'Times New Roman', 'Verdana'],
        fontSizes: ['8', '9', '10', '11', '12', '14', '16', '18', '20', '24', '28', '32'],
        tabsize: 4,
        callbacks: {
            onChange: function (html) {
                updatePreview(html);
                setTimeout(updatePageBreaks, 50);
            },
            onPaste: function (e) {
                e.preventDefault();
                var cd = (e.originalEvent || e).clipboardData || window.clipboardData;
                var html = cd.getData('text/html');
                var text = cd.getData('text/plain');
                var content = html
                    ? cleanPaste(html)
                    : (text ? '<p>' + text.replace(/\n\n+/g,'</p><p>').replace(/\n/g,'<br>') + '</p>' : '');
                if (content) document.execCommand('insertHTML', false, content);
                setTimeout(updatePageBreaks, 100);
            }
        }
    });

    updatePageBreaks();

    /* Seed the preview with existing content */
    updatePreview($('#html_body_editor').summernote('code'));

    $('.ph-chip').on('click', function () {
        $('#html_body_editor').summernote('focus');
        $('#html_body_editor').summernote('insertText', $(this).data('ph'));
    });

    let previewOpen = false;

    function updatePreview(html) {
        if (!previewOpen) return;
        let preview = html || '';
        Object.entries(sampleData).forEach(([ph, val]) => {
            const esc = ph.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            preview = preview.replace(new RegExp(esc, 'g'), `<span class="sample-val">${val}</span>`);
        });
        $('#previewContent').html(preview.trim()
            ? preview
            : '<p style="text-align:center;font-family:sans-serif;color:#aaa;font-size:13px;">No content yet…</p>');
    }

    $('#togglePreviewBtn').on('click', function () {
        previewOpen = !previewOpen;
        if (previewOpen) {
            $('#previewPanel').addClass('open');
            $('#lctSplit').addClass('preview-open');
            $('#previewBtnText').text('Hide Preview');
            updatePreview($('#html_body_editor').summernote('code'));
        } else {
            $('#previewPanel').removeClass('open');
            $('#lctSplit').removeClass('preview-open');
            $('#previewBtnText').text('Show Preview');
        }
    });

    $('#lctForm').on('submit', function (e) {
        const content = $('#html_body_editor').summernote('code');
        if (!content.replace(/<[^>]+>/g, '').trim()) {
            e.preventDefault();
            toastr.error('Contract body cannot be empty.');
            return false;
        }
        if (!$('#doc_title').val().trim()) {
            e.preventDefault();
            toastr.error('Please enter a template name.');
            $('#doc_title').focus();
            return false;
        }
        $('#html_body_hidden').val(content);
    });
});
</script>
@endsection
@endsection
