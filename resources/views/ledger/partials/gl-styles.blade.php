{{-- Styles shared by the Account Balances and Account Ledger pages --}}
.gl-card { background: #fff; border: 1px solid #e3e8ef; border-radius: 8px; margin-bottom: 16px; overflow: hidden; }
.gl-card-head { display: flex; justify-content: space-between; align-items: center; padding: 10px 16px; background: #f6f8fb; border-bottom: 1px solid #e3e8ef; }
.gl-card-title { font-size: .72rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #5b6475; }
.gl-card-count { font-size: .75rem; color: #8a93a4; }
.gl-table { width: 100%; border-collapse: collapse; font-size: .84rem; }
.gl-table th { text-align: left; font-size: .72rem; font-weight: 600; color: #8a93a4; padding: 8px 16px; border-bottom: 1px solid #eef1f6; white-space: nowrap; }
.gl-table td { padding: 9px 16px; border-bottom: 1px solid #f1f4f8; vertical-align: middle; }
.gl-table th.r, .gl-table td.r { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
.gl-table td.code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .78rem; color: #8a93a4; white-space: nowrap; }
.gl-table tbody tr:hover td { background: #f7f9fc; }
.gl-system { margin-left: 6px; font-size: .7rem; color: #3598db; cursor: help; }
.gl-dr { color: #1a56a0; }
.gl-cr { color: #157347; }
.gl-neg { color: #a8232c; }
.gl-side { font-size: .7rem; color: #8a93a4; margin-left: 4px; }
.gl-abnormal { color: #b26a00; font-weight: 600; }
.gl-muted { color: #b0b7c3; }
.gl-link { font-size: .78rem; text-decoration: none; }
.gl-tb { background: #0b1b63; color: #fff; border-radius: 8px; padding: 14px 18px; display: flex; flex-wrap: wrap; gap: 18px; align-items: center; justify-content: space-between; }
.gl-tb-label { font-size: .68rem; letter-spacing: 1px; text-transform: uppercase; color: #12b5c0; font-weight: 700; }
.gl-tb-value { font-size: 1.15rem; font-weight: 700; font-variant-numeric: tabular-nums; }
.gl-tb-ok { color: #6ee7a8; font-weight: 600; }
.gl-tb-bad { color: #ffb4b4; font-weight: 600; }
.gl-tiles { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 18px; }
.gl-tile { background: #f3f6fb; border: 1px solid #dfe5ef; border-left: 3px solid #12b5c0; border-radius: 6px; padding: 10px 14px; }
.gl-tile-label { font-size: .68rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #12b5c0; }
.gl-tile-value { font-size: 1.15rem; font-weight: 700; color: #0b1b63; margin-top: 2px; font-variant-numeric: tabular-nums; }
.gl-tile-closing { background: #0b1b63; border-color: #0b1b63; border-left-color: #12b5c0; }
.gl-tile-closing .gl-tile-value { color: #fff; font-size: 1.35rem; }
.gl-tile-note { font-size: .72rem; color: #b9c3e6; margin-top: 1px; }
.gl-sheet { background: #fff; border: 1px solid #e3e8ef; border-radius: 8px; padding: 20px 22px; }
.gl-sheet-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
.gl-sheet-name { font-size: 1.2rem; font-weight: 700; color: #0b1b63; }
.gl-sheet-meta { margin-top: 4px; font-size: .82rem; color: #5b6475; }
.gl-sheet-period { font-size: .95rem; font-weight: 600; color: #12b5c0; white-space: nowrap; }
.gl-lines { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .84rem; }
.gl-lines thead th { position: sticky; top: var(--header-height, 54px); z-index: 3; background: #0b1b63; color: #fff; font-size: .7rem; letter-spacing: .6px; text-transform: uppercase; padding: 9px 10px; text-align: left; white-space: nowrap; }
.gl-lines th.r, .gl-lines td.r { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
.gl-lines td { padding: 8px 10px; border-bottom: 1px solid #e9edf4; vertical-align: top; }
.gl-lines td:first-child { white-space: nowrap; }
.gl-lines tbody tr:nth-child(even) td { background: #f8fafd; }
.gl-lines tbody tr:hover td { background: #eef4fb; }
.gl-lines tbody tr.bf td { background: #e8f7f8; color: #0b1b63; font-weight: 700; border-bottom-color: #b9e3e7; }
.gl-lines .ref { color: #5b6475; font-size: .78rem; white-space: nowrap; }
.gl-lines td.empty { text-align: center; color: #8a93a4; padding: 22px; }
.gl-lines tfoot td { font-weight: 700; padding: 9px 10px; border-bottom: 0; }
.gl-lines tfoot tr.totals td { background: #eef1f8; border-top: 2px solid #0b1b63; }
.gl-lines tfoot tr.final td { background: #0b1b63; color: #fff; }
.gl-note { margin-top: 12px; font-size: .75rem; color: #6b7486; line-height: 1.5; }
.gl-empty { text-align: center; color: #6b7a90; padding: 56px 12px; background: #fff; border: 1px dashed #d5dce8; border-radius: 8px; }
.gl-empty .material-symbols-outlined { font-size: 56px; color: #b8c2d3; }
.gl-area { position: relative; min-height: 160px; }
.gl-content.is-loading { opacity: .35; pointer-events: none; }
.gl-loading { position: absolute; inset: 0; display: none; align-items: flex-start; justify-content: center; padding-top: 60px; z-index: 5; }
.gl-filter { background: #f8fafc; border: 1px solid #e3e8ef; border-radius: 8px; padding: 14px 18px; margin-bottom: 16px; }
.gl-filter label { font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; color: #6b7a90; margin-bottom: 4px; }
@media (max-width: 991px) { .gl-tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 767px) { .gl-lines thead th { position: static; } }
