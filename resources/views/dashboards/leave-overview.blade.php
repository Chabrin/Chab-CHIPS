<style>
    .leave-overview { background:#fff; border:1px solid #dce7e0; border-radius:16px; padding:24px; margin:24px 0; box-shadow:0 4px 18px rgba(20,58,41,.04); }
    .leave-overview h5 { font-size:21px; color:#214c3b; font-weight:750; margin:0 0 6px; text-transform:none; }
    .leave-overview .lo-intro { color:#667b70; font-size:13px; letter-spacing:0; }
    .leave-overview .lo-controls { display:flex; flex-wrap:wrap; gap:16px; background:#f5f9f6; padding:18px; border-radius:12px; margin:18px 0; }
    .leave-overview .lo-field { flex:1 1 180px; min-width:0; }
    .leave-overview .lo-field:first-child { flex:2 1 260px; }
    .leave-overview label { display:block; font-size:12px; font-weight:700; color:#365747; margin-bottom:6px; }
    .leave-overview .lo-table { width:100%; font-size:13px; letter-spacing:0; }
    .leave-overview .lo-table th { background:#f1f6f3; color:#365747; white-space:nowrap; padding:12px; }
    .leave-overview .lo-table td { padding:12px; border-bottom:1px solid #e8eeea; vertical-align:top; }
    .leave-overview .lo-number { text-align:right; font-variant-numeric:tabular-nums; }
    .leave-overview h6 { font-size:15px; font-weight:700; margin:24px 0 10px; color:#294b3a; }
    .leave-overview .lo-note { color:#667b70; font-size:12px; letter-spacing:0; margin-top:8px; }
    .leave-overview .lo-available { font-weight:750; color:#226345; }
</style>
<section class="leave-overview" aria-labelledby="lo-title">
    <h5 id="lo-title">Leave overview</h5>
    <p class="lo-intro">Review an employee’s leave account by category, then inspect the individual days behind it.</p>
    <div class="lo-controls">
        <div class="lo-field"><label for="lo-employee">Employee</label><select id="lo-employee" class="form-select"><option value="">Loading employees…</option></select></div>
        <div class="lo-field"><label for="lo-year">Year</label><input id="lo-year" class="form-control" type="number" min="2000" max="2100" value="{{ now()->year }}"></div>
        <div class="lo-field"><label for="lo-category">Leave category</label><select id="lo-category" class="form-select"><option value="">All categories</option></select></div>
        <div class="lo-field"><label for="lo-month">Daily breakdown month</label><select id="lo-month" class="form-select"><option value="">All months</option>@foreach(range(1,12) as $month)<option value="{{ sprintf('%02d', $month) }}">{{ \Carbon\Carbon::create(2000,$month,1)->format('F') }}</option>@endforeach</select></div>
    </div>
    <div id="lo-status" class="lo-intro" role="status" aria-live="polite">Select an employee to load their leave account.</div>
    <details id="lo-warnings" class="alert alert-warning mt-3 d-none"><summary>Some figures need HR review</summary><ul id="lo-warning-list" class="mt-2 mb-0"></ul></details>
    <div id="lo-results" class="d-none">
        <h6>Category balances · days</h6>
        <div class="table-responsive"><table class="lo-table"><thead><tr><th>Category</th><th class="lo-number">Entitlement</th><th class="lo-number">Approved allocated</th><th class="lo-number">Pending reserved</th><th class="lo-number">Recorded balance</th><th class="lo-number">Available to request</th><th>Review</th></tr></thead><tbody id="lo-balances"></tbody></table></div>
        <p class="lo-note">Configured categories use the same balance source as leave applications. Available days deduct pending reservations and have a minimum of zero. Missing or inactive categories keep their own recorded requests; their entitlement and balances appear as — until HR confirms the setup. Approved allocations include future approved leave.</p>
        <h6>Daily breakdown</h6>
        <div class="table-responsive" style="max-height:420px;overflow:auto;"><table class="lo-table"><thead><tr><th>Date</th><th>Category</th><th>Request</th><th>Status</th><th class="lo-number">Days</th><th>Pay tier / rate</th></tr></thead><tbody id="lo-days"></tbody></table></div>
        <p class="lo-note">Approved rows use saved day fractions, including half-days. Refunded dates charge zero. Pending rows preview current rules only for configured categories. Where category rules are unavailable, recorded request totals are labelled separately; a multi-day total is not a daily deduction. Month filters also show overlapping request periods without assigning their total to that month.</p>
    </div>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const employee = document.getElementById('lo-employee');
    const year = document.getElementById('lo-year');
    const category = document.getElementById('lo-category');
    const month = document.getElementById('lo-month');
    const status = document.getElementById('lo-status');
    const results = document.getElementById('lo-results');
    const endpoint = @json(route('dashboard.leave-overview'));
    let data = null, revision = 0;
    const number = value => value === null || value === undefined ? '—' : Number(value).toLocaleString(undefined, { maximumFractionDigits: 2 });
    function row(target, values, numeric = []) {
        const tr = document.createElement('tr');
        values.forEach((value, index) => {
            const cell = document.createElement('td');
            cell.textContent = value;
            if (numeric.includes(index)) cell.classList.add('lo-number');
            if (target === 'lo-balances' && index === 5) cell.classList.add('lo-available');
            tr.appendChild(cell);
        });
        document.getElementById(target).appendChild(tr);
    }
    function render() {
        if (!data) return;
        document.getElementById('lo-balances').replaceChildren();
        document.getElementById('lo-days').replaceChildren();
        const categories = data.categories.filter(item => !category.value || String(item.id) === category.value);
        categories.forEach(item => row('lo-balances', [item.name, number(item.entitled), number(item.approved), number(item.pending), number(item.balance), number(item.available), item.setup_status === 'missing' ? 'Category missing' : (item.setup_status === 'inactive' ? 'Category inactive' : (item.warnings.length ? 'Needs review' : 'Reconciled'))], [1,2,3,4,5]));
        if (!categories.length) row('lo-balances', ['No leave categories or recorded requests found.']);
        const selectedMonth = year.value + '-' + month.value;
        const days = data.days.filter(item => (!category.value || String(item.category_id) === category.value) && (!month.value || (item.date
            ? item.date.slice(0,7) === selectedMonth
            : item.request_start && item.request_end && item.request_start.slice(0,7) <= selectedMonth && item.request_end.slice(0,7) >= selectedMonth)));
        days.forEach(item => {
            const period = item.date || (item.request_start && item.request_end ? item.request_start + ' to ' + item.request_end + ' (request period)' : 'Dates unavailable');
            row('lo-days', [period, item.category, '#' + item.request_id, item.status, number(item.days), item.tier ? 'Tier ' + item.tier + (item.pay_rate !== null ? ' · ' + number(item.pay_rate) + '%' : '') : '—'], [4]);
        });
        if (!days.length) row('lo-days', ['No leave days match these filters.']);
        const messages = categories.flatMap(item => item.warnings.map(message => item.name + ': ' + message));
        const list = document.getElementById('lo-warning-list');
        list.replaceChildren();
        messages.forEach(message => { const li = document.createElement('li'); li.textContent = message; list.appendChild(li); });
        document.getElementById('lo-warnings').classList.toggle('d-none', !messages.length);
        document.getElementById('lo-warnings').open = messages.length > 0;
    }
    async function load() {
        const current = ++revision;
        results.classList.add('d-none');
        document.getElementById('lo-warnings').classList.add('d-none');
        data = null;
        if (!employee.value) { status.textContent = 'Select an employee to load their leave account.'; return; }
        if (!year.checkValidity() || !year.value) { status.textContent = 'Enter a year between 2000 and 2100.'; return; }
        status.textContent = 'Loading leave account…';
        try {
            const response = await fetch(endpoint + '?' + new URLSearchParams({staff_id: employee.value, year: year.value}), {headers: {'Accept':'application/json'}});
            if (current !== revision) return;
            if (!response.ok) throw new Error('Unable to load this employee’s leave account. Check access and try again.');
            const loaded = await response.json();
            if (current !== revision) return;
            data = loaded;
            category.replaceChildren(new Option('All categories', ''));
            data.categories.forEach(item => category.add(new Option(item.name, item.id)));
            status.textContent = employee.options[employee.selectedIndex].text + ' · ' + year.value;
            render(); results.classList.remove('d-none');
        } catch (error) { if (current === revision) status.textContent = error.message; }
    }
    fetch(endpoint, {headers: {'Accept':'application/json'}})
        .then(response => { if (!response.ok) throw new Error(); return response.json(); })
        .then(payload => {
            employee.replaceChildren(new Option('Select an employee', ''));
            payload.employees.forEach(item => employee.add(new Option((item.number || '—') + ' · ' + item.name, item.id)));
            if (window.jQuery && jQuery.fn.select2) jQuery(employee).select2({width:'100%', placeholder:'Select an employee'}).on('change', load);
            else employee.addEventListener('change', load);
            if (!payload.employees.length) status.textContent = 'No active or on-leave employees were found.';
        })
        .catch(() => { employee.replaceChildren(new Option('Unable to load employees', '')); status.textContent = 'Unable to load employees. Refresh to try again.'; });
    year.addEventListener('change', load);
    category.addEventListener('change', render);
    month.addEventListener('change', render);
});
</script>
