@page {
    margin: 30px 30px 36px 30px;
}

@include('pdf.partials.statement-styles')

/* White rows with a complete grid and the statement's blue headers. */
.card {
    background-color: #fff;
    border: 1px solid #666;
    border-left: 3px solid #12b5c0;
}
.txn {
    border-collapse: collapse;
    border-spacing: 0;
}
.txn thead th,
.txn td,
.txn tr.alt td,
.txn tr.totals td,
.txn tr.final td {
    background-color: #fff;
    color: #111;
    border: 1px solid #666;
}
.txn thead th { background-color: #0b1b63; color: #fff; border-color: #8795bc; }
.txn tr.final td { background-color: #0b1b63; color: #fff; }
.txn tr.totals td { border-top: 1.5px solid #0b1b63; }
.summary td { background-color: #fff; border: 1px solid #666; }
.statement-summary .summary td { padding-left: 5px; padding-right: 5px; }

/* One sign-off block in normal document flow, after the financial summary. */
.signoff-footer {
    margin-top: 12px;
    border-top: 1px solid #dfe5ef;
    padding-top: 8px;
    page-break-inside: avoid;
    break-inside: avoid;
}
.statement-closing { page-break-inside: avoid; break-inside: avoid; }
.statement-summary { margin-top: 18px; page-break-inside: avoid; }
.statement-summary .card { padding: 8px 10px; }
.statement-summary .summary td { padding: 1.5px 5px; font-size: 7.5pt; }
.statement-summary .summary .closing td { padding-top: 5px; }
.txn thead { display: table-header-group; }
.txn .remarks { white-space: pre-wrap; word-wrap: break-word; }

.signoff {
    width: 100%;
    table-layout: fixed;
}

.signoff > tbody > tr > td {
    vertical-align: top;
    padding-right: 10px;
}

.signoff-title {
    font-size: 6.5pt;
    font-weight: bold;
    letter-spacing: 1.2px;
    color: #12b5c0;
    margin-bottom: 4px;
}

.signoff-line {
    width: 100%;
}

.signoff-line td {
    padding: 2px 0 0 0;
    font-size: 7pt;
    height: 13px;
}

.signoff-line .k {
    width: 30px;
    padding-right: 6px;
    color: #5b6475;
}

.signoff-line .v {
    border-bottom: 1px solid #9aa6bd;
    color: #1c2433;
}

.txn tr.totals td { font-size: 6pt; padding: 5px 4px; }
