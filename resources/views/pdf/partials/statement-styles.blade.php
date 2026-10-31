{{-- Shared look of the statement-style PDFs: letterhead, cards, tables. Each template sets its own @page margin. --}}
* {
    box-sizing: border-box;
}

body {
    font-family: 'DejaVu Sans', Arial, sans-serif;
    font-size: 8pt;
    color: #1c2433;
    margin: 0;
    padding: 0;
}

table {
    border-collapse: collapse;
}

/* ── Letterhead ─────────────────────────── */
.head {
    width: 100%;
}

.head td {
    vertical-align: top;
    padding: 0;
}

.head .left {
    width: 55%;
}

.head .right {
    width: 45%;
    text-align: right;
}

.logo {
    height: 44px;
}

.company-line {
    margin-top: 5px;
    font-size: 7pt;
    line-height: 1.5;
    color: #5b6475;
}

.doc-title {
    font-size: 18pt;
    font-weight: bold;
    letter-spacing: 1.5px;
    color: #0b1b63;
}

.doc-period {
    margin-top: 4px;
    font-size: 9pt;
    font-weight: bold;
    color: #12b5c0;
}

.doc-meta {
    margin-top: 3px;
    font-size: 7pt;
    color: #5b6475;
}

.rule-navy {
    height: 3px;
    background-color: #0b1b63;
    margin-top: 12px;
}

.rule-teal {
    height: 1px;
    background-color: #12b5c0;
    margin-top: 2px;
    margin-bottom: 14px;
}

/* ── Landlord + summary cards ───────────── */
.cards {
    width: 100%;
    margin-bottom: 14px;
}

.cards td {
    vertical-align: top;
    padding: 0;
}

.cards .col-landlord {
    width: 52%;
    padding-right: 12px;
}

.cards .col-summary {
    width: 48%;
}

.card {
    background-color: #f3f6fb;
    border: 1px solid #dfe5ef;
    border-left: 3px solid #12b5c0;
    padding: 10px 12px;
}

.card-label {
    font-size: 6.5pt;
    font-weight: bold;
    letter-spacing: 1.2px;
    color: #12b5c0;
    margin-bottom: 5px;
}

.landlord-name {
    font-size: 12pt;
    font-weight: bold;
    color: #0b1b63;
    margin-bottom: 6px;
}

.kv {
    width: 100%;
}

.kv td {
    padding: 1.5px 0;
    font-size: 7.5pt;
}

.kv .k {
    width: 28%;
    color: #5b6475;
}

.summary {
    width: 100%;
}

.summary td {
    padding: 2.5px 0;
    font-size: 8pt;
}

.summary .k {
    color: #5b6475;
}

.summary .v {
    text-align: right;
    font-weight: bold;
    white-space: nowrap;
}

.summary tr.sub .k {
    padding-left: 10px;
}

.summary tr.sub .v {
    font-weight: normal;
}

.summary tr.strong .k {
    color: #1c2433;
    font-weight: bold;
}

.closing td {
    border-top: 1px solid #c9d2e3;
    padding-top: 7px;
}

.closing .k {
    font-weight: bold;
    color: #0b1b63;
}

.closing .v {
    font-size: 13pt;
    color: #0b1b63;
}

.closing-note {
    text-align: right;
    font-size: 6.5pt;
    color: #5b6475;
    padding-top: 0;
}

/* ── Section label ──────────────────────── */
.section {
    margin: 12px 0 5px 0;
    font-size: 6.5pt;
    font-weight: bold;
    letter-spacing: 1.2px;
    color: #12b5c0;
}

/* ── Tables ─────────────────────────────── */
.txn {
    width: 100%;
    table-layout: fixed;
    border-collapse: separate;
    border-spacing: 0;
}

.txn thead th {
    background-color: #0b1b63;
    color: #ffffff;
    font-size: 7pt;
    font-weight: bold;
    letter-spacing: 0.5px;
    padding: 6px 6px;
    text-align: left;
    border-right: 1px solid #0b1b63;
}

.txn thead th.r {
    text-align: right;
}

.txn td {
    padding: 5px 6px;
    border-bottom: 1px solid #e6ebf3;
    font-size: 7.5pt;
    vertical-align: top;
    word-wrap: break-word;
}

.txn td.r {
    text-align: right;
    white-space: nowrap;
}

.txn tr {
    page-break-inside: avoid;
}

.txn tr.alt td {
    background-color: #f8fafd;
    border-right: 1px solid #f8fafd;
}

.txn tr.totals td {
    background-color: #eef1f8;
    font-weight: bold;
    border-top: 1.5px solid #0b1b63;
    border-bottom: none;
    border-right: 1px solid #eef1f8;
}

.txn tr.final td {
    background-color: #0b1b63;
    color: #ffffff;
    font-weight: bold;
    border-bottom: none;
    border-right: 1px solid #0b1b63;
    padding: 7px 6px;
}

.ref {
    color: #5b6475;
    font-size: 7pt;
}

.dr {
    color: #a8232c;
}

.cr {
    color: #137a46;
}

.neg {
    color: #a8232c;
}

.muted {
    color: #8a93a4;
}

.empty {
    text-align: center;
    color: #8a93a4;
    padding: 18px 6px;
}

/* ── Page footer (text) ─────────────────── */
.footer {
    position: fixed;
    left: 0;
    right: 0;
    bottom: -38px;
    border-top: 1px solid #dfe5ef;
    padding-top: 5px;
    font-size: 6.5pt;
    color: #8a93a4;
}
