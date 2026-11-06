<?php

namespace Tests\Unit;

use App\Services\InvoiceBillingService;
use Tests\TestCase;

class InvoiceBillingServiceTest extends TestCase
{
    public function test_commissionable_rent_on_a_normal_unit_has_commission_and_no_vat(): void
    {
        $a = InvoiceBillingService::computeAmounts(30000, true, true, false, 10);

        $this->assertEquals(0.0, $a['vat_amount']);
        $this->assertEquals(30000.0, $a['commissionable_amount']);
        $this->assertEquals(3000.0, $a['commission_amount']);
        $this->assertEquals(27000.0, $a['payable_amount']);
        $this->assertEquals(480.0, $a['vat_on_commission']);
    }

    public function test_rent_on_a_vatable_unit_has_inclusive_vat_taken_out_before_commission(): void
    {
        $a = InvoiceBillingService::computeAmounts(30000, true, true, true, 10);

        $this->assertEquals(4137.93, $a['vat_amount']);
        $this->assertEquals(25862.07, $a['commissionable_amount']);
        $this->assertEquals(2586.21, $a['commission_amount']);
        $this->assertEquals(27413.79, $a['payable_amount']);
        $this->assertEquals(413.79, $a['vat_on_commission']);
    }

    public function test_inclusive_vat_and_the_rent_after_vat_add_back_to_the_rent(): void
    {
        $a = InvoiceBillingService::computeAmounts(30000, true, true, true, 10);

        $this->assertEquals(30000.0, round($a['vat_amount'] + $a['commissionable_amount'], 2));
    }

    public function test_a_deposit_on_a_vatable_unit_carries_no_vat_and_no_commission(): void
    {
        $a = InvoiceBillingService::computeAmounts(30000, false, false, true, 10);

        $this->assertEquals(0.0, $a['vat_amount']);
        $this->assertEquals(0.0, $a['commissionable_amount']);
        $this->assertEquals(0.0, $a['commission_amount']);
        $this->assertEquals(30000.0, $a['payable_amount']);
        $this->assertEquals(0.0, $a['vat_on_commission']);
    }

    public function test_a_service_charge_on_a_vatable_unit_carries_no_vat(): void
    {
        $a = InvoiceBillingService::computeAmounts(5000, false, false, true, 10);

        $this->assertEquals(0.0, $a['vat_amount']);
        $this->assertEquals(5000.0, $a['payable_amount']);
    }

    public function test_rent_that_is_not_commissionable_still_carries_its_vat(): void
    {
        $a = InvoiceBillingService::computeAmounts(30000, true, false, true, 10);

        $this->assertEquals(4137.93, $a['vat_amount']);
        $this->assertEquals(0.0, $a['commissionable_amount']);
        $this->assertEquals(0.0, $a['commission_amount']);
        $this->assertEquals(30000.0, $a['payable_amount']);
    }

    public function test_a_commissionable_non_rent_item_is_commissioned_on_the_full_amount(): void
    {
        $a = InvoiceBillingService::computeAmounts(10000, false, true, true, 10);

        $this->assertEquals(0.0, $a['vat_amount']);
        $this->assertEquals(10000.0, $a['commissionable_amount']);
        $this->assertEquals(1000.0, $a['commission_amount']);
    }

    public function test_a_zero_percent_agreement_has_no_commission(): void
    {
        $a = InvoiceBillingService::computeAmounts(30000, true, true, false, 0);

        $this->assertEquals(0.0, $a['commission_amount']);
        $this->assertEquals(30000.0, $a['payable_amount']);
    }

    public function test_amounts_are_rounded_to_cents(): void
    {
        $a = InvoiceBillingService::computeAmounts(12345.67, true, true, false, 7.5);

        $this->assertEquals(925.93, $a['commission_amount']);
        $this->assertEquals(11419.74, $a['payable_amount']);
        $this->assertEquals(148.15, $a['vat_on_commission']);
    }

    public function test_item_match_condition_covers_new_and_legacy_invoices(): void
    {
        $sql = InvoiceBillingService::sameItemCondition('existing', 'src.item_id', 'src.item_name');

        $this->assertStringContainsString('existing.item_type_id = src.item_id', $sql);
        $this->assertStringContainsString('existing.item_type_id IS NULL', $sql);
        $this->assertStringContainsString('existing.return_type_id = src.item_name', $sql);
        $this->assertStringContainsString("existing.narration ILIKE (src.item_name || ' FOR %')", $sql);
    }

    public function test_item_match_condition_takes_a_literal_id_and_a_quoted_name(): void
    {
        $sql = InvoiceBillingService::sameItemCondition('sys_pm_debtors_invoices', '24', "'RENT'");

        $this->assertStringContainsString('sys_pm_debtors_invoices.item_type_id = 24', $sql);
        $this->assertStringContainsString("sys_pm_debtors_invoices.return_type_id = 'RENT'", $sql);
    }

    public function test_returns_period_is_normalised_to_the_first_of_the_month(): void
    {
        $this->assertSame('2026-10-01', InvoiceBillingService::firstOfMonth('2026-10-27'));
        $this->assertSame('2026-02-01', InvoiceBillingService::firstOfMonth('2026-02-01'));
        $this->assertSame('2027-01-01', InvoiceBillingService::firstOfMonth('2027-01-31'));
    }

    public function test_rent_journal_credits_the_landlord_net_and_the_commission(): void
    {
        $lines = InvoiceBillingService::invoiceJournalLines(30000, 2586.21, 133, 130, 140);

        $this->assertSame([
            ['account_id' => 133, 'type' => 'Debit', 'amount' => 30000.0],
            ['account_id' => 130, 'type' => 'Credit', 'amount' => 27413.79],
            ['account_id' => 140, 'type' => 'Credit', 'amount' => 2586.21],
        ], $lines);
    }

    public function test_a_deposit_journal_has_two_lines_and_no_commission_line(): void
    {
        $lines = InvoiceBillingService::invoiceJournalLines(30000, 0, 133, 130, 140);

        $this->assertCount(2, $lines);
        $this->assertSame(130, $lines[1]['account_id']);
        $this->assertSame(30000.0, $lines[1]['amount']);
    }

    public function test_a_company_held_deposit_is_credited_to_deposits_payable(): void
    {
        $lines = InvoiceBillingService::invoiceJournalLines(30000, 0, 133, 130, 140, 149);

        $this->assertSame(149, $lines[1]['account_id']);
    }

    public function test_every_invoice_journal_balances(): void
    {
        foreach ([[30000, 2586.21], [12345.67, 925.93], [100, 0], [99999.99, 8999.99]] as [$total, $commission]) {
            $lines = InvoiceBillingService::invoiceJournalLines($total, $commission, 1, 2, 3);
            $debits = array_sum(array_map(fn ($l) => $l['type'] === 'Debit' ? $l['amount'] : 0, $lines));
            $credits = array_sum(array_map(fn ($l) => $l['type'] === 'Credit' ? $l['amount'] : 0, $lines));

            $this->assertEquals(round($debits, 2), round($credits, 2));
        }
    }

    public function test_item_name_comes_from_the_item_then_return_type_then_the_narration(): void
    {
        $this->assertSame('RENT', InvoiceBillingService::itemName((object) ['item_name' => 'Rent', 'return_type_id' => 'Water Bill', 'narration' => 'x']));
        $this->assertSame('WATER BILL', InvoiceBillingService::itemName((object) ['item_name' => null, 'return_type_id' => 'Water Bill', 'narration' => 'x']));
        $this->assertSame('SERVICE CHARGE', InvoiceBillingService::itemName((object) ['item_name' => null, 'return_type_id' => '', 'narration' => 'Service Charge FOR August 2026']));
    }

    public function test_only_rent_is_rent_so_every_bill_is_matched_before_it(): void
    {
        $this->assertTrue(InvoiceBillingService::isRentInvoice((object) ['narration' => 'RENT FOR July 2026']));
        $this->assertTrue(InvoiceBillingService::isRentInvoice((object) ['return_type_id' => 'RENT']));
        $this->assertFalse(InvoiceBillingService::isRentInvoice((object) ['narration' => 'Water Bill FOR July 2026']));
        $this->assertFalse(InvoiceBillingService::isRentInvoice((object) ['return_type_id' => 'House Deposit']));
        $this->assertFalse(InvoiceBillingService::isRentInvoice((object) []));
    }
}
