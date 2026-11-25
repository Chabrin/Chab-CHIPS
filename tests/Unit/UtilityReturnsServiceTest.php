<?php

namespace Tests\Unit;

use App\Models\ReturnClient;
use App\Services\ReturnStatementService;
use App\Services\UtilityReturnsService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UtilityReturnsServiceTest extends TestCase
{
    /** Every item name the invoices carry, and which side of the return it is on (rent, arrears and deposits are rent). */
    public static function itemNames(): array
    {
        return [
            'rent' => ['RENT', false],
            'rent lower case' => ['rent', false],
            'rent arrears' => ['RENT ARREARS', false],
            'prepayment' => ['Rent Prepayment', false],
            'house deposit' => ['House Deposit', false],
            'water deposit stays rent' => ['Water Deposit', false],
            'electricity deposit stays rent' => ['Electricity Deposit', false],
            'old abbreviation of a deposit' => ['HODE', false],
            'old abbreviation of a water deposit' => ['WADE', false],
            'service charge' => ['Service Charge', true],
            'water bill' => ['Water Bill', true],
            'electricity bill' => ['Electricity Bill', true],
            'garbage fee' => ['Garbage Fee', true],
            'garbage fees' => ['Garbage Fees', true],
            'lease agreement fee' => ['Lease Agreement Fee', true],
            'bounced cheque charges' => ['Bounced Cheque Charges', true],
            'blank is nothing' => ['', false],
            'padded name' => ['  Water Bill ', true],
        ];
    }

    /** @dataProvider itemNames */
    public function test_an_item_is_a_utility_unless_it_is_rent_arrears_or_a_deposit(string $name, bool $expected): void
    {
        $this->assertSame($expected, UtilityReturnsService::isUtilityItemName($name));
    }

    /** @dataProvider itemNames */
    public function test_generation_and_the_statement_agree_on_what_a_utility_is(string $name, bool $expected): void
    {
        // The SQL rule generation uses
        $sql = UtilityReturnsService::utilityItemSql(DB::connection()->getPdo()->quote($name));
        $this->assertSame($expected, (bool) DB::selectOne("SELECT CASE WHEN {$sql} THEN 1 ELSE 0 END AS utility")->utility, "SQL rule for '{$name}'");

        // The rule the statement uses on a line carrying that name (a blank name is no line)
        if (trim($name) !== '') {
            $line = (new ReturnClient())->forceFill(['entry_type' => $name, 'entry_description' => $name, 'dr_cr' => 'CR', 'net_amount' => 10]);
            $this->assertSame($expected, app(ReturnStatementService::class)->isUtilityLine($line), "statement rule for '{$name}'");
        }
    }

    public function test_a_catch_all_line_is_named_by_its_description(): void
    {
        $service = app(ReturnStatementService::class);
        $line = fn(string $description) => (new ReturnClient())->forceFill(['entry_type' => 'OTHER PAYMENTS/DEPOSITS', 'entry_description' => $description, 'dr_cr' => 'CR', 'net_amount' => 10]);

        $this->assertTrue($service->isUtilityLine($line('Water Bill')));
        $this->assertFalse($service->isUtilityLine($line('House Deposit')));
        $this->assertFalse($service->isUtilityLine($line('Water Deposit')));
    }

    public function test_debits_and_balances_are_never_utility(): void
    {
        $service = app(ReturnStatementService::class);

        foreach ([
            ['Commission', 'DR'], ['VAT', 'DR'], ['Water Bill', 'DR'], ['Advance Payments-From Landlord Acc', 'DR'],
            ['Other Receipts-From Landlord Acc', 'CR'], ['Add Back Deductions', 'CR'], ['RETURN NET SUM', 'CR'],
        ] as [$type, $side]) {
            $line = (new ReturnClient())->forceFill(['entry_type' => $type, 'entry_description' => $type, 'dr_cr' => $side, 'net_amount' => 10]);
            $this->assertFalse($service->isUtilityLine($line), "{$side} {$type}");
        }
    }

    public function test_an_unknown_setting_is_the_default(): void
    {
        $this->assertSame('with_rent', UtilityReturnsService::normalise(null));
        $this->assertSame('with_rent', UtilityReturnsService::normalise('anything'));
        $this->assertSame('separate_report', UtilityReturnsService::normalise('separate_report'));
        $this->assertSame('separate_return', UtilityReturnsService::normalise('separate_return'));
    }

    public function test_a_nameless_catch_all_line_stays_with_the_rent_as_in_generation(): void
    {
        $service = app(ReturnStatementService::class);
        $line = fn(string $description) => (new ReturnClient())->forceFill(['entry_type' => 'OTHER PAYMENTS/DEPOSITS', 'entry_description' => $description, 'dr_cr' => 'CR', 'net_amount' => 10]);

        // A collection with no item name goes to the rent return when returns are generated, so the report agrees
        $this->assertFalse($service->isUtilityLine($line('')));
        $this->assertTrue($service->isUtilityLine($line('Refunds')));
    }
}
