<?php

namespace Tests\Unit;

use App\Services\ClientLedgerService;
use PHPUnit\Framework\TestCase;

class ClientLedgerChargesTest extends TestCase
{
    private function row(string $code, string $description, float $debit, bool $counted = true, string $reference = '-'): array
    {
        return ['code' => $code, 'description' => $description, 'reference' => $reference, 'debit' => $debit, 'credit' => 0.0, 'counted' => $counted];
    }

    public function test_cheque_banking_and_advance_charges_are_the_companys_and_grouped_by_kind(): void
    {
        $charges = (new ClientLedgerService())->charges([
            $this->row('0014', 'CHEQUE CHARGES', 150),
            $this->row('0030', 'CHEQUE CHARGES', 100),
            $this->row('0019', 'BANKING SERVICES', 100),
            $this->row('0008', 'Advance Payments Charges', 100),
            $this->row('0008', '3% Interest on Advances', 900),
            $this->row('0009', 'ADV-2026-004', 100, true, 'ADV-2026-004/CHG'),
        ]);

        // Biggest first inside the group
        $this->assertSame(
            [
                ['label' => 'Interest on advances', 'count' => 1, 'amount' => 900.0],
                ['label' => 'Cheque charges', 'count' => 2, 'amount' => 250.0],
                ['label' => 'Advance charges', 'count' => 2, 'amount' => 200.0],
                ['label' => 'Banking services', 'count' => 1, 'amount' => 100.0],
            ],
            $charges['company']['rows']
        );
        $this->assertSame(1450.0, $charges['company']['total']);
        $this->assertSame([], $charges['behalf']['rows']);
    }

    public function test_salaries_security_and_bills_paid_for_the_landlord_are_listed_apart(): void
    {
        $charges = (new ClientLedgerService())->charges([
            $this->row('0015', 'CARETAKER SALARY CHARGE', 6000),
            $this->row('0033', 'WATCHMAN SALARY CHARGE', 4000),
            $this->row('0004', 'Water Bill Charge', 100),
            $this->row('0002', 'Electricity Bill Charge', 100),
            $this->row('0031', 'SECURITY PAYMENT CHARGES', 100),
        ]);

        $this->assertSame(10300.0, $charges['behalf']['total']);
        $this->assertCount(5, $charges['behalf']['rows']);
        $this->assertSame('Caretaker salary', $charges['behalf']['rows'][0]['label']);
        $this->assertSame(0.0, $charges['company']['total']);
    }

    public function test_advances_returns_refunds_and_recoveries_are_not_charges(): void
    {
        $charges = (new ClientLedgerService())->charges([
            $this->row('0009', 'ADVANCE PAYMENTS', 500000, true, 'ADV-2026-001'),
            $this->row('0020', 'LOAN', 100000),
            $this->row('0013', 'REFUND OF DEPOSITS', 20000),
            $this->row('0024', 'Rent recovery', 30000),
            $this->row('0005', 'RETURNS', 0),
        ]);

        $this->assertSame(0.0, $charges['company']['total'] + $charges['behalf']['total']);
    }

    public function test_entries_that_do_not_count_and_credits_are_left_out(): void
    {
        $charges = (new ClientLedgerService())->charges([
            $this->row('0014', 'CHEQUE CHARGES', 150, false),
            $this->row('0014', 'CHEQUE CHARGES', 0),
            $this->row('0014', 'CHEQUE CHARGES', 150),
        ]);

        $this->assertSame(150.0, $charges['company']['total']);
        $this->assertSame(1, $charges['company']['rows'][0]['count']);
    }
}
