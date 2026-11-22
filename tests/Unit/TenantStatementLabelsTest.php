<?php

namespace Tests\Unit;

use App\Services\TenantStatementService;
use PHPUnit\Framework\TestCase;

class TenantStatementLabelsTest extends TestCase
{
    public function test_an_invoice_line_starts_with_invoice_and_shows_its_invoice_number(): void
    {
        $this->assertSame('INVOICE - RENT FOR August 2026', TenantStatementService::narrationOf(true, 'RENT FOR August 2026', 'INV202681048E030', null));
        $this->assertSame('INV202681048E030', TenantStatementService::referenceOf(true, 'INV202681048E030', null, null));
    }

    public function test_a_payment_line_starts_with_payment_and_shows_its_own_reference_not_the_invoice(): void
    {
        $this->assertSame('PAYMENT - RENT FOR August 2026', TenantStatementService::narrationOf(false, 'RENT FOR August 2026', 'INV202681048E030', 'CA'));
        $this->assertSame('PAYMENT - RENT FOR August 2026', TenantStatementService::narrationOf(false, 'RENT FOR August 2026', 'INV202681048E030', null));
        $this->assertSame('RCT-2026-00012', TenantStatementService::referenceOf(false, 'INV202681048E030', null, 'RCT-2026-00012'));
        $this->assertSame('615218253612', TenantStatementService::referenceOf(false, 'INV1', 77, '615218253612'));
        $this->assertSame('77', (string) TenantStatementService::referenceOf(false, 'INV1', 77, null));
    }

    public function test_a_payment_with_no_reference_falls_back_to_the_invoice_it_paid_and_never_to_nothing(): void
    {
        $this->assertSame('INV1', TenantStatementService::referenceOf(false, 'INV1', null, null));
        $this->assertSame('-', TenantStatementService::referenceOf(false, null, null, null));
    }

    public function test_paid_owner_and_recovery_credits_say_so(): void
    {
        $this->assertSame('PAID TO OWNER - RENT FOR December 2026', TenantStatementService::narrationOf(false, 'RENT FOR December 2026', 'INV9', 'PO'));
        $this->assertSame('RECOVERY - RENT FOR December 2026', TenantStatementService::narrationOf(false, 'RENT FOR December 2026', 'INV9', 'rec'));
    }

    public function test_a_debit_that_is_not_an_invoice_keeps_its_own_wording_and_nothing_is_stamped_twice(): void
    {
        $this->assertSame('Deposit refund to tenant', TenantStatementService::narrationOf(true, 'Deposit refund to tenant', null, null));
        $this->assertSame('Invoice', TenantStatementService::narrationOf(true, '', null, null));
        $this->assertSame('PAYMENT - RENT', TenantStatementService::narrationOf(false, 'PAYMENT - RENT', 'INV1', 'CA'));
        $this->assertSame('PAYMENT', TenantStatementService::narrationOf(false, '', null, null));
    }
}
