@extends('layouts.main')

@section('content')
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">Payout Accounts Configuration</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="display" id="payout-accounts-table" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th style="max-width: 150px;">Client/Landlord</th>
                                        <th>Reference</th>
                                        <th>Accounts</th>
                                        <th>Properties</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        var table = $('#payout-accounts-table').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: '/ajax/payout/accounts/data',
                type: 'GET',
                dataSrc: 'data'
            },
            columns: [{
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    data: 'client_name',
                    render: function(data, type, row) {
                        return '<span style="word-wrap: break-word; max-width: 150px; display: inline-block;">' + (data || 'N/A') + '</span>';
                    }
                },
                {
                    data: 'client_reference'
                },
                {
                    data: 'accounts',
                    orderable: false,
                    render: function(data, type, row) {
                        if (data && data.length > 0) {
                            var html = '<small>';
                            data.forEach(function(account, index) {
                                if (index > 0) html += '<br>';
                                html += account.account_name + '<br>';
                                var bankName = account.bank_name && account.bank_name !== '-' && account.bank_name !== 'N/A' && account.bank_name.toLowerCase() !== 'unspecified bank' ? account.bank_name : '-';
                                html += account.account_number + ' (' + bankName + ')';
                            });
                            html += '</small>';
                            return html;
                        }
                        return '<span class="text-muted">No accounts</span>';
                    }
                },
                {
                    data: 'properties',
                    orderable: false,
                    render: function(data, type, row) {
                        if (data && data.length > 0) {
                            var html = '<div style="border-collapse: collapse;">';
                            data.forEach(function(property, index) {
                                if (index > 0) html += '<div style="border-top: 1px solid #dee2e6; margin: 2px 0;"></div>';
                                html += '<small>' + property.name + '</small>';
                            });
                            html += '</div>';
                            return html;
                        }
                        return '<span class="text-muted">No properties</span>';
                    }
                }
            ],
            order: [
                [1, 'asc']
            ],
            pageLength: 25,
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, "All"]
            ]
        });
    });
</script>
@endsection