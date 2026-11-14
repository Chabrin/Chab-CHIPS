@extends('layouts.main')
@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/clients-list.css') }}?v={{ filemtime(public_path('assets/css/clients-list.css')) }}">
@endsection
@section('content')
<div class="page-body" id="clients-list" data-clients-url="{{ route('property.clients.index') }}" data-data-url="{{ route('property.clients.data') }}">
    <div class="container-fluid">
        <section class="card client-list-card" aria-labelledby="clientsListTitle">
            <header class="card-header client-list-header">
                <div>
                    <span class="client-list-eyebrow">Management</span>
                    <h1 id="clientsListTitle">Clients</h1>
                </div>
                <div class="d-flex align-items-center flex-wrap gap-2">
                    @permission('create_clients')
                    <a href="{{ route('property.clients.create') }}" class="btn btn-primary client-add-button">
                        <span class="material-symbols-outlined" aria-hidden="true">add</span>Add Client
                    </a>
                    @endpermission
                </div>
            </header>
            <div class="card-body client-list-body">
                <div class="client-list-toolbar">
                    <label class="client-list-search" for="clientListSearch">
                        <span>Search clients</span>
                        <span class="client-list-search-input">
                            <span class="material-symbols-outlined" aria-hidden="true">search</span>
                            <input type="search" id="clientListSearch" placeholder="Name, phone, ID number or client reference…" autocomplete="off">
                        </span>
                    </label>
                    <button type="button" id="clientListFilterToggle" class="btn client-list-secondary" aria-expanded="false" aria-controls="clientListFilters">
                        <span class="material-symbols-outlined" aria-hidden="true">tune</span>Filters<span id="clientListFilterCount" class="client-list-filter-count" hidden></span>
                    </button>
                    <label class="client-list-sort" for="clientListSort">
                        <span>Sort by</span>
                        <select id="clientListSort" class="form-select">
                            <option value="name">Name A–Z</option>
                            <option value="reverse">Name Z–A</option>
                            <option value="newest">Newest registered</option>
                            <option value="oldest">Oldest registered</option>
                            <option value="custom" hidden disabled>Table column order</option>
                        </select>
                    </label>
                </div>
                <div class="client-list-filters" id="clientListFilters" role="group" aria-label="Filter clients by specific fields" hidden>
                    <label for="clientFilterName">Client name<input type="search" id="clientFilterName" class="form-control" data-client-filter="2" placeholder="Enter a name" autocomplete="off"></label>
                    <label for="clientFilterPhone">Phone<input type="search" id="clientFilterPhone" class="form-control" data-client-filter="3" placeholder="Enter a phone number" autocomplete="off"></label>
                    <label for="clientFilterId">ID number<input type="search" id="clientFilterId" class="form-control" data-client-filter="4" placeholder="Enter an ID number" autocomplete="off"></label>
                    <label for="clientFilterReference">Client reference<input type="search" id="clientFilterReference" class="form-control" data-client-filter="5" placeholder="e.g. LAN-00146" autocomplete="off"></label>
                </div>
                <div class="client-list-results">
                    <span id="clientListResults" role="status" aria-live="polite">Loading clients…</span>
                    <button type="button" id="clientListClear" hidden>Clear search &amp; filters</button>
                </div>
                <div id="clientListError" class="client-list-error" role="alert" hidden>
                    <span class="material-symbols-outlined" aria-hidden="true">error_outline</span>
                    <div><strong>Could not load clients</strong><p id="clientListErrorDetail">Please try again.</p></div>
                    <button type="button" id="clientListRetry" class="btn client-list-secondary">Try again</button>
                </div>
                <div id="clientListTableArea" aria-busy="true" hidden>
                    <div class="table-responsive custom-scrollbar">
                        <table class="display" id="clients-table" aria-label="Clients" style="width:100%;">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Internal ID</th>
                                    <th scope="col">Client</th>
                                    <th scope="col">Phone</th>
                                    <th scope="col">ID number</th>
                                    <th scope="col">Client reference</th>
                                    <th scope="col">Registered</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection
@section('scripts')
<script src="{{ asset('assets/js/clients-list.js') }}?v={{ filemtime(public_path('assets/js/clients-list.js')) }}"></script>
@endsection
