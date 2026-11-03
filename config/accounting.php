<?php

return [
    // Post a GL journal when a tenant invoice is raised (Dr Tenants / Cr Clients net / Cr Commission). Off until the
    // accountant has agreed the posting timing; invoices and ledger rows are written either way.
    'post_invoice_journals' => env('ACCOUNTING_POST_INVOICE_JOURNALS', false),

    // Post the VAT on commission to VAT Control when a return is approved (Dr Clients Account / Cr VAT Control). Off until
    // the accountant has agreed that this is where and when the VAT is recognised; the return's ledger rows are written either way.
    'post_return_vat_journals' => env('ACCOUNTING_POST_RETURN_VAT_JOURNALS', false),
];
