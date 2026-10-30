<?php

return [
    // Post a GL journal when a tenant invoice is raised (Dr Tenants / Cr Clients net / Cr Commission). Off until the
    // accountant has agreed the posting timing; invoices and ledger rows are written either way.
    'post_invoice_journals' => env('ACCOUNTING_POST_INVOICE_JOURNALS', false),
];
