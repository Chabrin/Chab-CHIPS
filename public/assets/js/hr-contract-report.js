(function (root, $) {
    'use strict';
    $(function () {
        const table = root.document.getElementById('contracts_table');
        if (!table) return;
        const api = root.ContractCsvExport;
        const params = new URLSearchParams(root.location.search);
        let report = {}, loaded = false, applied = null, contractsTable;
        let selectedId = params.get('contract_id');
        let initial = true;
        const text = $.fn.dataTable.render.text();
        const downloads = $('#contracts-export, #contracts-print, #contracts-expiry-export');
        const defaultFilters = () => ({period:'all', from:'', to:'', scope:'history', department:'', search:'', selectedId:null});

        function controls(filters) {
            $('#contract-report-window').val(filters.period);
            $('#contract-from').val(filters.from || '');
            $('#contract-to').val(filters.to || '');
            $('#contract-scope').val(filters.scope);
            $('#contract-department').val(filters.department);
            $('#contract-employee-search').val(filters.search);
            dateControls();
        }
        function dateControls() {
            const review = $('#contract-scope').val() === 'review';
            $('#contract-report-window').prop('disabled', review);
            $('#contract-from, #contract-to').prop('disabled', review || $('#contract-report-window').val() === 'all').prop('readOnly', $('#contract-report-window').val() === '30');
            $('#contract-scope-help').text(review
                ? 'Showing contracts with missing or invalid dates for active/on-leave employees. A date range cannot be applied to these records. Department and employee filters still apply.'
                : 'All contracts preserves the existing listing, including older, unapproved and undated records. Optional calendar dates filter contract end dates inclusively. Current approved scope excludes superseded contracts. Employee coverage follows the existing listing.');
        }
        function readControls() {
            const period = $('#contract-report-window').val();
            return {period, from:period === '30' ? report.as_of : $('#contract-from').val(), to:period === '30' ? report.through : $('#contract-to').val(),
                scope:$('#contract-scope').val(), department:$('#contract-department').val() || '', search:$('#contract-employee-search').val().trim(), selectedId};
        }
        function address() {
            const url = new URL(root.location.href);
            ['window','from','to','scope','department','search','contract_id'].forEach(key => url.searchParams.delete(key));
            url.searchParams.set('window', applied.period);
            if (applied.period === 'custom') { url.searchParams.set('from', applied.from); url.searchParams.set('to', applied.to); }
            if (applied.scope !== 'current') url.searchParams.set('scope', applied.scope);
            if (applied.department) url.searchParams.set('department', applied.department);
            if (applied.search) url.searchParams.set('search', applied.search);
            if (applied.selectedId) url.searchParams.set('contract_id', applied.selectedId);
            root.history.replaceState({}, '', url);
        }
        function describe() {
            $('#contract-active-filters').text(api.reportDescription({filters:applied}));
            $('#contract-alert-filter').toggleClass('d-none', !applied.selectedId);
        }
        function apply() {
            if (!loaded) return;
            const next = readControls();
            const error = api.validateFilters(next);
            $('#contract-filter-error').text(error);
            if (error) { downloads.prop('disabled', true); return; }
            applied = next;
            $('#contract-filter-pending').text('');
            address(); describe(); contractsTable.draw();
        }
        function failed() {
            loaded = false;
            $('#contracts-refresh').prop('disabled', false);
            downloads.prop('disabled', true);
            $('#contracts-apply, #contracts-reset').prop('disabled', true);
            $('#contract-load-error').removeClass('d-none');
            $('#contracts-expiring-count').text('Unavailable');
            $('#contract-report-generated').text('Refresh failed. Any rows below are from the previous report.');
        }
        $.fn.dataTable.ext.search.push(function (settings, data, index, row) {
            return settings.nTable !== table || (applied && api.matchesFilters(row, applied));
        });
        // Register before initial AJAX so controls also update on the first draw.
        $(table).on('draw.dt', function () {
            const dt = $(table).DataTable();
            const count = dt.rows({search:'applied', page:'all'}).count();
            downloads.prop('disabled', !loaded || !count || !!$('#contract-filter-pending').text() || !!api.validateFilters(applied || {}));
            $('#contracts-export-status').text(loaded ? count + ' matching contract(s). Downloads include all matching pages in the selected sort order.' : 'Loading report...');
        }).on('preXhr.dt', function () {
            loaded = false;
            downloads.prop('disabled', true);
            $('#contracts-apply, #contracts-reset').prop('disabled', true);
            $('#contracts-refresh').prop('disabled', true);
            $('#contract-report-generated').text('Refreshing report...');
        });
        contractsTable = $(table).DataTable({
            processing:true, serverSide:false, autoWidth:false, order:[[1,'desc']], dom:'lfrtip',
            language:{emptyTable:'No contracts to display.', zeroRecords:'No contracts match these filters. Try another date range, department or employee, or Reset.'},
            ajax:{url:table.dataset.endpoint, type:'GET', dataType:'json', cache:false,
                dataSrc(response) {
                    if (!Array.isArray(response.contracts) || !response.report || !response.report.as_of || !response.report.through) { failed(); return []; }
                    report = response.report;
                    const departments = $('#contract-department').empty().append(new Option('All departments',''));
                    departments.append(new Option('Not set','__missing__'));
                    [...new Set(response.contracts.map(row => row.department).filter(Boolean))].sort().forEach(name => departments.append(new Option(name,name)));
                    if (initial) {
                        applied = defaultFilters();
                        if (params.get('window') === '30') Object.assign(applied, {period:'30', scope:'current', from:report.as_of, to:report.through});
                        if (params.get('window') === 'custom') Object.assign(applied, {period:'custom', scope:'current', from:params.get('from') || '', to:params.get('to') || ''});
                        if (['current','history','review'].includes(params.get('scope'))) applied.scope = params.get('scope');
                        Object.assign(applied, {department:params.get('department') || '', search:params.get('search') || '', selectedId});
                        // Old individual notifications must still find historical contracts.
                        if (selectedId) {
                            const row = response.contracts.find(item => String(item.id) === String(selectedId));
                            if (row) Object.assign(applied, {scope:['missing_dates','invalid_dates'].includes(row.expiry?.status) ? 'review' : 'history', period:'custom', from:row.date_end, to:row.date_end});
                        }
                        initial = false;
                    } else if (applied.period === '30') Object.assign(applied, {from:report.as_of, to:report.through});
                    // Preserve unavailable bookmarked departments so a missing option never broadens the filter.
                    if (applied.department && !Array.from(departments[0].options).some(option => option.value === applied.department)) departments.append(new Option(applied.department,applied.department));
                    controls(applied);
                    const error = api.validateFilters(applied);
                    $('#contract-filter-error').text(error);
                    $('#contract-filter-pending').text('');
                    loaded = true;
                    $('#contracts-apply, #contracts-reset').prop('disabled', false);
                    $('#contracts-refresh').prop('disabled', false);
                    $('#contract-load-error').addClass('d-none');
                    $('#contracts-expiring-count').text(report.due_30 ?? 'Unavailable');
                    $('#contracts-default-period').text(report.as_of + ' to ' + report.through + ' (inclusive)');
                    $('#contract-report-generated').text('Generated ' + report.generated_at + ' (Africa/Nairobi). Refresh report for the latest records.');
                    const reviewCount = response.contracts.filter(row => ['missing_dates','invalid_dates'].includes(row.expiry?.status)).length;
                    $('#contract-data-review').toggleClass('d-none', !reviewCount).text(reviewCount + ' contract(s) have missing or invalid dates. Select Records needing date review to inspect them; they are excluded from date-based reports.');
                    describe();
                    return response.contracts;
                }, error:failed},
            columns:[
                {data:null, orderable:false, searchable:false, render:(data,type,row,meta) => meta.row + 1},
                {data:'date_time', defaultContent:'', render(value,type) { return type === 'display' ? (value ? new Date(value).toLocaleString() : '-') : value; }},
                {data:null, render(data,type,row) { const label = (row.staff_number || '-') + ' - ' + (row.employee?.name || '-'); return type === 'display' ? text.display(label) : label; }},
                {data:'position.position_name', defaultContent:'Not set', render:text},
                {data:'date_start', defaultContent:'Not set', render:text},
                {data:'date_end', defaultContent:'Not set', render:text},
                {data:'basic_salary', defaultContent:'', render(value,type) { return type === 'display' ? (value === null || value === undefined ? '-' : text.display('KES ' + value)) : value; }},
                {data:'created_by.name', defaultContent:'-', render:text},
                {data:'department', defaultContent:'Not set', render:text},
                {data:'approval_status', defaultContent:'Unknown', render:text},
                {data:'expiry', render(expiry,type) {
                    if (type !== 'display') return expiry?.label || '';
                    const statuses = {expired:['danger','Expired'], expiring_soon:['warning text-dark','Expiring soon'], active:['success','Active'], upcoming:['info text-dark','Upcoming'], missing_dates:['secondary','Missing dates'], invalid_dates:['danger','Invalid dates']};
                    const [color,label] = statuses[expiry?.status] || ['secondary','Unknown'];
                    return '<span class="badge bg-' + color + '">' + label + '</span>';
                }},
                {data:'expiry.days_remaining', defaultContent:'', render(days,type,row) {
                    if (type !== 'display') return days;
                    if (days === null || days === undefined || row.expiry?.status === 'invalid_dates') return 'Not available';
                    if (days === 0) return 'Expires today';
                    return Math.abs(days) + (Math.abs(days) === 1 ? ' day' : ' days') + (days < 0 ? ' overdue' : ' remaining');
                }},
                {data:'id', orderable:false, searchable:false, render(id,type,row) {
                    if (type !== 'display' || !row.employee?.encrypted_id) return '';
                    const key = encodeURIComponent(row.employee.encrypted_id);
                    return '<div class="d-flex flex-row gap-1">' + (row.can_read ? '<a class="staff-link" href="/hr/employees/' + key + '" title="View" aria-label="View employee"><span class="material-symbols-outlined view-icon" style="font-size:18px;">visibility</span></a><div class="spinner-border spinner-border-sm text-primary row-spinner" role="status" style="display:none;"><span class="visually-hidden">Loading...</span></div>' : '') +
                        (row.can_update ? '<a href="/hr/contracts/' + key + '/edit" title="Edit" aria-label="Edit contracts"><span class="material-symbols-outlined" style="font-size:18px;">edit_document</span></a>' : '') + '</div>';
                }}
            ]
        });
        $('#contract-filters').on('submit', event => { event.preventDefault(); apply(); });
        $('#contract-filters input, #contract-filters select').on('input change', function () {
            $('#contract-filter-pending').text('Filters changed. Click Apply filters to update the report and downloads.');
            downloads.prop('disabled', true);
        });
        $('#contract-report-window').on('change', function () {
            if (this.value === '30') { $('#contract-from').val(report.as_of); $('#contract-to').val(report.through); $('#contract-scope').val('current'); }
            if (this.value === 'all') { $('#contract-from, #contract-to').val(''); $('#contract-scope').val('history'); }
            dateControls();
        });
        $('#contract-scope').on('change', dateControls);
        $('#contracts-reset').on('click', function () { selectedId = null; if (contractsTable.search) contractsTable.search(''); controls(defaultFilters()); apply(); });
        $('#clear-contract-alert').on('click', function () { selectedId = null; apply(); });
        $('#contracts-refresh').on('click', function () { contractsTable.ajax.reload(null, false); });
        function exportReport(method, kind = 'full') {
            if (!loaded || $('#contract-filter-pending').text() || api.validateFilters(applied)) return;
            if (kind === 'expiry' && !expiryReportAvailable()) return;
            try {
                const count = api[method](contractsTable, {...report, kind, table_search:contractsTable.search ? contractsTable.search() : '', filters:{...applied}});
                $('#contracts-export-status').text(method === 'print' ? 'Print preview contains ' + count + ' contracts. Choose Save as PDF in the print dialog to download a PDF.' : 'Downloaded ' + count + ' matching contracts.');
            } catch (error) { $('#contracts-export-status').text(method === 'print' ? 'Unable to open print preview. Allow pop-up windows for this site and try again.' : 'Unable to export. Please try again.'); }
        }
        $('#contracts-export').on('click', () => exportReport('download'));
        $('#contracts-expiry-export').on('click', () => exportReport('download', 'expiry'));
        $('#contracts-print').on('click', () => exportReport('print'));

        function expiryReportAvailable() { return applied && applied.scope === 'current' && ['30','custom'].includes(applied.period); }
        $(table).on('draw.dt', function () {
            if (!expiryReportAvailable()) $('#contracts-expiry-export').prop('disabled', true);
        });
    });
})(window, jQuery);
