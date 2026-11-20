@extends('layouts.main')
@section('content')
<div class="page-body container-fluid">
    <h1>Approval Setup</h1>
    <h2>New Tenant Onboarding</h2>
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <p>The preparing Field Officer signs when saving the tenant. Choose the approval roles that follow. The last step gives final approval to the tenant and lease.</p>
    <p>Before adding an approver role, grant it the <strong>New Tenant Approvals</strong> module and the <strong>view_tenant_onboarding</strong> and <strong>review_tenant_onboarding</strong> permissions in Roles. Selecting a role here does not grant those permissions.</p>
    <p>Publishing applies the new sequence to new tenant submissions. Reviews already in progress, including returned corrections, keep their saved version.</p>
    <form id="tenant-workflow-form" action="{{ route('tenant-reviews.publish') }}" method="POST">
        @csrf
        <input type="hidden" name="version" value="{{ $version->id }}">
        <p><strong>First step</strong> · {{ $version->steps[0]['label'] }} · Save and Sign</p>
        <div id="tenant-workflow-steps">
            @foreach(array_slice($version->steps, 1) as $index => $step)
            <div class="workflow-step border rounded p-3 mb-2 d-flex align-items-end flex-wrap gap-2">
                <label class="flex-grow-1">Approver role
                    <select name="steps[{{ $index }}][role_id]" class="form-select" required>
                    @foreach($roles as $role)<option value="{{ $role->id }}" @selected($role->id == $step['role_id'])>{{ $role->display_name ?: $role->name }}</option>@endforeach
                    </select>
                </label>
                <button type="button" class="btn btn-outline-secondary" data-move="up" aria-label="Move step up">Move up</button>
                <button type="button" class="btn btn-outline-secondary" data-move="down" aria-label="Move step down">Move down</button>
                <button type="button" class="btn btn-outline-danger" data-remove>Remove step</button>
            </div>
            @endforeach
        </div>
        <p>Zone Manager steps use the manager assigned to the property's zone. Other steps allow an active account holding the selected role. Each approval needs a different person, and the preparer cannot approve a later step.</p>
        <button type="button" class="btn btn-outline-primary" id="tenant-workflow-add">Add step</button>
        <button class="btn btn-primary">Publish New Version</button>
    </form>
    <h2 class="mt-4">Published versions</h2>
    <div class="table-responsive"><table class="table">
        <thead><tr><th>Version</th><th>Sequence</th><th>Published by</th><th>Published on</th></tr></thead>
        <tbody>@foreach($history as $published)
            <tr><td>{{ $published->number }}</td><td>{{ implode(' → ', array_column($published->steps, 'label')) }}</td><td>{{ $published->author->name ?? 'Initial setup' }}</td><td>{{ $published->created_at }}</td></tr>
        @endforeach</tbody>
    </table></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('tenant-workflow-form');
    const list = document.getElementById('tenant-workflow-steps');
    const add = document.getElementById('tenant-workflow-add');
    const template = list.firstElementChild.cloneNode(true);

    function refreshSteps() {
        const rows = Array.from(list.children);
        rows.forEach(function (row, index) {
            row.querySelector('select').name = 'steps[' + index + '][role_id]';
            row.querySelector('[data-move="up"]').disabled = index === 0;
            row.querySelector('[data-move="down"]').disabled = index === rows.length - 1;
            row.querySelector('[data-remove]').disabled = rows.length === 1;
        });
        add.disabled = rows.length >= 11;
    }

    form.addEventListener('click', function (event) {
        const button = event.target.closest('button');
        if (!button || button.type === 'submit') return;
        if (button === add && list.children.length < 11) {
            list.append(template.cloneNode(true));
        } else {
            const row = button.closest('.workflow-step');
            if (!row) return;
            if (button.hasAttribute('data-remove') && list.children.length > 1) row.remove();
            if (button.dataset.move === 'up' && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
            if (button.dataset.move === 'down' && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
        }
        refreshSteps();
    });
    refreshSteps();
});
</script>
@endsection
