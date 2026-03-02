

<?php $__env->startSection('content'); ?>
<div class="page-body" style="padding-top:10px;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header card-no-border d-flex align-items-center">
                        <h5 class="mb-0">Client Returns Batches</h5>
                        <div class="ms-auto d-flex gap-2">
                            <a href="<?php echo e(route('returns.generate.index')); ?>" class="btn btn-primary btn-sm d-flex align-items-center">
                                <span class="material-symbols-outlined">add_circle</span>
                                <span class="ms-1">Generate Returns</span>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive custom-scrollbar">
                            <table class="table" id="basic-1">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Batch Number</th>
                                        <th>Property</th>
                                        <th>Returns Count</th>
                                        <th>Total Payable</th>
                                        <th>Approvals</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Loaded via Ajax -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
<script>
    $(document).ready(function() {
        let batchesTable;

        // Load batches via Ajax
        function loadBatches() {
            if ($.fn.DataTable.isDataTable('#basic-1')) {
                $('#basic-1').DataTable().clear().destroy();
            }

            batchesTable = $('#basic-1').DataTable({
                processing: true,
                ajax: {
                    url: '/ajax/returns/batches/data',
                    dataSrc: 'data'
                },
                columns: [{
                        data: null,
                        render: function(data, type, row, meta) {
                            return meta.row + 1;
                        }
                    },
                    {
                        data: 'batch_number'
                    },
                    {
                        data: 'property_name',
                        render: function(data, type, row) {
                            if (row.encrypted_id) {
                                return `<a href="/returns/batches/${row.encrypted_id}/details" class="property-link">${data}</a>`;
                            }
                            return data;
                        }
                    },
                    {
                        data: 'returns_count'
                    },
                    {
                        data: 'total_payable_amount',
                        render: function(data, type, row) {
                            return `<strong>${data}</strong>`;
                        }
                    },
                    {
                        data: 'approval_status',
                        render: function(data, type, row) {
                            if (data == 1) {
                                return '<span class="badge badge-light-success">Approved</span>';
                            } else if (data == 0) {
                                return 'Pending';
                            } else if (data == 2) {
                                return '<span class="badge badge-light-danger">Rejected</span>';
                            } else if (data == 101) {
                                return '<span class="badge badge-light-success">InProgress</span>';
                            } else if (data == 102) {
                                return '<span class="badge badge-light-success">Approved</span>';
                            } else {
                                return 'Unknown';
                            }
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        render: function(data, type, row) {
                            return `<a href="/returns/batches/${row.encrypted_id}/details" class="d-flex align-items-center text-decoration-none" title="View Details">
                               Details
                            </a>`;
                        }
                    }
                ],
                order: [
                    [1, 'desc']
                ]
            });
        }

        loadBatches();
    });
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.main', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\CHIPS\chips_2025\resources\views/property/returns/list.blade.php ENDPATH**/ ?>