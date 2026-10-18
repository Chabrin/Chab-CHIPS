@extends('layouts.main')
@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Employee Documents Report</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="{{ route('hr.reports.employee_documents') }}" class="row g-2 mb-3">
                            <div class="col-md-3">
                                <label for="document_type_id" class="form-label">Document Type</label>
                                <select class="form-select" id="document_type_id" name="document_type_id">
                                    <option value="">All types</option>
                                    @foreach($documentTypes as $type)
                                    <option value="{{ $type->id }}" {{ request('document_type_id') == $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="search" class="form-label">Employee</label>
                                <input type="text" class="form-control" id="search" name="search" value="{{ request('search') }}" placeholder="Name or staff number">
                            </div>
                            <div class="col-md-2">
                                <label for="from_date" class="form-label">Uploaded from</label>
                                <input type="date" class="form-control" id="from_date" name="from_date" value="{{ request('from_date') }}">
                            </div>
                            <div class="col-md-2">
                                <label for="to_date" class="form-label">Uploaded to</label>
                                <input type="date" class="form-control" id="to_date" name="to_date" value="{{ request('to_date') }}">
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                                <a href="{{ route('hr.reports.employee_documents') }}" class="btn btn-light btn-sm">Reset</a>
                            </div>
                        </form>

                        <div class="table-responsive custom-scrollbar">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Staff No.</th>
                                        <th>Document Type</th>
                                        <th>Title</th>
                                        <th>File</th>
                                        <th>Size</th>
                                        <th>Uploaded By</th>
                                        <th>Uploaded On</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($documents as $doc)
                                    <tr>
                                        <td>{{ $doc->employee->full_names ?? '-' }}</td>
                                        <td>{{ $doc->employee->staff_number ?? '-' }}</td>
                                        <td>
                                            {{ $doc->documentType->name ?? '-' }}
                                            @if($doc->documentType && $doc->documentType->is_mandatory)
                                            <span class="badge bg-danger ms-1">Mandatory</span>
                                            @endif
                                        </td>
                                        <td>{{ $doc->title }}</td>
                                        <td>{{ $doc->file_name }}</td>
                                        <td>{{ number_format($doc->file_size) }} KB</td>
                                        <td>{{ $doc->uploader->name ?? '-' }}</td>
                                        <td>{{ $doc->created_at ? $doc->created_at->format('d M Y H:i') : '-' }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('documents.view', $doc->id) }}" target="_blank" class="btn btn-light btn-sm">View</a>
                                            <a href="{{ route('documents.download', $doc->id) }}" class="btn btn-light btn-sm">Download</a>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="9" class="text-center text-muted py-4">No documents match these filters.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3">
                            {{ $documents->withQueryString()->links('pagination::bootstrap-5') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
