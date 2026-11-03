@page {
    margin: 30px 34px 128px 34px;
}

@include('pdf.partials.statement-styles')

/* ── Sign-off footer (every page) ───────── */
.signoff-footer {
    position: fixed;
    left: 0;
    right: 0;
    bottom: -90px;
    border-top: 1px solid #dfe5ef;
    padding-top: 6px;
}

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
