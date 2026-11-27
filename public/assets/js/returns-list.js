window.ReturnsList = (function () {
    'use strict';
    const escape = value => String(value ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
    const money = value => new Intl.NumberFormat('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value || 0);
    const detailsUrl = row => '/returns/' + encodeURIComponent(row.encrypted_id) + '/details';
    const typeLabel = row => row.group === 'merged' ? (String(row.return_code).startsWith('RU') ? 'Merged utilities' : 'Merged rent')
        : ({ MAINRETURN: 'Main', MINIRETURN: 'Mini', UTILITYRETURN: 'Utility' }[row.return_type] || row.return_type || 'Return');
    function matchesStatus(row, selected) {
        if (!selected) return true;
        if (selected.startsWith('level:')) return matchesLevel(row, selected.slice(6));
        return row.status_key === selected;
    }
    function matchesLevel(row, level) {
        return !level || (['awaiting', 'returned'].includes(row.status_key) && row.current_level === Number(level));
    }
    function matchesRow(row, property, status, level = '') {
        const children = row.children || [];
        if (row.group === 'merged') {
            if (!property && !level && matchesStatus(row, status)) return true;
            // All selected filters must match the same property return.
            return children.some(child => (!property || child.property_code === property) && matchesLevel(child, level) &&
                (matchesStatus(child, status) || (!level && matchesStatus(row, status))));
        }
        return (!property || (row.property_codes || []).includes(property)) && matchesStatus(row, status) && matchesLevel(row, level);
    }
    function totals(rows) {
        return { count: rows.length, net: rows.reduce((sum, row) => sum + (row.include_in_total === false ? 0 : Number(row.net_amount_raw || 0)), 0),
            excludesComponents: rows.some(row => row.include_in_total === false) };
    }
    function drafts(rows, property, status, level = '') {
        const found = new Map();
        rows.flatMap(row => row.group === 'merged' ? row.children || [] : [row]).forEach(row => {
            if (row.is_draft && (!property || row.property_code === property) && matchesStatus(row, status) && matchesLevel(row, level)) found.set(row.id, row);
        });
        return [...found.values()];
    }
    function statusHtml(row) {
        const colour = ({ approved: 'success', paid: 'success', draft: 'secondary', returned: 'danger', rejected: 'danger', review: 'warning', awaiting: 'info', processing: 'warning' })[row.status_key] || 'secondary';
        let html = '<span class="badge badge-light-' + colour + ' return-status">' + escape(row.stage) + '</span>';
        if (row.next_approver) html += '<small class="return-subtext return-next-approver"><b>Next:</b> ' + escape(row.next_approver) + '</small>';
        if (row.approval_summary) html += '<small class="return-subtext text-muted">' + escape(row.approval_summary) + '</small>';
        if (row.status_note) html += '<small class="return-subtext text-muted">' + escape(row.status_note) + '</small>';
        return html;
    }
    function unitsHtml(row) {
        if (row.group === 'merged') return '&mdash;';
        let html = escape(row.payable_units || '-');
        if (row.not_payable_reason) html += '<small class="return-subtext text-muted">' + escape((Number(String(row.withheld).replace(/,/g, '')) > 0 ? 'Withheld ' + row.withheld + ': ' : '') + row.not_payable_reason) + '</small>';
        return html;
    }
    function actionsHtml(row) {
        return '<a href="' + detailsUrl(row) + '">View<span class="visually-hidden"> return ' + escape(row.return_code) + '</span></a>';
    }
    function childrenHtml(row) {
        let html = '<div class="return-components"><div class="table-responsive"><table class="table table-sm align-middle">' +
            '<caption>Property returns in ' + escape(row.return_code) + '</caption><thead><tr><th>Property / Return</th><th>Type</th><th class="text-end">Net</th><th>Units</th><th>Approval progress</th><th>Action</th></tr></thead><tbody>';
        (row.children || []).forEach(child => {
            html += '<tr><td>' + escape(child.property_name) + '<small class="return-subtext text-muted">' + escape(child.property_code) + ' &middot; ' + escape(child.return_code) + '</small></td>' +
                '<td>' + escape(typeLabel(child)) + '</td><td class="text-end">' + escape(child.net_amount) + '</td><td>' + unitsHtml(child) + '</td><td>' + statusHtml(child) + '</td><td>' + actionsHtml(child) + '</td></tr>';
        });
        if (!(row.children || []).length) html += '<tr><td colspan="6">No linked property returns are available. Open the merged return to review its records.</td></tr>';
        html += '</tbody></table></div>';
        if (row.hidden_child_count) html += '<p>' + escape(row.hidden_child_count) + ' additional property return(s) are outside your permitted properties.</p>';
        return html + '<p>Component amounts are a breakdown of this merged return; they are not added to the list total.</p></div>';
    }
    function init($, config) {
        let records = [], activeGroup = 'individual', loading = false;
        const expanded = new Set();
        const filter = () => ({ property: $('#property_select').val(), status: $('#status_filter').val(), level: $('#level_filter').val() });
        const table = $('#returns-table').DataTable({
            data: [], pageLength: 50, orderCellsTop: true, order: [[2, 'asc']],
            language: { emptyTable: 'No returns found for this period.', zeroRecords: 'No returns match these filters.' },
            columns: [
                { data: null, orderable: false, searchable: false, render: (d, t, row) => row.group === 'merged' ? '<button type="button" class="btn btn-outline-primary btn-sm return-expand" data-return-expand aria-expanded="false" aria-label="Expand properties for ' + escape(row.return_code) + '">+</button>' : '' },
                { data: null, orderable: false, searchable: false, render: (d, t, r, meta) => meta.row + 1 },
                { data: 'client_name', render: (d, t, row) => t === 'display' ? escape(d) + '<small class="return-subtext text-muted">' + escape(row.return_code) + '</small>' : d + ' ' + row.return_code },
                { data: 'property_name', render: (d, t, row) => t === 'display' ? escape(d || 'Property links need review') + (row.is_component ? '<small class="return-subtext text-muted">' + escape(row.membership_note || 'Included in merged return') + '</small>' : '') : d },
                { data: 'return_type', render: (d, t, row) => escape(typeLabel(row)) },
                { data: 'net_amount', className: 'text-end', searchable: false, render: (d, t, row) => t === 'display' ? escape(d) : row.net_amount_raw },
                { data: 'payable_units', render: (d, t, row) => t === 'display' ? unitsHtml(row) : d },
                { data: 'stage', render: (d, t, row) => t === 'display' ? statusHtml(row) : [d, row.next_approver, row.status_note, row.approval_summary, ...(row.children || []).map(c => c.stage)].filter(Boolean).join(' ') },
                { data: null, orderable: false, searchable: false, render: (d, t, row) => actionsHtml(row) }
            ]
        });
        $('#returns-table thead .column-filters th').each(function (index) {
            $('input', this).on('keyup change', function () { table.column(index).search(this.value).draw(); });
        });
        $('#property_select').select2({ width: '100%' });
        $.fn.dataTable.ext.search.push(function (settings, data, index) {
            if (settings.nTable.id !== 'returns-table') return true;
            const selected = filter(), row = table.row(index).data();
            return matchesRow(row, selected.property, selected.status, selected.level);
        });
        function shownRows() { return table.rows({ search: 'applied' }).data().toArray(); }
        function refresh() {
            const shown = shownRows(), summary = totals(shown), selected = filter();
            $('#result_count').text(summary.count); $('#returns_net_total').text(money(summary.net));
            $('#component_total_note').prop('hidden', !summary.excludesComponents);
            const count = drafts(shown, selected.property, selected.status, selected.level).length;
            $('#draft_count').text(count); $('#forward_btn').prop('hidden', config.canForward === false || !count || loading);
            table.rows({ page: 'current' }).every(function () {
                const row = this.data();
                if (row.group === 'merged' && expanded.has(row.id)) {
                    this.child(childrenHtml(row)).show(); $(this.node()).find('[data-return-expand]').attr('aria-expanded', 'true').html('&minus;');
                }
            });
        }
        table.on('draw', refresh);
        $('#returns-table tbody').on('click', '[data-return-expand]', function () {
            const row = table.row($(this).closest('tr')), data = row.data();
            if (row.child.isShown()) { row.child.hide(); expanded.delete(data.id); $(this).attr('aria-expanded', 'false').text('+'); }
            else { row.child(childrenHtml(data)).show(); expanded.add(data.id); $(this).attr('aria-expanded', 'true').html('&minus;'); }
        });
        function showGroup(group) {
            activeGroup = group;
            $('[data-return-group]').each(function () {
                const active = $(this).attr('data-return-group') === group;
                $(this).toggleClass('active', active).attr('aria-selected', String(active)).attr('tabindex', active ? '0' : '-1');
            });
            $('#returns_panel').attr('aria-labelledby', group === 'merged' ? 'merged_returns_tab' : 'individual_returns_tab');
            $('#returns_view_help').text(group === 'merged' ? 'One combined return per row. Expand a row to review each property and its approval progress.' : 'Individual returns awaiting approval or payment.');
            table.clear(); table.rows.add(records.filter(row => row.group === group)); table.draw(); table.columns.adjust();
        }
        $('[data-return-group]').on('click', function () { showGroup($(this).attr('data-return-group')); });
        $('[data-return-group]').on('keydown', function (event) {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault(); const group = event.key === 'Home' ? 'individual' : event.key === 'End' ? 'merged' : activeGroup === 'merged' ? 'individual' : 'merged';
            showGroup(group); $('#' + (group === 'merged' ? 'merged_returns_tab' : 'individual_returns_tab')).trigger('focus');
        });
        $('#property_select, #status_filter, #level_filter').on('change', () => table.draw());
        function fillFilters(response) {
            const property = $('#property_select'), previous = property.val();
            property.empty().append($('<option>').val('').text('All properties'));
            (response.properties || []).forEach(p => property.append($('<option>').val(p.code).text(p.name + ' (' + p.code + ')')));
            property.val((response.properties || []).some(p => p.code === previous) ? previous : '').trigger('change.select2');
            const levels = response.approval_levels || [], level = $('#level_filter'), oldLevel = Number(level.val());
            level.empty().append($('<option>').val('').text('All levels'));
            levels.forEach(number => level.append($('<option>').val(String(number)).text('Level ' + number + (number === levels[levels.length - 1] ? ' (final)' : ''))));
            level.val(levels.includes(oldLevel) ? String(oldLevel) : '');
            $('#workflow_notice').prop('hidden', response.workflow_configured !== false);
        }
        function loadData() {
            if (loading) return;
            loading = true; const year = $('#year_select').val(), month = $('#month_select').val();
            $('#search_spinner').prop('hidden', false); $('#search_label').text('Loading...');
            $('#search_btn, #year_select, #month_select').prop('disabled', true); $('#forward_btn').prop('hidden', true); $('#returns_error').prop('hidden', true);
            $('#returns_panel').attr('aria-busy', 'true');
            $.ajax({ url: config.dataUrl, type: 'GET', data: { year, month, generation: year === config.year && month === config.month ? config.generation : null },
                success(response) {
                    if (!response.success) { fail(response.message); return; }
                    records = response.data || []; expanded.clear(); fillFilters(response);
                    $('#individual_count').text(records.filter(r => r.group === 'individual').length); $('#merged_count').text(records.filter(r => r.group === 'merged').length);
                    if (config.generation && !records.some(r => r.group === activeGroup) && records.length) activeGroup = records[0].group;
                    showGroup(activeGroup);
                },
                error(xhr) { fail(xhr.responseJSON?.message); },
                complete() { loading = false; $('#search_spinner').prop('hidden', true); $('#search_label').text('Search'); $('#search_btn, #year_select, #month_select').prop('disabled', false); $('#returns_panel').attr('aria-busy', 'false'); refresh(); }
            });
        }
        function fail(message) {
            records = []; expanded.clear(); showGroup(activeGroup); $('#individual_count, #merged_count').text('0');
            $('#returns_error').text(message || 'Could not load returns. Search again to retry.').prop('hidden', false);
        }
        $('#forward_btn').on('click', function () {
            const selected = filter(), rows = drafts(shownRows(), selected.property, selected.status, selected.level);
            if (!rows.length || !window.confirm('Forward ' + rows.length + ' draft return(s) to the zone manager for verification?')) return;
            const button = $(this).prop('disabled', true);
            $.ajax({ url: config.forwardUrl, type: 'POST', data: { _token: $('meta[name="csrf-token"]').attr('content'), ids: rows.map(r => r.encrypted_id) },
                success(response) { window.alert(response.message); loadData(); }, error(xhr) { $('#returns_error').text(xhr.responseJSON?.message || 'Could not forward the returns.').prop('hidden', false); }, complete() { button.prop('disabled', false); }
            });
        });
        $('#search_btn').on('click', loadData); loadData();
    }
    return { init, matchesStatus, matchesLevel, matchesRow, totals, drafts, statusHtml, childrenHtml };
})();
