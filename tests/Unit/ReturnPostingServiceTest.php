<?php

namespace Tests\Unit;

use App\Services\ClientAdvanceService;
use App\Services\ReturnPostingService;
use Tests\TestCase;

class ReturnPostingServiceTest extends TestCase
{
    public function test_a_return_owes_the_client_the_credits_less_commission_and_vat_before_deductions(): void
    {
        // Rent 100,000, 10% commission (10,000), VAT on commission (1,600), fee 1,500 and advance 20,700 on the ledger
        $f = ReturnPostingService::settle(100000, 10000, 1600, 22200, 0);

        $this->assertSame(88400.0, $f['gross']);
        $this->assertSame(66200.0, $f['net']);
        $this->assertSame(88400.0, $f['ledger_credit']);
    }

    public function test_the_ledger_credit_plus_the_debits_already_on_it_less_the_payout_nets_to_nil(): void
    {
        $f = ReturnPostingService::settle(100000, 10000, 1600, 22200, 0);

        $this->assertSame(0.0, round(-22200 + $f['ledger_credit'] - $f['net'], 2));
    }

    public function test_money_recoveries_with_no_ledger_debit_come_off_the_credit_itself(): void
    {
        // 3,000 of tenant recoveries are deducted but have no debit row, so the credit is posted net of them
        $f = ReturnPostingService::settle(100000, 10000, 1600, 22200, 3000);

        $this->assertSame(63200.0, $f['net']);
        $this->assertSame(85400.0, $f['ledger_credit']);
        $this->assertSame(0.0, round(-22200 + $f['ledger_credit'] - $f['net'], 2));
    }

    public function test_a_return_with_nothing_payable_has_a_zero_credit_and_net(): void
    {
        $f = ReturnPostingService::settle(0, 0, 0, 0, 0);

        $this->assertSame(0.0, $f['gross']);
        $this->assertSame(0.0, $f['net']);
        $this->assertSame(0.0, $f['ledger_credit']);
    }

    public function test_an_instalment_row_splits_into_its_schedule_principal_and_the_rest(): void
    {
        $this->assertSame([5000.0, 232.26], ClientAdvanceService::splitRecovery(5232.26, 'ADV-2026-005/1', 5000.0, 15000.0));
    }

    public function test_the_charge_row_is_all_charge(): void
    {
        $this->assertSame([0.0, 100.0], ClientAdvanceService::splitRecovery(100.0, 'ADV-2026-005/CHG', null, 15000.0));
    }

    public function test_a_one_off_advance_row_holds_the_advance_principal_and_the_rest_is_interest_and_charge(): void
    {
        $this->assertSame([20000.0, 700.0], ClientAdvanceService::splitRecovery(20700.0, 'ADV-2026-009', null, 20000.0));
    }

    public function test_the_principal_is_never_more_than_the_row(): void
    {
        $this->assertSame([300.0, 0.0], ClientAdvanceService::splitRecovery(300.0, 'ADV-2026-009', null, 20000.0));
    }
}
