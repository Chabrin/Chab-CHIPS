{{-- Search and type filter above a table on the Monthly Payroll Items page. $table is "items" or "recurring". --}}
<div class="pm-toolbar" data-table="{{ $table }}">
    <div class="pm-search">
        <span class="material-symbols-outlined">search</span>
        <input type="search" class="form-control form-control-sm pm-search-input" placeholder="{{ $placeholder }}" aria-label="{{ $placeholder }}" autocomplete="off">
    </div>
    <div class="pm-chips" role="group" aria-label="Filter by type">
        <button type="button" class="pm-chip is-active" data-type="" aria-pressed="true">All <span class="pm-chip-count">0</span></button>
        <button type="button" class="pm-chip" data-type="allowance" aria-pressed="false">Earnings <span class="pm-chip-count">0</span></button>
        <button type="button" class="pm-chip" data-type="deduction" aria-pressed="false">Deductions <span class="pm-chip-count">0</span></button>
    </div>
    <span class="pm-shown ms-auto" aria-live="polite"></span>
</div>
