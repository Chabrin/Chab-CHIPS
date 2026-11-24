<?php

namespace Tests\Unit;

use App\Exports\ReturnStatementExport;
use App\Models\ReturnClient;
use App\Models\ReturnSummary;
use App\Services\ReturnStatementService;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Tests\TestCase;

class ReturnStatementServiceTest extends TestCase
{
    private function line(int $id, string $type, float $amount, array $extra = []): ReturnClient
    {
        $line = new ReturnClient();
        $line->forceFill(array_merge(['id' => $id, 'entry_type' => $type, 'net_amount' => $amount,
            'property_code' => 'P1', 'unit_code' => 'B2', 'tenant_name' => 'Anne', 'remarks' => ''], $extra));
        return $line;
    }

    public function test_signed_balances_and_totals_survive_pivoting_without_mixing_properties(): void
    {
        $data = app(ReturnStatementService::class)->build(collect([
            $this->line(1, 'RENT CURRENT', 20000), $this->line(2, 'RENT ARREARS', -3000),
            $this->line(3, 'RENT CURRENT', 10000, ['unit_code' => 'B10']),
            $this->line(4, 'RENT CURRENT', 12000, ['property_code' => 'P2']),
            $this->line(5, 'Commission', 2000, ['unit_code' => null, 'tenant_name' => null]),
        ]));
        $this->assertCount(3, $data['tenantRows']);
        $this->assertSame(['B2', 'B2', 'B10'], array_column($data['tenantRows'], 'unit_code'));
        $this->assertSame(-3000.0, $data['totals']['rent_arrears']);
        $this->assertSame(42000.0, $data['totals']['monthly_rent']);
    }

    /** A return shaped like the Hawaki Plaza July 2026 return, with every kind of line it carries. */
    private function hawakiShapedLines(): \Illuminate\Support\Collection
    {
        $dr = ['dr_cr' => 'DR', 'unit_code' => null, 'tenant_name' => null];

        return collect([
            // The rent lines' vat_amount holds the rent VAT on old-system lines; arrears lines hold none.
            $this->line(1, 'RENT CURRENT', 26796, ['unit_code' => '001', 'tenant_name' => 'Shop', 'is_vatable' => 'Yes', 'vat_amount' => 3696, 'amount_payable_landlord' => 26796]),
            $this->line(2, 'RENT CURRENT', 0, ['unit_code' => '051', 'tenant_name' => 'Maina', 'is_vatable' => 'Yes', 'amount_payable_landlord' => 15312]),
            $this->line(3, 'RENT ARREARS', 58464, ['unit_code' => '051', 'tenant_name' => 'Maina', 'is_vatable' => 'Yes', 'narration' => 'RENT FOR May 2026', 'transaction_date' => '13-Jul-2026']),
            $this->line(4, 'RENT CURRENT', 17600, ['unit_code' => 'F01', 'tenant_name' => 'Resident', 'is_vatable' => 'No', 'amount_payable_landlord' => 17600]),
            $this->line(5, 'Service Charge', 150, ['unit_code' => '001', 'tenant_name' => 'Shop']),
            $this->line(6, 'House Deposit', 18000, ['unit_code' => 'F01', 'tenant_name' => 'Resident']),
            $this->line(7, 'Electricity Deposit', 3000, ['unit_code' => 'F01', 'tenant_name' => 'Resident']),
            $this->line(8, 'Deductions Before Commission', 11760, array_merge($dr, ['transaction_source' => 'COMPUTED'])),
            $this->line(9, 'Add Back Deductions', 11760, ['unit_code' => null, 'tenant_name' => null, 'dr_cr' => 'CR', 'transaction_source' => 'COMPUTED']),
            $this->line(10, 'Commission', 7288, array_merge($dr, ['transaction_source' => 'COMPUTED'])),
            $this->line(11, 'VAT', 1166, array_merge($dr, ['transaction_source' => 'COMPUTED'])),
            // Old-system catch-all type: named by its description, so a water bill is a column and a refund is not.
            $this->line(12, 'OTHER PAYMENTS/DEPOSITS', 1000, ['dr_cr' => 'CR', 'entry_description' => 'Refunds', 'unit_code' => null, 'tenant_name' => null]),
            $this->line(13, 'OTHER PAYMENTS/DEPOSITS', 500, ['dr_cr' => 'CR', 'entry_description' => 'Water Bill', 'unit_code' => '001', 'tenant_name' => 'Shop']),
            $this->line(14, 'Recoveries', 60, array_merge($dr, ['unit_code' => '039', 'narration' => 'SAT DEPOSIT', 'transaction_ref' => 'RENT FOR May 2026', 'transaction_source' => 'TENANT ACCOUNT'])),
            $this->line(15, 'Other Receipts-From Landlord Acc', 10000, ['dr_cr' => 'CR', 'unit_code' => null, 'tenant_name' => null, 'narration' => 'Opening at Start of Month']),
            $this->line(16, 'Advance Payments-From Landlord Acc', 5000, array_merge($dr, ['narration' => 'ADVANCE PAYMENTS', 'transaction_ref' => '164532 CO-OP', 'source_date' => '2026-07-03 00:00:00'])),
            $this->line(17, 'Advance Payments-From Landlord Acc', 100, array_merge($dr, ['narration' => 'CHEQUE CHARGES'])),
        ]);
    }

    public function test_old_system_return_gives_the_two_vats_and_every_block_like_the_printed_statement(): void
    {
        $lines = $this->hawakiShapedLines();
        $return = new ReturnSummary(['return_code' => 'R1', 'commission_rate' => 8, 'net_amount' => 121896]);
        $data = app(ReturnStatementService::class)->statement($return, $lines, collect());
        $summary = $data['summary'];

        $this->assertSame(11760.0, $summary['rent_vat']);
        $this->assertSame(91100.0, $summary['commissionable']);
        $this->assertSame(7288.0, $summary['commission']);
        $this->assertSame(1166.0, $summary['vat_on_commission']);
        $this->assertSame(82646.0, $summary['bal_after_vat']);
        $this->assertSame(500.0, $summary['bills']);
        $this->assertSame(1000.0, $summary['other_payments']);
        $this->assertSame(117056.0, $summary['total_income']);

        // I to O: recoveries, opening balance, landlord-account deductions, amount payable; no gap against the stored net.
        $block = collect($data['settlementRows'])->keyBy(fn($row) => strtok($row['label'], '.'));
        $this->assertSame(60.0, $block['I']['amount']);
        $this->assertSame(116996.0, $block['J']['amount']);
        $this->assertSame(10000.0, $block['K']['amount']);
        $this->assertSame(5100.0, $block['L']['amount']);
        $this->assertSame(121896.0, $block['M']['amount']);
        $this->assertSame(121896.0, $block['O']['amount']);
        $this->assertNull(collect($data['settlementRows'])->firstWhere('kind', 'gap'));
        $this->assertSame(['SAT DEPOSIT 039', 'RENT FOR May 2026'], [$data['settlementRows'][0]['label'], $data['settlementRows'][0]['ref']]);
        $advance = collect($data['settlementRows'])->firstWhere('ref', '164532 CO-OP');
        $this->assertSame('03-Jul-2026', $advance['date']);
        $this->assertSame([['date' => '13-Jul-2026', 'details' => 'RENT FOR May 2026', 'room' => '051', 'amount' => 58464.0]], $data['arrears']);

        // The rows agree with the summary: VAT includes arrears, and Payable is the invoiced rent, not what was collected.
        $room = collect($data['tenantRows'])->firstWhere('unit_code', '051');
        $this->assertSame(8064.0, $room['vat']);
        $this->assertSame(15312.0, $room['payable']);
        $this->assertSame(0.0, $room['monthly_rent']);
        $this->assertSame(59708.0, $data['totals']['payable']);
        $this->assertSame(500.0, collect($data['tenantRows'])->firstWhere('unit_code', '001')['water_bill']);
    }

    public function test_a_statement_that_does_not_foot_to_the_stored_net_says_by_how_much(): void
    {
        $return = new ReturnSummary(['return_code' => 'R1', 'commission_rate' => 8, 'net_amount' => 120000]);
        $rows = app(ReturnStatementService::class)->statement($return, $this->hawakiShapedLines(), collect())['settlementRows'];
        $gap = collect($rows)->firstWhere('kind', 'gap');
        $this->assertSame(1896.0, $gap['amount']);
    }

    public function test_ledger_debits_with_no_line_are_listed_as_deductions_and_bring_the_statement_to_the_stored_net(): void
    {
        $lines = $this->hawakiShapedLines()->reject(fn($line) => $line->id === 17);
        $return = new ReturnSummary(['return_code' => 'R1', 'commission_rate' => 8, 'net_amount' => 121896]);
        $gaps = collect([(object) ['entry_description' => 'CHEQUE CHARGES', 'entry_particulars' => null, 'payment_ref' => null,
            'payment_ref_type' => null, 'entry_date' => '2026-07-06', 'debit_amount' => 100]]);
        $rows = app(ReturnStatementService::class)->statement($return, $lines, $gaps)['settlementRows'];
        $this->assertSame('06-Jul-2026', collect($rows)->firstWhere('label', 'CHEQUE CHARGES')['date']);
        $this->assertSame(121896.0, collect($rows)->first(fn($row) => str_starts_with($row['label'], 'M.'))['amount']);
        $this->assertNull(collect($rows)->firstWhere('kind', 'gap'));
    }

    public function test_ledger_gaps_are_used_only_when_the_lines_miss_the_stored_net_and_the_gaps_close_it(): void
    {
        $service = app(ReturnStatementService::class);
        $gap = fn(float $amount) => collect([(object) ['entry_description' => 'LOST CHEQUE', 'entry_particulars' => null, 'payment_ref' => null,
            'payment_ref_type' => null, 'entry_date' => '2026-07-06', 'debit_amount' => $amount]]);
        $amountPayable = fn(array $rows) => collect($rows)->first(fn($row) => str_starts_with($row['label'], 'M.'))['amount'];
        $withoutCheque = $this->hawakiShapedLines()->reject(fn($line) => $line->id === 17);

        // The lines already reach the stored net: a ledger row is not added on top.
        $reached = new ReturnSummary(['return_code' => 'R1', 'commission_rate' => 8, 'net_amount' => 121896]);
        $this->assertSame(121896.0, $amountPayable($service->statement($reached, $this->hawakiShapedLines(), $gap(100))['settlementRows']));

        // The lines are 100 short of a deduction and the gap is that 100: it is listed.
        $this->assertSame(121896.0, $amountPayable($service->statement($reached, $withoutCheque, $gap(100))['settlementRows']));

        // The gap does not close the difference: it is not listed, and the red row says by how much the lines differ.
        $rows = $service->statement($reached, $withoutCheque, $gap(5000))['settlementRows'];
        $this->assertSame(121996.0, $amountPayable($rows));
        $this->assertSame(100.0, collect($rows)->firstWhere('kind', 'gap')['amount']);
        $this->assertNull(collect($rows)->firstWhere('label', 'LOST CHEQUE'));
    }

    public function test_an_old_return_that_charged_commission_but_stored_no_rent_vat_deducted_none(): void
    {
        $service = app(ReturnStatementService::class);
        $lines = collect([
            $this->line(1, 'RENT CURRENT', 24000, ['unit_code' => 'A1', 'tenant_name' => 'a', 'is_vatable' => 'Yes', 'amount_payable_landlord' => 24000]),
            $this->line(2, 'Commission', 2400, ['dr_cr' => 'DR', 'unit_code' => null, 'tenant_name' => null, 'transaction_source' => 'COMPUTED']),
        ]);
        $data = $service->build($lines);
        $summary = $service->summary(new ReturnSummary(['return_code' => 'R2']), $lines, $data['totals']);
        $this->assertSame(0.0, $data['totals']['vat']);
        $this->assertSame(0.0, $data['tenantRows'][0]['vat']);
        $this->assertSame(24000.0, $summary['commissionable']);
        $this->assertSame(21600.0, $summary['total_income']);
    }

    public function test_the_stored_rent_vat_is_the_vat_total_and_the_rooms_keep_their_own_cents(): void
    {
        $lines = $this->hawakiShapedLines()->map(function ($line) {
            if ($line->id === 8) {
                $line->net_amount = 11761;
            }
            return $line;
        });
        $data = app(ReturnStatementService::class)->build($lines);
        $this->assertSame(11761.0, $data['totals']['vat']);
        $this->assertSame(8064.0, collect($data['tenantRows'])->firstWhere('unit_code', '051')['vat']);
    }

    public function test_generated_return_keeps_vat_on_commission_out_of_the_rent_vat_and_ignores_the_commission_repeat_line(): void
    {
        // A return generated here: vat_amount on a rent line is the VAT on its commission, and a DR COMMISSION line repeats the totals.
        $lines = collect([
            $this->line(1, 'RENT CURRENT', 20000, ['commission_amount' => 2000, 'vat_amount' => 320]),
            $this->line(2, 'COMMISSION', 2000, ['dr_cr' => 'DR', 'vat_amount' => 320, 'entry_description' => 'Commission', 'transaction_source' => 'COMPUTED']),
        ]);
        $service = app(ReturnStatementService::class);
        $data = $service->build($lines);
        $summary = $service->summary(new ReturnSummary(['return_code' => 'R1']), $lines, $data['totals']);

        $this->assertSame(0.0, $data['totals']['vat']);
        $this->assertSame(20000.0, $summary['commissionable']);
        $this->assertSame(2000.0, $summary['commission']);
        $this->assertSame(320.0, $summary['vat_on_commission']);
        $this->assertSame(17680.0, $summary['total_income']);
    }

    public function test_only_credits_feed_the_columns_so_a_debit_named_rent_is_a_recovery_not_income(): void
    {
        $lines = collect([
            $this->line(1, 'RENT CURRENT', 20000),
            $this->line(2, 'RENT', 700, ['dr_cr' => 'DR', 'unit_code' => null, 'tenant_name' => null, 'transaction_source' => 'LANDLORD ACCOUNT', 'narration' => 'Rent paid direct']),
        ]);
        $service = app(ReturnStatementService::class);
        $data = $service->build($lines);
        $this->assertSame(20000.0, $data['totals']['monthly_rent']);
        $rows = $service->settlement(new ReturnSummary(['return_code' => 'R1']), $lines, $service->summary(new ReturnSummary(['return_code' => 'R1']), $lines, $data['totals']), collect());
        $this->assertSame(700.0, collect($rows)->firstWhere('label', 'I. Total deductions')['amount']);
    }

    public function test_early_abbreviations_and_description_named_deposits_land_in_their_own_columns(): void
    {
        $data = app(ReturnStatementService::class)->build(collect([
            $this->line(1, 'WABILL', 300), $this->line(2, 'ELBILL', 200), $this->line(3, 'HODE', 5000), $this->line(4, 'ELDE', 400), $this->line(5, 'WADE', 100),
            $this->line(6, 'OTHER PAYMENTS/DEPOSITS', 700, ['entry_description' => 'House Deposit', 'unit_code' => 'B3']),
            $this->line(7, 'OTHER PAYMENTS/DEPOSITS', 90, ['entry_description' => 'Service Charge', 'unit_code' => 'B3']),
            $this->line(8, 'OTHERRECS', 2000, ['unit_code' => null, 'tenant_name' => null]),
        ]));
        $totals = $data['totals'];
        $this->assertSame([300.0, 200.0, 5700.0, 400.0, 100.0, 90.0, 2000.0],
            [$totals['water_bill'], $totals['elec_bill'], $totals['house_deposit'], $totals['elec_deposit'], $totals['water_deposit'], $totals['service_charge'], $totals['other_payments']]);
    }

    public function test_rooms_follow_the_old_systems_order_where_lines_carry_it_else_natural_order(): void
    {
        $service = app(ReturnStatementService::class);
        $withSeq = $service->build(collect([
            $this->line(1, 'RENT CURRENT', 1, ['unit_code' => 'F01', 'unit_seq_id' => 110, 'tenant_name' => 'a']),
            $this->line(2, 'RENT CURRENT', 1, ['unit_code' => 'STORE', 'unit_seq_id' => 109, 'tenant_name' => 'b']),
            $this->line(3, 'RENT CURRENT', 1, ['unit_code' => '001', 'unit_seq_id' => 1, 'tenant_name' => 'c']),
        ]));
        $this->assertSame(['001', 'STORE', 'F01'], array_column($withSeq['tenantRows'], 'unit_code'));
        $natural = $service->build(collect([
            $this->line(1, 'RENT CURRENT', 1, ['unit_code' => 'B10', 'tenant_name' => 'a']),
            $this->line(2, 'RENT CURRENT', 1, ['unit_code' => 'B2', 'tenant_name' => 'b']),
        ]));
        $this->assertSame(['B2', 'B10'], array_column($natural['tenantRows'], 'unit_code'));
    }

    public function test_lists_read_chronologically_and_rooms_drop_the_property_prefix_of_units_created_here(): void
    {
        $dr = ['dr_cr' => 'DR', 'unit_code' => null, 'tenant_name' => null, 'transaction_source' => 'LANDLORD ACCOUNT'];
        $lines = collect([
            $this->line(1, 'RENT CURRENT', 1000, ['property_code' => '272E', 'unit_code' => '272e-A1', 'tenant_name' => 'Anne']),
            $this->line(2, 'RENT ARREARS', 300, ['property_code' => '272E', 'unit_code' => '272e-A1', 'tenant_name' => 'Anne', 'narration' => 'Rent for November 2026']),
            $this->line(3, 'RENT ARREARS', 200, ['property_code' => '272E', 'unit_code' => '272e-A1', 'tenant_name' => 'Anne', 'narration' => 'Rent for March 2026']),
            $this->line(4, 'ADVANCE PAYMENTS', 50, array_merge($dr, ['narration' => 'later', 'source_date' => '2026-07-16 10:00:00'])),
            $this->line(5, 'ADVANCE PAYMENTS', 70, array_merge($dr, ['narration' => 'earlier', 'source_date' => '2026-07-03 10:00:00'])),
        ]);
        $return = new ReturnSummary(['return_code' => 'R1']);
        $data = app(ReturnStatementService::class)->statement($return, $lines, collect());

        $this->assertSame('A1', $data['tenantRows'][0]['unit_code']);
        $this->assertSame(['Rent for March 2026', 'Rent for November 2026'], array_column($data['arrears'], 'details'));
        $this->assertSame('A1', $data['arrears'][0]['room']);
        $deductions = array_values(array_filter($data['settlementRows'], fn($row) => $row['kind'] === 'item'));
        $this->assertSame(['earlier', 'later'], array_column($deductions, 'label'));
    }

    public function test_a_room_whose_tenant_changed_is_one_row_named_after_the_real_tenant(): void
    {
        $data = app(ReturnStatementService::class)->build(collect([
            $this->line(1, 'RENT CURRENT', 0, ['unit_code' => 'F19', 'tenant_name' => '[VACANT]', 'amount_payable_landlord' => 17600]),
            $this->line(2, 'RENT ARREARS', 17600, ['unit_code' => 'F19', 'tenant_name' => 'Dorcas']),
        ]));
        $this->assertCount(1, $data['tenantRows']);
        $this->assertSame('Dorcas', $data['tenantRows'][0]['tenant_name']);
        $this->assertSame(17600.0, $data['tenantRows'][0]['payable']);
    }

    public function test_note_comes_from_the_current_rent_line_regardless_of_line_order(): void
    {
        $service = app(ReturnStatementService::class);
        $lines = collect([$this->line(3, 'Water Bill', 500, ['remarks' => 'Water note']),
            $this->line(2, 'RENT CURRENT', 10000, ['remarks' => 'Tenant note']),
            $this->line(1, 'RENT ARREARS', 2000, ['remarks' => 'Older note'])]);
        foreach ([$lines, $lines->reverse()] as $input) {
            $row = $service->build($input)['tenantRows'][0];
            $this->assertSame(2, $row['remarks_line_id']);
            $this->assertSame('Tenant note', $row['remarks']);
        }
        $this->assertSame('Water note', $lines[0]->remarks);
    }

    public function test_prepaid_rent_does_not_get_mixed_into_current_month_rent(): void
    {
        $data = app(ReturnStatementService::class)->build(collect([
            $this->line(1, 'RENT CURRENT', 20000), $this->line(2, 'RENT PREPAYMENT', -5000),
        ]));
        $this->assertSame(-5000.0, $data['tenantRows'][0]['rent_arrears']);
        $this->assertSame(20000.0, $data['tenantRows'][0]['monthly_rent']);
    }

    public function test_excel_matches_statement_columns_and_treats_remarks_as_text(): void
    {
        $return = new ReturnSummary(['return_code' => 'R1', 'commission_total' => 20000, 'commission_rate' => 10]);
        $lines = collect([$this->line(1, 'RENT CURRENT', 20000, ['remarks' => '=1+1', 'commission_amount' => 2000, 'vat_amount' => 320])]);
        $data = app(ReturnStatementService::class)->statement($return, $lines, collect());
        $title = 'Sample Property - November 2026 - Main Return';
        $book = app(ReturnStatementExport::class)->build($return, $data,
            ['title' => $title, 'landlord_label' => 'Landlord', 'property_label' => 'Property', 'period' => 'November 2026'], []);
        $sheet = $book->getActiveSheet();
        $this->assertSame($title, $book->getProperties()->getTitle());
        $this->assertSame($title, $sheet->getCell('A2')->getValue());
        $this->assertSame('Monthly Rent', $sheet->getCell('L8')->getValue());
        $this->assertSame('Remarks', $sheet->getCell('M8')->getValue());
        $this->assertSame('Arrears / Prepayments', $sheet->getCell('D8')->getValue());
        $this->assertSame('=1+1', $sheet->getCell('M9')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('M9')->getDataType());
        $this->assertSame(17680.0, $data['summary']['total_income']);
        $values = array_column($sheet->toArray(), 0);
        $this->assertSame(1, count(array_filter($values, fn($value) => $value === 'Prepared by')));
        $this->assertStringNotContainsString('Prepared', $sheet->getHeaderFooter()->getOddFooter());
        $book->disconnectWorksheets();
    }

    public function test_screen_escapes_notes_and_places_summary_after_the_tenant_table(): void
    {
        $return = new ReturnSummary(['id' => 1, 'return_code' => 'R1', 'commission_total' => 10000]);
        $lines = collect([
            $this->line(1, 'RENT CURRENT', 10000, ['remarks' => '<script>alert(1)</script>']),
            $this->line(2, 'Service Charge', 150),
        ]);
        $data = app(ReturnStatementService::class)->statement($return, $lines, collect());
        $html = view('property.processed-returns.partials.statement', array_merge($data, ['return' => $return]))->render();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $overview = substr($html, 0, strpos($html, '<table'));
        $this->assertStringContainsString('Service charges', $overview);
        $this->assertStringContainsString('150.00', $overview);
        $this->assertStringContainsString('10,150.00', $overview);
        $this->assertLessThan(strpos($html, 'Financial summary'), strpos($html, '</table>'));
        $this->assertStringContainsString('statement-save', $html);
    }

    public function test_both_detail_pages_keep_processing_controls_and_the_statement_container(): void
    {
        $return = new ReturnSummary(['id' => 1, 'return_code' => 'R1', 'return_type' => 'MAINRETURN', 'paid_status' => 0]);
        $headerInfo = ['return_code' => 'R1', 'heading' => 'Sample property - November 2026 - Main Return',
            'property_label' => 'Sample property', 'landlord_label' => 'Sample landlord',
            'period' => '11/2026', 'return_type' => 'MAINRETURN'];
        foreach (['property.processed-returns.details', 'property.returns.unprocessed-details'] as $view) {
            $html = view($view, ['return' => $return, 'lineTotals' => (object) [],
                'allReturns' => collect([$return]), 'headerInfo' => $headerInfo])->render();
            $this->assertStringContainsString('Sample property - November 2026 - Main Return', $html);
            $this->assertStringContainsString('id="return-transaction-details"', $html);
            $this->assertStringContainsString('id="return-statement"', $html);
            $this->assertStringContainsString('return-statement.js', $html);
        }
    }

    public function test_the_rent_report_and_the_utility_report_add_up_to_the_return(): void
    {
        $service = app(ReturnStatementService::class);
        $return = new ReturnSummary(['return_code' => 'R1', 'commission_rate' => 8, 'net_amount' => 121896]);
        $lines = $this->hawakiShapedLines();
        $amountPayable = fn(array $rows) => collect($rows)->first(fn($row) => str_starts_with($row['label'], 'M.'))['amount'];

        $whole = $service->statement($return, $lines, collect());
        $rent = $service->statement($return, $lines, collect(), ReturnStatementService::PART_RENT);
        $utility = $service->statement($return, $lines, collect(), ReturnStatementService::PART_UTILITY);

        // Service charge 150, water bill 500 and the refund 1,000 (an item with no column is not rent) are the utility side
        $this->assertSame(1650.0, $utility['summary']['total']);
        $this->assertSame(150.0, $utility['summary']['service_charge']);
        $this->assertSame(500.0, $utility['summary']['water_bill']);
        $this->assertSame(1000.0, $utility['summary']['other_items']);

        // The rent report is the rest, with the commission, VAT and every deduction, and the two foot to the return
        $this->assertSame(121896.0 - 1650.0, $amountPayable($rent['settlementRows']));
        $this->assertSame(121896.0, round($amountPayable($rent['settlementRows']) + $utility['summary']['total'], 2));
        $this->assertSame($amountPayable($whole['settlementRows']), round($amountPayable($rent['settlementRows']) + $utility['summary']['total'], 2));
        $this->assertNull(collect($rent['settlementRows'])->firstWhere('kind', 'gap'), 'the rent report reaches its own net, so no gap row');
        $this->assertSame($whole['summary']['commission'], $rent['summary']['commission']);

        // Each report shows only its own columns and rows
        $this->assertArrayNotHasKey('service_charge', $rent['columns']);
        $this->assertArrayHasKey('house_deposit_amt', $rent['columns']);
        $this->assertSame(['unit_code', 'tenant_name', 'elec_bill', 'water_bill', 'service_charge', 'remarks'], array_keys($utility['columns']));
        $this->assertNotContains('bills', array_column($rent['summaryRows'], 0));
        $this->assertSame([], $utility['settlementRows']);
        $this->assertSame(0.0, $rent['totals']['water_bill']);
        $this->assertSame(500.0, $utility['totals']['water_bill']);
    }

    public function test_a_utility_return_shows_only_utility_columns(): void
    {
        $return = new ReturnSummary(['return_code' => 'RU1202607LAN-00001P1', 'commission_rate' => 0, 'net_amount' => 650]);
        $lines = collect([
            $this->line(1, 'Service Charge', 150, ['unit_code' => '001', 'tenant_name' => 'Shop']),
            $this->line(2, 'Water Bill', 500, ['unit_code' => '001', 'tenant_name' => 'Shop']),
        ]);
        $data = app(ReturnStatementService::class)->statement($return, $lines, collect());

        $this->assertSame(['unit_code', 'tenant_name', 'elec_bill', 'water_bill', 'service_charge', 'remarks'], array_keys($data['columns']));
        $this->assertSame(['bills', 'service_charge', 'other_payments', 'total_income'], array_column($data['summaryRows'], 0));
        $this->assertSame(650.0, $data['summary']['total_income']);
        $this->assertSame(650.0, collect($data['settlementRows'])->first(fn($row) => str_starts_with($row['label'], 'M.'))['amount']);
        $this->assertNull(collect($data['settlementRows'])->firstWhere('kind', 'gap'));
    }

    public function test_the_excel_of_each_part_has_only_its_own_columns(): void
    {
        $service = app(ReturnStatementService::class);
        $return = new ReturnSummary(['return_code' => 'R1', 'commission_rate' => 8, 'net_amount' => 121896]);
        $lines = $this->hawakiShapedLines();
        $header = ['title' => 'Sample - July 2026 - Main Return', 'landlord_label' => 'Landlord', 'property_label' => 'Property', 'period' => 'July 2026'];
        $labels = function ($sheet) {
            $row = [];
            foreach (range('A', 'M') as $col) {
                $value = $sheet->getCell($col . '8')->getValue();
                if ($value !== null && $value !== '') {
                    $row[] = $value;
                }
            }

            return $row;
        };

        // The rent report has no utility columns and its remarks are the last column
        $rent = app(ReturnStatementExport::class)->build($return, $service->statement($return, $lines, collect(), ReturnStatementService::PART_RENT), $header, []);
        $rentLabels = $labels($rent->getActiveSheet());
        $this->assertNotContains('Svc Charge', $rentLabels);
        $this->assertNotContains('Water Bill', $rentLabels);
        $this->assertSame('Remarks', end($rentLabels));
        $this->assertSame('Monthly Rent', $rent->getActiveSheet()->getCell('I8')->getValue());
        $this->assertStringContainsString('RENT REPORT', $rent->getActiveSheet()->getCell('A1')->getValue());

        // The utility report is its own workbook with only the utility columns
        $utilityData = $service->statement($return, $lines, collect(), ReturnStatementService::PART_UTILITY);
        $utility = app(ReturnStatementExport::class)->buildUtility($return, $utilityData, $header, []);
        $this->assertSame(['Room', 'Tenant', 'Elec Bill', 'Water Bill', 'Svc Charge', 'Remarks'], $labels($utility->getActiveSheet()));

        // A utility return (code RU...) has the utility columns in its normal statement workbook
        $ru = new ReturnSummary(['return_code' => 'RU1202607LAN-00001P1', 'net_amount' => 650]);
        $ruLines = collect([$this->line(1, 'Service Charge', 150), $this->line(2, 'Water Bill', 500)]);
        $ruBook = app(ReturnStatementExport::class)->build($ru, $service->statement($ru, $ruLines, collect()), $header, []);
        $this->assertSame(['Room', 'Tenant', 'Elec Bill', 'Water Bill', 'Svc Charge', 'Remarks'], $labels($ruBook->getActiveSheet()));
        $this->assertStringContainsString('UTILITY RETURN STATEMENT', $ruBook->getActiveSheet()->getCell('A1')->getValue());
    }
}
