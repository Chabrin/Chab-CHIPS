{{-- Shared look for the approval queue pages (Approve SLA, Approve Leases). --}}
<style>
    .sla-page, .sla-modal, .small-modal, .sla-bulk {
        --ink: #0f172a; --ink-2: #334155; --muted: #64748b; --faint: #94a3b8;
        --line: #e7ebf1; --line-2: #f1f4f8; --canvas: #f6f8fb; --brand: #3598db; --brand-ink: #1d6fa5; --brand-soft: #eaf4fc;
        --ok: #12a150; --ok-soft: #e4f6ec; --warn: #b7791f; --warn-soft: #fdf3dc; --bad: #d92d20; --bad-soft: #fde8e6;
        font-family: inherit; color: var(--ink);
    }
    .sla-page * { box-sizing: border-box; }
    .sla-page :is(input, select, button, textarea), .sla-modal :is(input, select, button, textarea), .small-modal :is(input, select, button, textarea), .sla-bulk button { font-family: inherit; }

    /* header + tabs */
    .sla-eyebrow { font-size: 11px; font-weight: 500; letter-spacing: .12em; text-transform: uppercase; color: var(--brand-ink); }
    .sla-title { font-size: 26px; font-weight: 500; letter-spacing: -.02em; margin: 2px 0 2px; color: var(--ink); }
    .sla-sub { color: var(--muted); font-size: 14px; margin: 0; }
    .sla-tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--line); margin: 22px 0 0; overflow-x: auto; }
    .sla-tab { appearance: none; background: none; border: 0; padding: 12px 16px; font-size: 14px; font-weight: 500; color: var(--muted); cursor: pointer; position: relative; white-space: nowrap; display: inline-flex; align-items: center; gap: 8px; }
    .sla-tab:hover { color: var(--ink); }
    .sla-tab .n { font-size: 12px; font-weight: 500; padding: 1px 8px; border-radius: 999px; background: var(--line-2); color: var(--ink-2); }
    .sla-tab.active { color: var(--ink); }
    .sla-tab.active::after { content: ""; position: absolute; left: 12px; right: 12px; bottom: -1px; height: 2px; background: var(--brand); border-radius: 2px; }
    .sla-tab.active .n { background: var(--brand-soft); color: var(--brand-ink); }

    /* card + filters */
    .sla-card { background: #fff; border: 1px solid var(--line); border-radius: 16px; box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 8px 24px -12px rgba(15,23,42,.08); margin-top: 18px; overflow: hidden; }
    .sla-filters { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; padding: 16px 18px; border-bottom: 1px solid var(--line-2); }
    .sla-field { position: relative; }
    .sla-field .material-symbols-outlined { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 19px; color: var(--faint); pointer-events: none; }
    .sla-input, .sla-select { height: 40px; border: 1px solid var(--line); border-radius: 10px; background: #fff; padding: 0 12px; font-size: 13.5px; color: var(--ink); outline: none; transition: border-color .15s, box-shadow .15s; }
    .sla-input:focus, .sla-select:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(53,152,219,.15); }
    .sla-field .sla-input { padding-left: 38px; width: 300px; max-width: 100%; }
    .sla-select { padding-right: 30px; min-width: 150px; }
    .sla-range { display: inline-flex; align-items: center; border: 1px solid var(--line); border-radius: 10px; height: 40px; padding: 0 8px; gap: 4px; background: #fff; }
    .sla-range input { border: 0; outline: 0; font-size: 13px; color: var(--ink-2); background: transparent; width: 122px; }
    .sla-range span { color: var(--faint); font-size: 12px; }
    .sla-reset { margin-left: auto; background: none; border: 0; color: var(--muted); font-size: 13px; font-weight: 500; display: inline-flex; align-items: center; gap: 4px; padding: 8px 10px; border-radius: 8px; }
    .sla-reset:hover { background: var(--line-2); color: var(--ink); }
    .sla-reset .material-symbols-outlined { font-size: 18px; }

    /* table */
    .sla-table-wrap { padding: 0 6px 6px; }
    table#sla-table { width: 100% !important; border-collapse: separate; border-spacing: 0; }
    table#sla-table thead th { font-size: 11px; font-weight: 500; letter-spacing: .08em; text-transform: uppercase; color: var(--faint); padding: 14px 14px; border-bottom: 1px solid var(--line-2); background: #fff; white-space: nowrap; }
    table#sla-table tbody td { padding: 14px 14px; border-bottom: 1px solid var(--line-2); vertical-align: middle; background: #fff; transition: background .12s; }
    table#sla-table tbody tr { cursor: pointer; }
    table#sla-table tbody tr:hover td { background: #f9fbfe; }
    table#sla-table tbody tr:last-child td { border-bottom: 0; }
    table#sla-table td.dt-empty { padding: 56px 0; text-align: center; color: var(--muted); }
    .t-main { font-weight: 400; color: var(--ink); font-size: 14px; line-height: 1.3; }
    .t-ref { font-weight: 700; color: var(--ink); font-size: 14px; line-height: 1.3; }
    .chip.ref { font-weight: 700; color: var(--ink); }
    .t-sub { font-size: 12.5px; color: var(--muted); line-height: 1.4; margin-top: 1px; }
    .who { display: flex; align-items: center; gap: 12px; }
    .avatar { width: 38px; height: 38px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-weight: 500; font-size: 13px; letter-spacing: .02em; flex: 0 0 auto; }
    .avatar.lg { width: 60px; height: 60px; border-radius: 18px; font-size: 20px; }
    .chip { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 500; padding: 2px 9px; border-radius: 999px; background: var(--line-2); color: var(--ink-2); white-space: nowrap; }
    .chip.zone { background: var(--brand-soft); color: var(--brand-ink); }
    .rate { font-weight: 500; font-size: 15px; color: var(--ink); }
    .status { display: inline-flex; align-items: center; gap: 7px; font-size: 12.5px; font-weight: 500; padding: 4px 11px 4px 9px; border-radius: 999px; }
    .status::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
    .status.pending { background: var(--warn-soft); color: var(--warn); }
    .status.approved { background: var(--ok-soft); color: var(--ok); }
    .status.rejected { background: var(--bad-soft); color: var(--bad); }
    .status.other { background: var(--line-2); color: var(--muted); }
    .row-actions { display: inline-flex; gap: 6px; }
    .icon-btn { width: 34px; height: 34px; border-radius: 10px; border: 1px solid var(--line); background: #fff; color: var(--ink-2); display: inline-flex; align-items: center; justify-content: center; padding: 0; transition: all .12s; }
    .icon-btn .material-symbols-outlined { font-size: 19px; }
    .icon-btn:hover { background: var(--line-2); }
    .icon-btn.ok { color: var(--ok); border-color: #bfe6cf; background: var(--ok-soft); }
    .icon-btn.ok:hover { background: #d3f0e0; }
    .icon-btn.bad { color: var(--bad); border-color: #f7c6c1; background: var(--bad-soft); }
    .icon-btn.bad:hover { background: #fbd5d1; }
    .form-check-input { cursor: pointer; width: 17px; height: 17px; border-radius: 5px; border-color: #cbd5e1; margin: 0; }
    .form-check-input:checked { background-color: var(--brand); border-color: var(--brand); }

    table#sla-table th.dt-type-numeric, table#sla-table td.dt-type-numeric { text-align: left !important; }
    table#sla-table th.dt-type-numeric div.dt-column-header { flex-direction: row !important; justify-content: flex-start; gap: 6px; }

    /* datatables chrome */
    .sla-page .dt-container .dt-layout-row { padding: 12px 14px; margin: 0; font-size: 13px; color: var(--muted); align-items: center; }
    .sla-page .dt-container .dt-length select { border: 1px solid var(--line); border-radius: 8px; padding: 4px 8px; margin-right: 6px; }
    .sla-page .dt-paging .dt-paging-button { border: 0 !important; border-radius: 8px !important; margin: 0 2px; padding: 5px 11px !important; font-weight: 500; color: var(--ink-2) !important; background: transparent !important; }
    .sla-page .dt-paging .dt-paging-button:hover { background: var(--line-2) !important; }
    .sla-page .dt-paging .dt-paging-button.current { background: var(--brand) !important; color: #fff !important; }
    .sla-page .dt-paging .dt-paging-button.disabled { opacity: .4; }
    .sla-page div.dt-processing { background: rgba(255,255,255,.7); color: var(--muted); }

    /* floating bulk bar */
    .sla-bulk { position: fixed; left: 50%; bottom: 28px; transform: translate(-50%, 24px); opacity: 0; pointer-events: none; z-index: 1040; background: #0f172a; color: #fff; border-radius: 16px; padding: 10px 12px 10px 20px; display: flex; align-items: center; gap: 14px; box-shadow: 0 20px 40px -12px rgba(15,23,42,.45); transition: all .2s ease; }
    .sla-bulk.show { transform: translate(-50%, 0); opacity: 1; pointer-events: auto; }
    .sla-bulk .count { font-weight: 500; font-size: 14px; }
    .sla-bulk .count small { color: #94a3b8; font-weight: 500; }
    .bulk-btn { border: 0; border-radius: 10px; padding: 8px 14px; font-weight: 500; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; color: #fff; }
    .bulk-btn .material-symbols-outlined { font-size: 18px; }
    .bulk-btn.ok { background: var(--ok); }
    .bulk-btn.bad { background: transparent; color: #fda4a0; border: 1px solid rgba(253,164,160,.35); }
    .bulk-btn.ghost { background: transparent; color: #94a3b8; padding: 8px; }
    .bulk-btn:disabled { opacity: .6; }

    /* review modal */
    .sla-modal .modal-content { border: 0; border-radius: 20px; overflow: hidden; box-shadow: 0 30px 80px -20px rgba(15,23,42,.45); font-family: inherit; color: var(--ink); }
    .sla-hero { padding: 26px 30px 0; background: linear-gradient(180deg, #f1f7fd 0%, #ffffff 100%); position: relative; }
    .sla-hero .close-x { position: absolute; right: 18px; top: 18px; }
    .hero-row { display: flex; gap: 18px; align-items: center; }
    .hero-name { font-size: 22px; font-weight: 500; letter-spacing: -.01em; line-height: 1.2; }
    .hero-sub { color: var(--muted); font-size: 14px; margin-top: 3px; }
    .hero-meta { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; align-items: center; }
    .kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-top: 22px; }
    @media (max-width: 767px) { .kpis { grid-template-columns: repeat(2, 1fr); } }
    .kpi { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 12px 16px; }
    .kpi .k { font-size: 11px; font-weight: 500; letter-spacing: .08em; text-transform: uppercase; color: var(--faint); }
    .kpi .v { font-size: 21px; font-weight: 500; margin-top: 2px; letter-spacing: -.01em; }
    .kpi .v small { font-size: 12px; font-weight: 500; color: var(--muted); letter-spacing: 0; }
    .kpi.good .v { color: var(--ok); } .kpi.warn .v { color: var(--warn); } .kpi.bad .v { color: var(--bad); }
    .m-tabs { display: flex; gap: 2px; margin-top: 18px; }
    .m-tab { appearance: none; background: none; border: 0; padding: 12px 16px; font-weight: 500; font-size: 14px; color: var(--muted); position: relative; cursor: pointer; }
    .m-tab:hover { color: var(--ink); }
    .m-tab.active { color: var(--ink); }
    .m-tab.active::after { content: ""; position: absolute; left: 10px; right: 10px; bottom: 0; height: 2px; background: var(--brand); border-radius: 2px; }
    .sla-modal .modal-body { padding: 24px 30px 28px; background: #fff; border-top: 1px solid var(--line); min-height: 320px; }
    .m-pane { display: none; } .m-pane.active { display: block; animation: fadeUp .18s ease; }
    @keyframes fadeUp { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }
    .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 28px; }
    @media (max-width: 991px) { .grid2 { grid-template-columns: 1fr; } }
    .sec-title { font-size: 11px; font-weight: 500; letter-spacing: .1em; text-transform: uppercase; color: var(--faint); margin: 0 0 12px; }
    .kv { display: grid; grid-template-columns: 140px 1fr; gap: 10px 14px; margin: 0; font-size: 14px; }
    .kv dt { color: var(--muted); font-weight: 500; }
    .kv dd { margin: 0; color: var(--ink); font-weight: 400; word-break: break-word; }
    .kv dd.none { color: var(--faint); font-weight: 400; }
    .checks { display: grid; gap: 6px; }
    .check { display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 12px; border: 1px solid var(--line); font-size: 13.5px; font-weight: 400; }
    .check .dot { width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto; }
    .check .dot .material-symbols-outlined { font-size: 16px; font-weight: 500; }
    .check.ok .dot { background: var(--ok-soft); color: var(--ok); }
    .check.bad { background: #fffafa; border-color: #f7d4d0; }
    .check.bad .dot { background: var(--bad-soft); color: var(--bad); }
    .banner { display: flex; gap: 10px; align-items: flex-start; padding: 12px 16px; border-radius: 12px; font-size: 14px; font-weight: 500; margin-bottom: 16px; }
    .banner.warn { background: var(--warn-soft); color: #7a4f0f; }
    .banner.ok { background: var(--ok-soft); color: #0b6b35; }
    .banner .material-symbols-outlined { font-size: 20px; }
    .bank-card { border: 1px solid var(--line); border-radius: 14px; padding: 14px 16px; display: flex; gap: 12px; align-items: center; margin-bottom: 10px; }
    .bank-card .ico { width: 38px; height: 38px; border-radius: 11px; background: var(--brand-soft); color: var(--brand-ink); display: inline-flex; align-items: center; justify-content: center; }
    .item-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .item { border: 1px solid var(--line); border-radius: 12px; padding: 12px 14px; display: flex; align-items: center; gap: 10px; font-weight: 400; font-size: 14px; }
    .item.off { color: var(--faint); background: #fafbfc; }
    .item .material-symbols-outlined { font-size: 20px; color: var(--ok); }
    .item.off .material-symbols-outlined { color: #cbd5e1; }
    .item .cost { margin-left: auto; font-weight: 500; color: var(--ink-2); }
    .units-tbl { border: 1px solid var(--line); border-radius: 14px; overflow: hidden; margin-top: 8px; }
    .units-tbl .scroll { max-height: 280px; overflow: auto; }
    .units-tbl table { width: 100%; font-size: 13.5px; border-collapse: collapse; }
    .units-tbl th { position: sticky; top: 0; background: #f8fafc; font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: var(--faint); padding: 10px 14px; text-align: left; }
    .units-tbl td { padding: 9px 14px; border-top: 1px solid var(--line-2); }
    .units-tbl td.r, .units-tbl th.r { text-align: right; }
    .sla-modal .modal-footer { border-top: 1px solid var(--line); padding: 16px 30px; background: #fbfcfe; justify-content: space-between; }
    .foot-note { color: var(--muted); font-size: 13px; max-width: 460px; }
    .btn-soft { border: 1px solid var(--line); background: #fff; color: var(--ink-2); font-weight: 500; border-radius: 11px; padding: 9px 18px; }
    .btn-soft:hover { background: var(--line-2); }
    .btn-bad { border: 1px solid #f3b8b2; background: #fff; color: var(--bad); font-weight: 500; border-radius: 11px; padding: 9px 18px; }
    .btn-bad:hover { background: var(--bad-soft); }
    .btn-ok { border: 0; background: var(--ok); color: #fff; font-weight: 500; border-radius: 11px; padding: 10px 22px; box-shadow: 0 6px 16px -6px rgba(18,161,80,.6); }
    .btn-ok:hover { background: #0f8f47; color: #fff; }
    @media (min-width: 992px) {
        .modal.sla-modal, .modal.small-modal { padding-left: var(--sidebar-width, 170px); padding-top: var(--header-height, 54px); }
    }
    .sla-empty { text-align: center; padding: 56px 0; color: var(--muted); }
    .small-modal .modal-content { border: 0; border-radius: 18px; box-shadow: 0 30px 80px -20px rgba(15,23,42,.45); }
</style>
