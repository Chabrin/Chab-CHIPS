@php($tabId = 'rp' . $return->id)
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="{{ $tabId }}-rent-tab" data-bs-toggle="tab" data-bs-target="#{{ $tabId }}-rent" type="button" role="tab" aria-controls="{{ $tabId }}-rent" aria-selected="true">Rent report</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="{{ $tabId }}-utility-tab" data-bs-toggle="tab" data-bs-target="#{{ $tabId }}-utility" type="button" role="tab" aria-controls="{{ $tabId }}-utility" aria-selected="false">Utility report</button>
    </li>
</ul>
<div class="tab-content">
    <div class="tab-pane fade show active" id="{{ $tabId }}-rent" role="tabpanel" aria-labelledby="{{ $tabId }}-rent-tab">
        <div class="d-flex justify-content-end gap-2 mb-2">
            <a class="btn btn-outline-primary btn-sm" href="{{ $pdfUrl }}?part=rent">Rent report PDF</a>
            <a class="btn btn-outline-primary btn-sm" href="{{ $excelUrl }}?part=rent">Rent report Excel</a>
        </div>
        @include('property.processed-returns.partials.statement', array_merge($rentStatement, ['return' => $return]))
    </div>
    <div class="tab-pane fade" id="{{ $tabId }}-utility" role="tabpanel" aria-labelledby="{{ $tabId }}-utility-tab">
        <div class="d-flex justify-content-end gap-2 mb-2">
            <a class="btn btn-outline-primary btn-sm" href="{{ $pdfUrl }}?part=utility">Utility report PDF</a>
            <a class="btn btn-outline-primary btn-sm" href="{{ $excelUrl }}?part=utility">Utility report Excel</a>
        </div>
        @include('property.processed-returns.partials.statement-utility', $utilityStatement)
    </div>
</div>
